<?php

namespace App\Services\Tetapan;

use Illuminate\Support\Facades\DB;

/**
 * Semai (bootstrap) Carta Akaun standard untuk masjid baharu. Menyalin DEFINISI
 * akaun sahaja (kod/nama/jenis/baki normal/hierarki) — TIADA baki/jurnal — daripada
 * masjid templat (rujukan) ke masjid sasaran. Idempoten: hanya jika sasaran belum
 * ada sebarang COA. Guna DB::table (bukan Eloquent) → bebas skop BelongsToMasjid.
 */
class CoaTemplateService
{
    public function sediaUntukMasjid(int $targetMasjidId, ?int $templateMasjidId = null): int
    {
        $templateMasjidId ??= (int) config('spkm.masjid_id');

        if ($targetMasjidId === $templateMasjidId) {
            return 0;
        }
        if (DB::table('coa')->where('masjid_id', $targetMasjidId)->exists()) {
            return 0; // sudah ada COA — jangan gandakan
        }

        $rows = DB::table('coa')
            ->where('masjid_id', $templateMasjidId)
            ->orderBy('id')
            ->get()
            ->map(function ($c) use ($targetMasjidId) {
                $arr = (array) $c;
                unset($arr['id']);                 // id auto-increment baharu
                $arr['masjid_id'] = $targetMasjidId; // skop ke masjid sasaran
                return $arr;
            })
            ->all();

        if ($rows) {
            DB::table('coa')->insert($rows);
        }

        return count($rows);
    }
}
