<?php

namespace App\Services\Integration;

use App\Exceptions\SppkmsPostSentException;
use App\Models\Kutipan;
use App\Models\Pembayaran;
use App\Models\SppkmsSync;
use App\Services\Security\SecretVaultService;
use App\Support\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Dual-write ke SPPKMS lama (Fasa 8) — jambatan SEMENTARA: setiap
 * kutipan/pembayaran BARU dalam sistem ini turut di-POST ke borang
 * sistem lama (spm.mesrasuci.com) supaya kedua-dua sistem selari
 * semasa tempoh peralihan.
 *
 * KEPUTUSAN REKA BENTUK:
 *   - semak='off' SENTIASA (no resit/baucer manual = nombor KITA) —
 *     JANGAN ganggu kaunter auto SPPKMS lama.
 *   - Idempoten melalui jadual sppkms_sync (UNIQUE masjid+jenis+sumber,
 *     simpan recno SPPKMS sebagai bukti).
 *   - Bayaran jenis ASET → SKIPPED (borang aset lama kompleks: daftar
 *     aset + susut nilai serentak — hantar manual).
 *   - Sesi PHPSESSID di-cache 10 minit; login semula SEKALI jika
 *     respons menunjukkan sesi luput (redirect ke index/login).
 */
class SppkmsDualWriteService
{
    private const TEMPOH_SESI = 600; // saat (10 minit)

    public function __construct(private SecretVaultService $vault)
    {
    }

    /**
     * Log masuk ke SPPKMS lama; pulangkan cookies ['PHPSESSID' => ...].
     * Kredensial dibaca dari vault melalui rujukan dalam app_setting
     * (sppkms_login_ref / sppkms_password_ref).
     */
    public function login(?int $masjidId = null): array
    {
        $loginRef = Setting::get('sppkms_login_ref', null, $masjidId);
        $passRef = Setting::get('sppkms_password_ref', null, $masjidId);
        if (!$loginRef || !$passRef) {
            throw new RuntimeException('Kredensial SPPKMS belum dikonfigurasi (halaman Dual-Write SPPKMS).');
        }

        $login = $this->vault->get($loginRef);
        $password = $this->vault->get($passRef);
        if ($login === null || $password === null) {
            throw new RuntimeException('Kredensial SPPKMS tiada dalam vault — sila simpan semula.');
        }

        $resp = Http::asForm()
            ->withOptions(['allow_redirects' => false, 'verify' => config('dualwrite.verify', true)])
            ->post($this->url('login-exec.php'), [
                'login'    => $login,
                'password' => $password,
                'hp_field' => '', // honeypot — mesti KOSONG
            ]);

        $sessid = $this->ekstrakPhpsessid($resp);
        if ($sessid === null) {
            throw new RuntimeException('Login SPPKMS gagal: tiada cookie PHPSESSID dalam respons.');
        }

        $lokasi = (string) $resp->header('Location');
        if ($lokasi !== '' && preg_match('/index\.php|login/i', $lokasi)) {
            throw new RuntimeException('Login SPPKMS gagal: kredensial ditolak (redirect ke '.$lokasi.').');
        }

        return ['PHPSESSID' => $sessid];
    }

    /**
     * Hantar SATU baris sppkms_sync ke borang SPPKMS lama dan tanda
     * DONE + recno; ASET → SKIPPED; tiada recno dalam respons → throw.
     */
    public function hantar(SppkmsSync $sync): void
    {
        [$endpoint, $fields] = $this->binaPermintaan($sync);

        if ($endpoint === null) {
            // Bayaran ASET — borang aset lama kompleks, hantar manual
            $sync->update([
                'status'     => 'SKIPPED',
                'last_error' => 'Aset: hantar manual',
                'done_at'    => now(),
            ]);

            return;
        }

        $resp = $this->postDenganSesi((int) $sync->masjid_id, $endpoint, $fields);

        [$berjaya, $recno, $sebab] = $this->tafsirRespons($resp, $endpoint);

        if (!$berjaya) {
            // POST SUDAH dihantar tetapi rekod TIDAK disahkan tercipta (borang render
            // semula / respons luar jangka). JANGAN retry buta (risiko pendua kewangan).
            throw new SppkmsPostSentException(
                "SPPKMS {$endpoint}: {$sebab} (HTTP {$resp->status()}). "
                .'SEMAK MANUAL di SPPKMS sama ada rekod tercipta sebelum cuba semula.'
            );
        }

        try {
            $sync->update([
                'sppkms_endpoint' => $endpoint,
                'sppkms_recno'    => $recno, // boleh null (cth rekupmen — redirect tanpa recno)
                'status'          => 'DONE',
                'last_error'      => null,
                'done_at'         => now(),
            ]);
        } catch (\Throwable $e) {
            // E7 — POST BERJAYA tetapi simpanan status DONE tempatan gagal (DB blip).
            // Retry buta akan RE-POST → PENDUA. Layan sebagai "sudah dihantar":
            // FAILED + amaran semak-manual, TIADA cubaan semula automatik.
            throw new SppkmsPostSentException(
                "SPPKMS {$endpoint}: POST berjaya (recno={$recno}) tetapi gagal simpan status DONE tempatan: "
                .$e->getMessage().'. SEMAK MANUAL sebelum cuba semula (elak pendua).'
            );
        }
    }

    // ------------------------------------------------------------------
    //  Pembinaan medan POST per jenis (medan TEPAT borang lama —
    //  disahkan empirik melalui skrip Playwright spm-explore)
    // ------------------------------------------------------------------

    /** @return array{0:?string,1:array} [endpoint, medan POST]; endpoint null = SKIP */
    private function binaPermintaan(SppkmsSync $sync): array
    {
        if ($sync->source_type === 'KUTIPAN') {
            $k = Kutipan::withoutMasjidScope()->with(['coa', 'bank'])->findOrFail($sync->source_id);

            return ['kutipan_save.php', $this->medanKutipan($k)];
        }

        $p = Pembayaran::withoutMasjidScope()->with(['coa', 'pwrCoa', 'bank'])->findOrFail($sync->source_id);

        return match ($p->jenis) {
            'ASET'     => [null, []],
            'REKUPMEN' => ['pwr_rekupmen.php', $this->medanRekupmen($p)],
            default    => ['belanja_expense.php', $this->medanBelanja($p)],
        };
    }

    /** kutipan_form.php → POST kutipan_save.php */
    private function medanKutipan(Kutipan $k): array
    {
        $kaedah = match ($k->kaedah) {
            'TUNAI' => '0',
            'CEK'   => '1',
            default => '2', // BANK_TRANSFER_QR
        };

        // NOTA: checkbox 'semak' (auto-resit) TIDAK disertakan — borang sebenar
        // hanya menghantar medan checkbox apabila DITANDA. Untuk no resit MANUAL
        // (kita), checkbox dikosongkan = medan TIADA dalam POST (disahkan empirik:
        // skrip Playwright uncheck #semak). Kaunter auto SPPKMS tidak terganggu.
        return [
            'combined_jenis' => $k->coa->kod.'|',
            'kaedah'         => $kaedah,
            'tar_kutipan'    => $k->tarikh?->format('Y-m-d'),
            'jum_kutipan'    => (string) $k->jumlah,
            'noresit'        => (string) $k->no_resit,
            'namapemberi'    => (string) ($k->nama_pemberi ?? ''),
            // Borang kutipan lama: <select name="bank"> value = NOMBOR SLOT (cth "1"),
            // bukan label. Disahkan via rakaman option sebenar (70-capture-options.mjs).
            'bank'           => $k->bank ? (string) $k->bank->slot : '',
            'noslip'         => (string) ($k->no_slip ?? ''),
            'tar_bankin'     => $k->tar_bankin?->format('Y-m-d') ?? '',
            'deskripsi'      => (string) ($k->deskripsi ?? ''),
            'semakan'        => 'on',
        ];
    }

    /** belanja_expense.php (bayaran perbelanjaan biasa) */
    private function medanBelanja(Pembayaran $p): array
    {
        // 'semak' (checkbox auto-baucer) dikosongkan = TIDAK dihantar (lihat medanKutipan).
        return [
            'tarmohon'     => $p->tar_mohon?->format('Y-m-d'),
            'tarlulus'     => $p->tar_lulus?->format('Y-m-d'),
            'baucerno'     => (string) $p->baucer_no,
            'nobaucer'     => (string) ($p->no_baucer ?? ''),
            'pemohon'      => (string) ($p->pemohon ?? ''),
            'jenispembyrn' => $p->coa->kod,
            'deskripsi'    => (string) ($p->deskripsi ?? ''),
            'jumlah'       => (string) $p->jumlah,
            'carapembyrn'  => $this->petaCaraBayar($p->cara_bayar),
            // Borang belanja: select name="bank" — DUA select kongsi nama ini:
            //   id=bank_select (value=kod COA bank, cth "250-05010") bila bukan PWR
            //   id=pwr_select  (value=kod COA PWR,  cth "250-06010") bila PWR
            // Disahkan via rakaman POST sebenar (74-capture-belanja-post.mjs).
            'bank'         => $p->cara_bayar === 'PWR'
                ? (string) ($p->pwrCoa?->kod ?? '')
                : $this->kodCoaBank($p),
            'noacct'       => (string) ($p->no_acct ?? ''),
            'nocek'        => (string) ($p->no_cek ?? ''),
            // Medan WAJIB borang belanja_expense (disahkan via tangkapan POST hidup):
            //   is_auto_active=0 → guna no baucer MANUAL (kita); hantar='' → tanda
            //   submission (PHP semak isset($_POST['hantar'])). Tanpa 'hantar' borang
            //   hanya render semula & TIADA rekod tercipta.
            'is_auto_active' => '0',
            'hantar'         => '',
        ];
    }

    /** pwr_rekupmen.php (rekupmen PWR — pemindahan Bank → PWR) */
    private function medanRekupmen(Pembayaran $p): array
    {
        // 'semak' dikosongkan = TIDAK dihantar (lihat medanKutipan).
        return [
            'tarmohon'    => $p->tar_mohon?->format('Y-m-d'),
            'tarlulus'    => $p->tar_lulus?->format('Y-m-d'),
            'baucerno'    => (string) $p->baucer_no,
            'pwr_coa'     => $p->pwrCoa?->kod ?? '',
            'bank_coa'    => $this->kodCoaBank($p),
            'jumlah'      => (string) $p->jumlah,
            'pemohon'     => (string) ($p->pemohon ?? ''),
            'deskripsi'   => (string) ($p->deskripsi ?? ''),
            'carapembyrn' => $this->petaCaraBayar($p->cara_bayar),
            'nocek'       => (string) ($p->no_cek ?? ''),
        ];
    }

    /** Kod COA akaun bank pembayaran (bank_account.coa_id → coa.kod). */
    private function kodCoaBank(Pembayaran $p): string
    {
        if (!$p->bank?->coa_id) {
            return '';
        }

        return (string) \App\Models\Coa::withoutMasjidScope()
            ->whereKey($p->bank->coa_id)
            ->value('kod');
    }

    private function petaCaraBayar(?string $cara): string
    {
        return match ($cara) {
            'CEK'      => '0',
            'PWR'      => '1',
            'EFT'      => '2',
            'NON_CASH' => '3',
            default    => '2',
        };
    }

    // ------------------------------------------------------------------
    //  Sesi & HTTP
    // ------------------------------------------------------------------

    /**
     * POST dengan cookie sesi (cache 10 min). Jika respons menunjukkan
     * sesi luput (redirect ke index/login) → login semula SEKALI.
     */
    private function postDenganSesi(int $masjidId, string $endpoint, array $fields): Response
    {
        $resp = $this->postSekali($masjidId, $endpoint, $fields, false);

        if ($this->sesiLuput($resp)) {
            $resp = $this->postSekali($masjidId, $endpoint, $fields, true);
        }

        return $resp;
    }

    private function postSekali(int $masjidId, string $endpoint, array $fields, bool $paksaLogin): Response
    {
        $key = 'sppkms_dw_sesi_'.$masjidId;

        if ($paksaLogin) {
            Cache::forget($key);
        }

        $cookies = Cache::get($key);
        if (!is_array($cookies) || empty($cookies['PHPSESSID'])) {
            $cookies = $this->login($masjidId);
            Cache::put($key, $cookies, self::TEMPOH_SESI);
        }

        return Http::asForm()
            ->withOptions(['allow_redirects' => false, 'verify' => config('dualwrite.verify', true)])
            ->withHeaders(['Cookie' => 'PHPSESSID='.$cookies['PHPSESSID']])
            ->post($this->url($endpoint), $fields);
    }

    /** Sesi luput = redirect ke halaman login/index sistem lama. */
    private function sesiLuput(Response $resp): bool
    {
        if (!in_array($resp->status(), [301, 302, 303], true)) {
            return false;
        }

        return (bool) preg_match('/index\.php|login/i', (string) $resp->header('Location'));
    }

    /**
     * Tafsir respons POST SPPKMS: berjaya?, recno (boleh null), sebab gagal.
     * Disahkan via ujian hidup (70-74-*.mjs):
     *   - kutipan_save → 302 Location kutipan_view.php?recno=N
     *   - belanja_expense → 302 Location belanja_view.php?id=N
     *   - pwr_rekupmen → redirect ke listingPembayaran (TIADA recno) = berjaya
     *   - GAGAL → render semula borang / redirect ke index/login
     * Mengelak false-positive 'paparMasjid.php?recno=49' (pautan sidebar masjid).
     *
     * @return array{0:bool,1:?string,2:string} [berjaya, recno, sebab]
     */
    private function tafsirRespons(Response $resp, string $endpoint): array
    {
        $loc  = (string) $resp->header('Location');
        $body = $resp->body();

        // Sesi luput / kredensial ditolak (selepas postDenganSesi cuba login semula)
        if ($loc !== '' && preg_match('/index\.php|login-exec|login/i', $loc)) {
            return [false, null, 'sesi luput / kredensial ditolak'];
        }

        // recno/id dari Location header halaman view (paling boleh dipercayai)
        if ($loc !== '' && preg_match('/(?:recno|id)=(\d+)/', $loc, $m)) {
            return [true, $m[1], 'redirect view'];
        }

        // recno/id dari pautan view dalam BODY (kutipan_view/belanja_view) —
        // SPESIFIK supaya tidak terbabit paparMasjid.php?recno=<masjid_id>
        if (preg_match('/(?:kutipan_view|belanja_view)\.php\?(?:recno|id)=(\d+)/', $body, $m)) {
            return [true, $m[1], 'pautan view dalam body'];
        }

        // Borang yang pos ke dirinya & render semula dengan ALERT kejayaan SPPKMS
        // (cth pwr_rekupmen: "Berjaya! Rekupmen Berjaya Disimpan!"). recno tiada dalam
        // respons → null (jejakan idempoten via baris sync UNIQUE, bukan recno).
        if (preg_match('/Berjaya[^<]{0,60}Disimpan/i', $body)) {
            return [true, null, 'mesej kejayaan SPPKMS'];
        }

        // Ralat eksplisit dari sistem lama
        if (preg_match('/\bRalat\b|\bgagal\b/i', $body) && !preg_match('/Berjaya/i', $body)) {
            return [false, null, 'sistem lama melaporkan ralat'];
        }

        // Tidak dapat disahkan berjaya — borang mungkin render semula tanpa simpan
        return [false, null, 'tiada tanda kejayaan dalam respons'];
    }

    private function ekstrakPhpsessid(Response $resp): ?string
    {
        $headers = $resp->toPsrResponse()->getHeader('Set-Cookie');
        foreach ($headers as $h) {
            if (preg_match('/PHPSESSID=([^;,\s]+)/', $h, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    private function url(string $path): string
    {
        return rtrim((string) config('sppkms.legacy_url'), '/').'/'.$path;
    }
}
