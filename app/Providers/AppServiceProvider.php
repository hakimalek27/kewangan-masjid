<?php

namespace App\Providers;

use App\Models\JournalVoucher;
use App\Models\Kutipan;
use App\Models\Masjid;
use App\Models\Pembayaran;
use App\Models\SecurityEvent;
use App\Observers\JournalVoucherObserver;
use App\Observers\KutipanObserver;
use App\Observers\PembayaranObserver;
use App\Observers\SecurityEventObserver;
use App\Services\Integration\Contracts\GdriveClientInterface;
use App\Services\Integration\GoogleDriveClient;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         | Klien Google Drive (Fasa 7) — lapisan boleh-mock. Ujian mengikat
         | instance palsu: app()->instance(GdriveClientInterface::class, $fake).
         | Laluan service account boleh dihantar sebagai parameter contextual:
         | app(GdriveClientInterface::class, ['saJsonPath' => $laluan]).
         */
        $this->app->bind(
            GdriveClientInterface::class,
            fn ($app, array $params = []) => new GoogleDriveClient($params['saJsonPath'] ?? null)
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         | Observer Fasa 7 — backup per-transaksi & amaran keselamatan.
         | Semua observer $afterCommit = true: berjalan SELEPAS commit sahaja,
         | TIDAK menyentuh/melengahkan logik kewangan dalam service transaksi.
         */
        Kutipan::observe(KutipanObserver::class);
        Pembayaran::observe(PembayaranObserver::class);
        JournalVoucher::observe(JournalVoucherObserver::class);
        SecurityEvent::observe(SecurityEventObserver::class);

        /*
         | Kongsi butiran masjid semasa ke SEMUA view (logo + nama + alamat +
         | telefon) — supaya tetapan info masjid keluar di dashboard, sidebar,
         | resit, baucer & kepala penyata TANPA placeholder. Masjid::semasa()
         | dimemo per-permintaan & null-safe.
         */
        View::composer('*', fn ($view) => $view->with('masjidSemasa', Masjid::semasa()));

        /*
         | Senarai masjid yang boleh dicapai pengguna log masuk — untuk penukar
         | masjid di bar atas (hanya dipaparkan bila > 1 masjid). Query hanya
         | dilakukan untuk pengguna multi-masjid (admin/pemerhati ditugaskan).
         */
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $senarai = collect();
            $modProvider = false;
            if ($user) {
                $ids = $user->accessibleMasjidIds();
                if (count($ids) > 1) {
                    $senarai = Masjid::whereIn('id', $ids)->orderBy('nama')->get(['id', 'nama']);
                }
                // Superadmin dalam MOD PENYEDIA (belum "Masuk" masjid) → papar jenama
                // SISTEM (bukan nama tenant): sistem ini milik penyedia, bukan Al-Muttaqin.
                $modProvider = $user->role === \App\Enums\UserRole::ADMIN
                    && ! session()->has('selected_masjid_id');
            }
            $view->with('masjidSenarai', $senarai);
            $view->with('modProvider', $modProvider);
        });
    }
}
