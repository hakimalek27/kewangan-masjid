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
     * Cadangan COA PINTAR berasaskan KATA KUNCI dalam deskripsi (derma/sedekah/
     * infaq/jumaat/ramadan/wakaf/khairat/zakat, dll). Utamakan mapping tempatan
     * tenant (label ada kata kunci), kemudian nama COA keluarga betul. Digunakan
     * untuk baris parse deterministik (tiada cadangan AI) & sebagai lapisan tengah.
     */
    public function cadangDariDeskripsi(int $masjidId, ?string $deskripsi, bool $masuk): ?int
    {
        $teks = mb_strtolower(trim((string) $deskripsi));
        if ($teks === '') {
            return null;
        }

        // kunci kanonik → sinonim yang dicari dalam deskripsi.
        $peta = $masuk ? [
            'jumaat' => ['jumaat', "juma'at", 'jumat'],
            'ramadan' => ['ramadan', 'ramadhan', 'tarawih', 'terawih', 'moreh', 'iftar'],
            'wakaf' => ['wakaf', 'waqaf'],
            'khairat' => ['khairat', 'kematian', 'jenazah'],
            'zakat' => ['zakat', 'fitrah'],
            'kariah' => ['kariah', 'ahli kariah'],
            'infaq' => ['infaq', 'infak', 'derma', 'sedekah', 'sumbangan', 'donation', 'duitnow', 'qr'],
        ] : [
            'elektrik' => ['elektrik', 'tnb'],
            'air' => ['air ', 'syabas', 'lap '],
            'gaji' => ['gaji', 'elaun', 'imam', 'bilal', 'siak', 'khadam'],
        ];

        $julat = $masuk ? ['400-%', '450-%'] : ['600-%', '650-%'];

        foreach ($peta as $kunci => $sinonim) {
            $jumpa = false;
            foreach ($sinonim as $s) {
                if (str_contains($teks, $s)) {
                    $jumpa = true;
                    break;
                }
            }
            if (!$jumpa) {
                continue;
            }

            // 1) mapping tempatan tenant yang labelnya mengandungi kata kunci.
            $coa = CoaLocalMapping::withoutMasjidScope()
                ->where('masjid_id', $masjidId)
                ->where('local_label', 'like', '%'.$kunci.'%')
                ->value('coa_id');
            if ($coa) {
                return (int) $coa;
            }

            // 2) COA keluarga betul yang namanya mengandungi kata kunci.
            $coa = Coa::withoutMasjidScope()
                ->where('masjid_id', $masjidId)
                ->where('is_header', 0)->where('is_active', 1)
                ->where(fn ($q) => $q->where('kod', 'like', $julat[0])->orWhere('kod', 'like', $julat[1]))
                ->where('nama', 'like', '%'.$kunci.'%')
                ->orderBy('kod')
                ->value('id');
            if ($coa) {
                return (int) $coa;
            }
        }

        return null;
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
