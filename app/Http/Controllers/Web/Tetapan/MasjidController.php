<?php

namespace App\Http\Controllers\Web\Tetapan;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tetapan\MasjidBaruRequest;
use App\Http\Requests\Tetapan\MasjidRequest;
use App\Models\AppUser;
use App\Models\Coa;
use App\Models\Masjid;
use App\Services\Security\AuditTrailService;
use App\Services\Tetapan\CoaTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/** Info Masjid (replika paparMasjid.php) — papar profil; edit oleh admin sahaja. */
class MasjidController extends Controller
{
    public function __construct(
        private AuditTrailService $audit,
        private CoaTemplateService $coaTemplat,
    ) {
    }

    public function index(): View
    {
        return view('tetapan.masjid', [
            'masjid'   => Masjid::findOrFail((int) app('current.masjid_id')),
            'kategori' => \App\Http\Requests\Tetapan\MasjidRequest::KATEGORI,
            'bilCoa'   => Coa::count(), // skop masjid aktif — 0 = perlu semai COA
        ]);
    }

    /** Phase B — borang Masjid Baru (admin sahaja). */
    public function baru(): View
    {
        return view('tetapan.masjid-baru', ['kategori' => MasjidRequest::KATEGORI]);
    }

    /**
     * Phase B — cipta masjid baharu + login bendahari pertama + semai Carta Akaun
     * standard (definisi sahaja, dari masjid templat) dalam SATU transaksi atomik.
     * Jika templat COA kosong/salah konfigurasi → SELURUH transaksi digulung balik
     * (tiada masjid yatim tanpa COA). Bendahari sediakan bank/baki awal sendiri
     * kemudian melalui Wizard Setup.
     */
    public function ciptaMasjid(MasjidBaruRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $hasil = DB::transaction(function () use ($data) {
                $masjid = Masjid::create([
                    'nama'     => $data['nama'],
                    'kategori' => $data['kategori'] ?? null,
                    'alamat'   => $data['alamat'] ?? null,
                    'poskod'   => $data['poskod'] ?? null,
                    'bandar'   => $data['bandar'] ?? null,
                    'daerah'   => $data['daerah'] ?? null,
                    'negeri'   => $data['negeri'] ?? null,
                    'telefon'  => $data['telefon'] ?? null,
                    'emel'     => $data['emel'] ?? null,
                ]);

                $user = AppUser::create([
                    'masjid_id'     => $masjid->id,
                    'login'         => $data['login'],
                    'nama_penuh'    => $data['nama_penuh'],
                    'role'          => UserRole::BENDAHARI->value,
                    'password_hash' => Hash::make($data['kata_laluan']),
                    'is_active'     => 1,
                ]);

                // Semai Carta Akaun standard supaya masjid baharu terus boleh berfungsi.
                $bilCoa = $this->coaTemplat->sediaUntukMasjid($masjid->id);

                // Templat COA kosong/salah → masjid TAK boleh rekod transaksi.
                // Lemparkan supaya transaksi gulung balik (jangan commit masjid yatim).
                if ($bilCoa < 1) {
                    throw new \RuntimeException(
                        'Templat COA kosong atau tidak dijumpai (SPPKMS_MASJID_ID='.
                        (int) config('sppkms.masjid_id').'). Masjid tidak dicipta.'
                    );
                }

                // Jejak audit di bawah masjid BAHARU (rekod permulaan jejaknya).
                $this->audit->log('CREATE', 'masjid', null, ['nama' => $masjid->nama], $masjid->id, null, $masjid->id);
                $this->audit->log('CREATE', 'app_user', null,
                    ['login' => $user->login, 'role' => 'bendahari'], $user->id, null, $masjid->id);
                $this->audit->log('CREATE', 'coa', null, ['disemai' => $bilCoa, 'templat' => (int) config('sppkms.masjid_id')], null, null, $masjid->id);

                return ['masjid' => $masjid, 'user' => $user, 'coa' => $bilCoa];
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['nama' => $e->getMessage()]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Perlumbaan login duplikat yang lolos pra-semak unik → mesej mesra (masjid digulung balik).
            return back()->withInput()->withErrors(['login' => 'Nama log masuk ini telah digunakan.']);
        }

        return redirect()->route('tetapan.pengguna')->with('success',
            "Masjid '".$hasil['masjid']->nama."' dicipta — bendahari '".$hasil['user']->login."' + ".
            $hasil['coa']." akaun COA standard disemai. Bendahari boleh log masuk & sediakan bank/baki awal melalui Wizard Setup.");
    }

    /** Phase B follow-up — semai COA standard untuk masjid AKTIF yang masih kosong (admin sahaja). */
    public function sediaCoa(): RedirectResponse
    {
        $masjidId = (int) app('current.masjid_id');
        $bil = $this->coaTemplat->sediaUntukMasjid($masjidId);

        if ($bil > 0) {
            $this->audit->log('CREATE', 'coa', null, ['disemai' => $bil], null, null, $masjidId);

            return back()->with('success', "Berjaya semai {$bil} akaun COA standard untuk masjid ini.");
        }

        // $bil === 0: bezakan 'sudah ada COA' (Coa::count diskop masjid semasa) vs 'templat kosong/salah'.
        if (Coa::count() > 0) {
            return back()->with('success', 'COA sudah wujud untuk masjid ini — tiada perubahan.');
        }

        return back()->with('error',
            'Templat COA kosong atau tidak dijumpai (SPPKMS_MASJID_ID='.
            (int) config('sppkms.masjid_id').'). Tiada akaun disemai — sila semak konfigurasi.');
    }

    public function kemaskini(MasjidRequest $request): RedirectResponse
    {
        $masjid = Masjid::findOrFail((int) app('current.masjid_id'));

        $sebelum = $masjid->only(array_keys($request->validated()));
        $masjid->update($request->validated());
        $this->audit->log('UPDATE', 'masjid', $sebelum, $request->validated(), $masjid->id);
        Masjid::lupakanSemasa(); // butiran baharu terus tampak di semua view

        return redirect()->route('tetapan.masjid')->with('success', 'Info masjid berjaya dikemaskini');
    }
}
