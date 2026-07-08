<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;

/**
 * Pemisahan PENYEDIA (provider) vs TENANT untuk superadmin (admin).
 *
 * Superadmin bermula dalam "MOD PENYEDIA" (tiada masjid dipilih dalam sesi):
 * hanya laluan peringkat-SISTEM dibenarkan (Konsol Sistem, /admin/*, urus pengguna,
 * onboarding, tetapan AI/API, akaun sendiri). Sebarang laluan KEWANGAN/tetapan tenant
 * dialihkan ke Konsol Sistem — admin MESTI klik "Masuk" sesebuah masjid dahulu.
 *
 * Selepas "Masuk" (masjid.tukar menetapkan selected_masjid_id) admin berada dalam
 * "MOD DALAM-TENANT": boleh membaca kewangan masjid itu (tulis kekal 403 —
 * lihat RoleMiddleware). "Kembali ke Konsol" (masjid.keluar) memadam pilihan itu.
 *
 * Peranan lain tidak terkesan (mereka sentiasa terikat pada masjid sendiri).
 */
class RestrictAdminProvider
{
    /** Awalan nama route peringkat-penyedia yang dibenarkan dalam mod penyedia. */
    private const AWALAN_DIBENARKAN = [
        'sistem.',            // Konsol Sistem
        'admin.',             // Pentadbiran (pemantauan/audit/backup/dualwrite/semak penyata/…)
        'tetapan.pengguna',   // urus pengguna (penyedia)
        'tetapan.ai',         // tetapan AI & Telegram
        'tetapan.api',        // API awam
        'tetapan.masjid.baru', // onboarding masjid baharu (+ .simpan)
        'tetapan.katalaluan', // tukar kata laluan sendiri
    ];

    /** Nama route tepat tambahan yang dibenarkan (akaun sendiri / kawalan sesi). */
    private const TEPAT_DIBENARKAN = [
        'masjid.tukar', 'masjid.keluar', 'logout', 'bahasa',
        'tetapan.masjid.sediacoa',
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Hanya admin dalam MOD PENYEDIA (belum "Masuk" mana-mana masjid) yang dihadkan.
        if ($user?->role === UserRole::ADMIN
            && ! $request->session()->has('selected_masjid_id')
            && ! $this->laluanPenyedia($request->route()?->getName())) {
            // Laluan kewangan/tetapan tenant → mesti Masuk masjid dahulu.
            return redirect()->route('sistem.console');
        }

        return $next($request);
    }

    private function laluanPenyedia(?string $route): bool
    {
        if ($route === null) {
            return true; // laluan tanpa nama — jangan halang (tiada dalam kumpulan tenant)
        }

        foreach (self::AWALAN_DIBENARKAN as $awalan) {
            if (str_starts_with($route, $awalan)) {
                return true;
            }
        }

        return in_array($route, self::TEPAT_DIBENARKAN, true);
    }
}
