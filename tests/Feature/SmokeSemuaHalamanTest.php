<?php

namespace Tests\Feature;

use App\Models\AppUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Smoke test SEMUA route GET bernama (replika "56 halaman, semua 200").
 * Setiap halaman mesti 200 (atau redirect yang sah) sebagai admin.
 */
class SmokeSemuaHalamanTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    public function test_semua_halaman_get_berfungsi(): void
    {
        $this->aktifkanKonteksMasjid();

        $admin = AppUser::create([
            'masjid_id' => config('spkm.masjid_id'), 'login' => 'smoke_'.uniqid(),
            'nama_penuh' => 'Smoke Admin', 'role' => 'admin',
            'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);

        // "Masuk" masjid supaya admin dalam mod dalam-tenant → halaman kewangan
        // dirender penuh (200), bukan dialih ke Konsol (mod penyedia).
        $this->adminMasuk($admin);

        // Route GET dengan parameter wajib — beri nilai sebenar
        $kutipanId = \App\Models\Kutipan::withoutMasjidScope()->where('status', 'ACTIVE')->value('id');
        $bayaranId = \App\Models\Pembayaran::withoutMasjidScope()->where('status', 'ACTIVE')->value('id');
        $sewaId    = \App\Models\Sewaan::withoutMasjidScope()->value('id');
        $param = [
            'kutipan.view'  => ['kutipan' => $kutipanId],
            'belanja.view'  => ['pembayaran' => $bayaranId],
            'sewa.edit'     => ['sewaan' => $sewaId],
        ];

        // Dikecualikan: perlukan konteks khusus / bukan halaman
        $kecuali = [
            'login', 'login.attempt', 'logout', 'storage.local',
            'draf.lihat', 'draf.imej',           // perlukan draf wujud
            'tetapan.pengguna.edit', 'tetapan.mapping.edit', 'tetapan.penyata.sig.edit',
            'tetapan.api.klien', 'sewa.edit',
        ];

        $gagal = [];
        foreach (Route::getRoutes() as $route) {
            $nama = $route->getName();
            if (!$nama || !in_array('GET', $route->methods()) || in_array($nama, $kecuali)) {
                continue;
            }
            if (str_starts_with($route->uri(), 'v1/') || str_starts_with($route->uri(), 'webhook') || $route->uri() === 'up') {
                continue;
            }

            $p = $param[$nama] ?? [];
            // Langkau route berparameter yang tiada nilai
            if (preg_match('/\{/', $route->uri()) && !$p) {
                continue;
            }
            if ($p && in_array(null, $p, true)) {
                continue;
            }

            $resp = $this->actingAs($admin)->get(route($nama, $p));
            if (!in_array($resp->status(), [200, 302])) {
                $gagal[] = "$nama ({$route->uri()}) → {$resp->status()}";
            }
        }

        $this->assertSame([], $gagal, "Halaman gagal:\n".implode("\n", $gagal));
    }
}
