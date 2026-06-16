<?php

namespace App\Services\Lanjutan;

use App\Models\Coa;
use App\Models\FundAccount;
use App\Models\SecurityEvent;
use App\Services\Integration\AlertService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dana / Tabung khusus (fund_account merujuk COA Liabiliti 300-04xxx).
 * Baki dana = Σ(kredit − debit) jurnal POSTED pada COA dana.
 * Defisit (baki < 0 dan !allow_deficit) → amaran kad merah + SecurityEvent
 * FUND_DEFICIT (HIGH) sekali sehari melalui penjadual harian.
 */
class FundService
{
    public function __construct(private AlertService $alert)
    {
    }

    /** Seed dana lalai untuk COA 300-04010..04050 (firstOrCreate, tiada duplikasi). */
    public function seedLalai(?int $masjidId = null): void
    {
        $masjidId ??= app('current.masjid_id');

        Coa::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->where('kod', 'like', '300-04%')
            ->where('is_header', 0)->where('is_active', 1)
            ->get(['id', 'nama'])
            ->each(fn ($coa) => FundAccount::withoutMasjidScope()->firstOrCreate(
                ['masjid_id' => $masjidId, 'coa_id' => $coa->id],
                ['nama' => $coa->nama, 'allow_deficit' => 0],
            ));
    }

    /** Senarai dana + baki semasa + status defisit. */
    public function senarai(?int $masjidId = null): Collection
    {
        $masjidId ??= app('current.masjid_id');

        $funds = FundAccount::withoutMasjidScope()
            ->where('fund_account.masjid_id', $masjidId)
            ->join('coa as c', 'c.id', '=', 'fund_account.coa_id')
            ->orderBy('c.kod')
            ->get(['fund_account.*', 'c.kod', 'c.nama as coa_nama']);

        $baki = DB::table('journal_entry as je')
            ->join('journal_voucher as jv', 'jv.id', '=', 'je.voucher_id')
            ->where('jv.status', 'POSTED')
            ->where('jv.masjid_id', $masjidId)
            ->whereIn('je.coa_id', $funds->pluck('coa_id'))
            ->groupBy('je.coa_id')
            ->selectRaw('je.coa_id, ROUND(SUM(je.kredit - je.debit),2) as baki')
            ->pluck('baki', 'coa_id');

        return $funds->map(function ($f) use ($baki) {
            $b = round((float) ($baki[$f->coa_id] ?? 0), 2);
            $f->baki = number_format($b, 2, '.', '');
            $f->defisit = $b < 0 && !$f->allow_deficit;

            return $f;
        });
    }

    /** Transaksi terkini sesuatu dana (jurnal POSTED pada COA dana). */
    public function transaksi(int $coaId, int $had = 50, ?int $masjidId = null): Collection
    {
        $masjidId ??= app('current.masjid_id');

        return DB::table('journal_entry as je')
            ->join('journal_voucher as jv', 'jv.id', '=', 'je.voucher_id')
            ->where('jv.status', 'POSTED')
            ->where('jv.masjid_id', $masjidId)
            ->where('je.coa_id', $coaId)
            ->orderByDesc('jv.tarikh')->orderByDesc('jv.id')
            ->limit($had)
            ->get(['jv.tarikh', 'jv.voucher_ref', 'jv.deskripsi', 'jv.source_type', 'je.debit', 'je.kredit']);
    }

    /**
     * Semakan defisit harian (penjadual) — SecurityEvent FUND_DEFICIT (HIGH)
     * sekali sehari per masjid + amaran Telegram best-effort.
     */
    public function semakDefisitSemua(): int
    {
        $bilangan = 0;

        $masjidIds = FundAccount::withoutMasjidScope()
            ->distinct()->pluck('masjid_id');

        foreach ($masjidIds as $masjidId) {
            // Boleh dimatikan per masjid melalui /tetapan/kawalan
            if (\App\Support\Setting::get('fund_deficit_alert', 'on', (int) $masjidId) === 'off') {
                continue;
            }

            $defisit = $this->senarai((int) $masjidId)->where('defisit', true);
            if ($defisit->isEmpty()) {
                continue;
            }

            // Sekali sehari sahaja
            $sudahHariIni = SecurityEvent::withoutMasjidScope()
                ->where('masjid_id', $masjidId)
                ->where('jenis', 'FUND_DEFICIT')
                ->whereDate('created_at', now()->toDateString())
                ->exists();
            if ($sudahHariIni) {
                continue;
            }

            $butir = $defisit->map(fn ($f) => "{$f->kod} ".($f->nama ?: $f->coa_nama).": RM{$f->baki}")->implode('; ');

            $event = SecurityEvent::withoutMasjidScope()->create([
                'masjid_id' => $masjidId,
                'jenis'     => 'FUND_DEFICIT',
                'detail'    => mb_substr('Dana/tabung defisit ('.$defisit->count().'): '.$butir, 0, 500),
                'severity'  => 'HIGH',
            ]);

            $this->alert->securityEvent($event); // best-effort Telegram
            $bilangan++;
        }

        return $bilangan;
    }
}
