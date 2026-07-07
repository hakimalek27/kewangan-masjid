<?php

namespace App\Services\Ai;

use App\Models\Coa;
use App\Models\CoaLocalMapping;

/**
 * Pemetaan cadangan COA daripada AI → coa.id + rantai fallback.
 * Dikongsi oleh CallAiExtraction (resit) dan ProsesPenyataAi (penyata bank).
 * Semua query terikat masjid_id eksplisit (dipanggil dari queue job).
 */
class CoaCadanganService
{
    /** Petakan cadangan AI → coa.id: kod sama ATAU label tempatan sepadan (case-insensitive). */
    public function petakan(int $masjidId, ?string $cadangan): ?int
    {
        $cadangan = trim((string) $cadangan);
        if ($cadangan === '') {
            return null;
        }

        $coa = Coa::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->where('kod', $cadangan)
            ->value('id');
        if ($coa) {
            return (int) $coa;
        }

        $mapping = CoaLocalMapping::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->whereRaw('LOWER(local_label) = ?', [mb_strtolower($cadangan)])
            ->value('coa_id');

        return $mapping ? (int) $mapping : null;
    }

    /**
     * Fallback wang MASUK tanpa deskripsi bermakna: infaq/sedekah.
     * Rantai: mapping label infaq/sedekah → COA 400-% nama infaq/sedekah/sumbangan
     * → COA hasil postable pertama (400-%).
     */
    public function fallbackKutipan(int $masjidId): ?int
    {
        $mapping = CoaLocalMapping::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->where(fn ($q) => $q->where('local_label', 'like', '%infaq%')
                ->orWhere('local_label', 'like', '%sedekah%'))
            ->value('coa_id');
        if ($mapping) {
            return (int) $mapping;
        }

        $coa = Coa::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->where('is_header', 0)->where('is_active', 1)
            ->where('kod', 'like', '400-%')
            ->where(fn ($q) => $q->where('nama', 'like', '%infaq%')
                ->orWhere('nama', 'like', '%sedekah%')
                ->orWhere('nama', 'like', '%sumbangan%'))
            ->orderBy('kod')
            ->value('id');
        if ($coa) {
            return (int) $coa;
        }

        $pertama = Coa::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->where('is_header', 0)->where('is_active', 1)
            ->where('kod', 'like', '400-%')
            ->orderBy('kod')
            ->value('id');

        return $pertama ? (int) $pertama : null;
    }

    /**
     * Fallback wang KELUAR tanpa padanan: belanja pelbagai/lain-lain.
     * Rantai: COA 600-% nama pelbagai/lain → COA belanja postable pertama (600-%).
     */
    public function fallbackBayaran(int $masjidId): ?int
    {
        $coa = Coa::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->where('is_header', 0)->where('is_active', 1)
            ->where('kod', 'like', '600-%')
            ->where(fn ($q) => $q->where('nama', 'like', '%pelbagai%')
                ->orWhere('nama', 'like', '%lain%'))
            ->orderBy('kod')
            ->value('id');
        if ($coa) {
            return (int) $coa;
        }

        $pertama = Coa::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->where('is_header', 0)->where('is_active', 1)
            ->where('kod', 'like', '600-%')
            ->orderBy('kod')
            ->value('id');

        return $pertama ? (int) $pertama : null;
    }
}
