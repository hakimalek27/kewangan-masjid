<?php

namespace App\Services\Lanjutan;

use App\Models\ErrorLog;
use App\Models\Masjid;
use App\Services\Integration\AlertService;
use App\Services\Laporan\StatementService;
use App\Support\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Notifikasi "Baki Rendah" bank — ambang melalui Setting 'baki_rendah_ambang'
 * (RM; '0' = mati, ditetapkan di /tetapan/kawalan). Baki dikira dari jurnal
 * POSTED (StatementService::bakiTunaiSehingga), akaun bank sahaja (250-050x0).
 */
class BakiRendahService
{
    /** Kod COA akaun bank yang dipantau (bukan PWR/tunai tangan). */
    private const KOD_BANK = ['250-05010', '250-05020', '250-05030'];

    public function __construct(
        private StatementService $statement,
        private AlertService $alert,
    ) {
    }

    /**
     * Senarai akaun bank yang baki < ambang untuk masjid ini.
     * @return array<int, object{kod:string, nama:string, baki:string}>
     */
    public function senaraiRendah(?int $masjidId = null): array
    {
        $masjidId ??= app('current.masjid_id');

        $ambang = (float) Setting::get('baki_rendah_ambang', '0', $masjidId);
        if ($ambang <= 0) {
            return [];
        }

        $periodSemasa = now()->format('Y-m');
        $baki = $this->statement->bakiTunaiSehingga($periodSemasa, $masjidId);

        return collect($baki)
            ->filter(fn ($b) => in_array($b->kod, self::KOD_BANK, true) && (float) $b->baki < $ambang)
            ->values()
            ->all();
    }

    public function ambang(?int $masjidId = null): float
    {
        return (float) Setting::get('baki_rendah_ambang', '0', $masjidId);
    }

    /**
     * Semakan berjadual harian SEMUA masjid: ErrorLog INFO (sekali sehari per
     * masjid) + amaran Telegram best-effort. Pulangkan bilangan masjid diberi amaran.
     */
    public function semakSemua(): int
    {
        $bil = 0;

        foreach (Masjid::pluck('id') as $masjidId) {
            $masjidId = (int) $masjidId;
            $rendah = $this->senaraiRendah($masjidId);
            if (empty($rendah)) {
                continue;
            }

            // Sekali sehari sahaja (corak FUND_DEFICIT)
            $sudahHariIni = ErrorLog::withoutMasjidScope()
                ->where('masjid_id', $masjidId)
                ->where('level', 'INFO')
                ->where('message', 'like', 'Baki bank rendah%')
                ->whereDate('created_at', now()->toDateString())
                ->exists();
            if ($sudahHariIni) {
                continue;
            }

            $ambang = number_format($this->ambang($masjidId), 2);
            $baris = collect($rendah)
                ->map(fn ($b) => sprintf('- %s %s: RM%s', $b->kod, $b->nama, number_format((float) $b->baki, 2)))
                ->implode("\n");

            ErrorLog::withoutMasjidScope()->create([
                'masjid_id' => $masjidId,
                'level'     => 'INFO',
                'message'   => mb_substr("Baki bank rendah (< RM{$ambang}): ".count($rendah)." akaun. {$baris}", 0, 500),
            ]);

            $this->alert->hantar(
                $masjidId,
                "⚠️ BAKI BANK RENDAH (bawah RM{$ambang})\n{$baris}\nSila semak aliran tunai masjid."
            );

            $bil++;
        }

        return $bil;
    }
}
