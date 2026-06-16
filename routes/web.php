<?php

use App\Http\Controllers\Web\Admin\AuditController;
use App\Http\Controllers\Web\Admin\BackupController;
use App\Http\Controllers\Web\Admin\DualWriteController;
use App\Http\Controllers\Web\Admin\KeselamatanController;
use App\Http\Controllers\Web\Admin\PemantauanController;
use App\Http\Controllers\Web\Admin\RalatController;
use App\Http\Controllers\Web\Aset\AsetController;
use App\Http\Controllers\Web\Aset\FdController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Belanja\BelanjaController;
use App\Http\Controllers\Web\Belanja\JurnalController;
use App\Http\Controllers\Web\Belanja\RekupmenController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\MasjidSwitchController;
use App\Http\Controllers\Web\Kutipan\DividenController;
use App\Http\Controllers\Web\Kutipan\KutipanController;
use App\Http\Controllers\Web\Kutipan\TabungController;
use App\Http\Controllers\Web\Laporan\PerakaunanController;
use App\Http\Controllers\Web\Penyata\PenyataController;
use App\Http\Controllers\Web\Register\BukuCekController;
use App\Http\Controllers\Web\Register\CekBatalController;
use App\Http\Controllers\Web\Register\PetiBesiController;
use App\Http\Controllers\Web\Register\SewaanController;
use App\Http\Controllers\Web\Statistik\StatistikController;
use App\Http\Controllers\Web\Ai\DrafController;
use App\Http\Controllers\Web\Ai\TetapanAiController;
use App\Http\Controllers\Web\Lanjutan\BelanjawanController;
use App\Http\Controllers\Web\Lanjutan\CarianController;
use App\Http\Controllers\Web\Lanjutan\DanaController;
use App\Http\Controllers\Web\Lanjutan\DrafBulkController;
use App\Http\Controllers\Web\Lanjutan\KawalanController;
use App\Http\Controllers\Web\Lanjutan\KelulusanController;
use App\Http\Controllers\Web\Lanjutan\RekonsiliasiController;
use App\Http\Controllers\Web\Lanjutan\SusutNilaiController;
use App\Http\Controllers\Web\Lanjutan\TutupTahunController;
use App\Http\Controllers\Web\Tetapan\BakiTerkiniController;
use App\Http\Controllers\Web\Tetapan\BankController;
use App\Http\Controllers\Web\Tetapan\CoaSemakController;
use App\Http\Controllers\Web\Tetapan\KataLaluanController;
use App\Http\Controllers\Web\Tetapan\MappingController;
use App\Http\Controllers\Web\Tetapan\MasjidController;
use App\Http\Controllers\Web\Tetapan\OpeningBalanceController;
use App\Http\Controllers\Web\Tetapan\PenggunaController;
use App\Http\Controllers\Web\Tetapan\ApiController as TetapanApiController;
use App\Http\Controllers\Web\Tetapan\PenyataSettingController;
use App\Http\Controllers\Web\Tetapan\ResitBaucerController;
use Illuminate\Support\Facades\Route;

// ---------- Auth ----------
Route::middleware('guest')->group(function () {
    Route::get('/', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// ---------- Aplikasi (perlu log masuk) ----------
Route::middleware(['auth', 'masjid', 'viewer.guard'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Konsol Sistem — pendaratan admin (pentadbir platform), pandangan merentas semua masjid.
    Route::middleware('role:admin')->get('/sistem', [\App\Http\Controllers\Web\SistemController::class, 'index'])->name('sistem.console');

    // Penukar masjid aktif (admin: semua; pemerhati: masjid ditugaskan) — tulis sesi sahaja
    Route::post('/masjid/tukar', [MasjidSwitchController::class, 'tukar'])->name('masjid.tukar');

    // Fasa 5 — Kotak Draf AI (Telegram → AI → draf → pengesahan bendahari)
    Route::get('/draf', [DrafController::class, 'index'])->name('draf.index');
    Route::get('/draf/{draf}', [DrafController::class, 'lihat'])->whereNumber('draf')->name('draf.lihat');
    Route::get('/draf/{draf}/imej', [DrafController::class, 'imej'])->whereNumber('draf')->name('draf.imej');
    Route::middleware('role:bendahari')->group(function () {
        Route::post('/draf/{draf}/sahkan', [DrafController::class, 'sahkan'])->whereNumber('draf')->name('draf.sahkan');
        Route::post('/draf/{draf}/tolak', [DrafController::class, 'tolak'])->whereNumber('draf')->name('draf.tolak');
    });

    // Fasa 5 — Tetapan AI & Telegram (admin sahaja)
    Route::middleware('role:admin')->group(function () {
        Route::get('/tetapan/ai', [TetapanAiController::class, 'index'])->name('tetapan.ai');
        Route::post('/tetapan/ai/provider', [TetapanAiController::class, 'providerSimpan'])->name('tetapan.ai.provider');
        Route::post('/tetapan/ai/provider/{provider}/kemaskini', [TetapanAiController::class, 'providerKemaskini'])->whereNumber('provider')->name('tetapan.ai.provider.kemaskini');
        Route::post('/tetapan/ai/provider/{provider}/default', [TetapanAiController::class, 'providerDefault'])->whereNumber('provider')->name('tetapan.ai.provider.default');
        Route::post('/tetapan/ai/provider/{provider}/uji', [TetapanAiController::class, 'providerUji'])->whereNumber('provider')->name('tetapan.ai.provider.uji');
        Route::post('/tetapan/ai/telegram', [TetapanAiController::class, 'telegramSimpan'])->name('tetapan.ai.telegram');
    });

    // Fasa 4 — Bank & Tetapan
    Route::get('/bank', [BankController::class, 'index'])->name('bank.index');
    Route::get('/bank/baki-awal', [OpeningBalanceController::class, 'index'])->name('bank.opening');
    Route::get('/bank/baki-terkini', [BakiTerkiniController::class, 'index'])->name('bank.baki');

    // Fasa 2 — Register Buku Cek / Cek Batal (tanpa GL)
    Route::get('/cek/daftar', [BukuCekController::class, 'daftar'])->name('cek.daftar');
    Route::get('/cek/senarai', [BukuCekController::class, 'senarai'])->name('cek.senarai');
    Route::get('/cek-batal/daftar', [CekBatalController::class, 'daftar'])->name('cekbatal.daftar');
    Route::get('/cek-batal/senarai', [CekBatalController::class, 'senarai'])->name('cekbatal.senarai');

    // Fasa 2 — Kutipan
    Route::get('/kutipan/baru', [KutipanController::class, 'baru'])->name('kutipan.baru');
    Route::get('/kutipan/tabung', [TabungController::class, 'borang'])->name('kutipan.tabung');
    Route::get('/kutipan/dividen', [DividenController::class, 'borang'])->name('kutipan.dividen');
    Route::get('/kutipan/senarai', [KutipanController::class, 'senarai'])->name('kutipan.senarai');
    Route::get('/kutipan/jumaat', [KutipanController::class, 'jumaat'])->name('kutipan.jumaat');
    Route::get('/kutipan/harian', [KutipanController::class, 'harian'])->name('kutipan.harian');
    Route::get('/kutipan/{kutipan}/cetak', [KutipanController::class, 'cetak'])->whereNumber('kutipan')->name('kutipan.cetak');
    Route::get('/kutipan/{kutipan}', [KutipanController::class, 'view'])->whereNumber('kutipan')->name('kutipan.view');

    // Fasa 2 — Perbelanjaan
    Route::get('/belanja', [BelanjaController::class, 'menu'])->name('belanja.menu');
    Route::get('/belanja/baru', [BelanjaController::class, 'baru'])->name('belanja.baru');
    Route::get('/belanja/aset', [BelanjaController::class, 'aset'])->name('belanja.aset');
    Route::get('/belanja/jurnal', [JurnalController::class, 'borang'])->name('belanja.jurnal');
    Route::get('/belanja/rekupmen', [RekupmenController::class, 'borang'])->name('belanja.rekupmen');
    Route::get('/belanja/senarai', [BelanjaController::class, 'senarai'])->name('belanja.senarai');
    // Lampiran perbelanjaan disimpan PRIVATE — dihidang berpagar-auth + skop masjid
    Route::get('/belanja/lampiran/{lampiran}', [BelanjaController::class, 'lampiran'])->whereNumber('lampiran')->name('belanja.lampiran');
    Route::get('/belanja/{pembayaran}/cetak', [BelanjaController::class, 'cetak'])->whereNumber('pembayaran')->name('belanja.cetak');
    Route::get('/belanja/{pembayaran}', [BelanjaController::class, 'view'])->whereNumber('pembayaran')->name('belanja.view');

    // Fasa 2/3 — PWR
    Route::get('/pwr/baki', [PenyataController::class, 'pwrBaki'])->name('pwr.baki');
    Route::get('/pwr/bayar', [BelanjaController::class, 'pwrBayar'])->name('pwr.bayar');
    Route::get('/pwr/buku', [BelanjaController::class, 'pwrBuku'])->name('pwr.buku');
    Route::get('/pwr/penyata', [PenyataController::class, 'pwrPenyata'])->name('pwr.penyata');

    // Fasa 2 — Aset / FD / Sewa / Peti Besi
    Route::get('/aset/daftar-lama', [AsetController::class, 'daftarLama'])->name('aset.daftarlama');
    Route::get('/aset/senarai', [AsetController::class, 'senarai'])->name('aset.senarai');
    Route::get('/aset/{aset}/lihat', [AsetController::class, 'lihat'])->whereNumber('aset')->name('aset.lihat');
    Route::get('/fd/daftar-lama', [FdController::class, 'daftarLama'])->name('fd.daftarlama');
    Route::get('/fd/baru', [FdController::class, 'baru'])->name('fd.baru');
    Route::get('/fd/senarai-lama', [FdController::class, 'senaraiLama'])->name('fd.senarailama');
    Route::get('/fd/senarai', [FdController::class, 'senarai'])->name('fd.senarai');
    Route::get('/fd/{fd}/edit', [FdController::class, 'edit'])->whereNumber('fd')->name('fd.edit');
    Route::get('/peti-besi/daftar', [PetiBesiController::class, 'daftar'])->name('petibesi.daftar');
    Route::get('/peti-besi/senarai', [PetiBesiController::class, 'senarai'])->name('petibesi.senarai');
    Route::get('/sewa/daftar', [SewaanController::class, 'daftar'])->name('sewa.daftar');
    Route::get('/sewa/senarai', [SewaanController::class, 'senarai'])->name('sewa.senarai');
    Route::get('/sewa/{sewaan}/edit', [SewaanController::class, 'edit'])->whereNumber('sewaan')->name('sewa.edit');

    /*
     | Route TULIS kewangan (POST) — BENDAHARI sahaja (maker). Admin = sistem,
     | TIDAK merekod kewangan. Semua mutasi melalui service.
     */
    Route::middleware('role:bendahari')->group(function () {
        // Kutipan
        Route::post('/kutipan/baru', [KutipanController::class, 'simpan'])->name('kutipan.simpan');
        Route::post('/kutipan/tabung', [TabungController::class, 'simpan'])->name('kutipan.tabung.simpan');
        Route::post('/kutipan/dividen', [DividenController::class, 'simpan'])->name('kutipan.dividen.simpan');
        Route::post('/kutipan/{kutipan}/padam', [KutipanController::class, 'padam'])->whereNumber('kutipan')->name('kutipan.padam');

        // Perbelanjaan
        Route::post('/belanja/baru', [BelanjaController::class, 'simpan'])->name('belanja.simpan');
        Route::post('/belanja/aset', [BelanjaController::class, 'simpanAset'])->name('belanja.aset.simpan');
        Route::post('/belanja/jurnal', [JurnalController::class, 'simpan'])->name('belanja.jurnal.simpan');
        Route::post('/belanja/rekupmen', [RekupmenController::class, 'simpan'])->name('belanja.rekupmen.simpan');
        Route::post('/belanja/{pembayaran}/padam', [BelanjaController::class, 'padam'])->whereNumber('pembayaran')->name('belanja.padam');

        // Aset & FD
        Route::post('/aset/daftar-lama', [AsetController::class, 'simpan'])->name('aset.daftarlama.simpan');
        Route::post('/fd/baru', [FdController::class, 'simpan'])->name('fd.simpan');
        Route::post('/fd/daftar-lama', [FdController::class, 'simpanOpening'])->name('fd.daftarlama.simpan');
        Route::post('/fd/{fd}/kemaskini', [FdController::class, 'kemaskini'])->whereNumber('fd')->name('fd.kemaskini');
        Route::post('/fd/{fd}/matang', [FdController::class, 'matang'])->whereNumber('fd')->name('fd.matang');
        Route::post('/fd/{fd}/renew', [FdController::class, 'renew'])->whereNumber('fd')->name('fd.renew');
        Route::post('/fd/{fd}/padam', [FdController::class, 'padam'])->whereNumber('fd')->name('fd.padam');

        // Register Cek (kewangan)
        Route::post('/cek/daftar', [BukuCekController::class, 'simpan'])->name('cek.simpan');
        Route::post('/cek-batal/daftar', [CekBatalController::class, 'simpan'])->name('cekbatal.simpan');
    });

    // Daftar BUKAN-kewangan (sewa, peti besi) — bendahari & SETIAUSAHA
    Route::middleware('role:bendahari,setiausaha')->group(function () {
        Route::post('/peti-besi/daftar', [PetiBesiController::class, 'simpan'])->name('petibesi.simpan');
        Route::post('/sewa/daftar', [SewaanController::class, 'simpan'])->name('sewa.simpan');
        Route::post('/sewa/{sewaan}/kemaskini', [SewaanController::class, 'kemaskini'])->whereNumber('sewaan')->name('sewa.kemaskini');
        Route::post('/sewa/{sewaan}/padam', [SewaanController::class, 'padam'])->whereNumber('sewaan')->name('sewa.padam');
    });

    // Fasa 3 — Penyata
    Route::get('/penyata/setting', [PenyataSettingController::class, 'index'])->name('penyata.setting'); // Fasa 4
    Route::get('/penyata/bulanan', [PenyataController::class, 'bulanan'])->name('penyata.bulanan');
    Route::get('/penyata/bank', [PenyataController::class, 'bank'])->name('penyata.bank');
    Route::get('/penyata/tahunan', [PenyataController::class, 'tahunan'])->name('penyata.tahunan');

    // Fasa 3 — Statistik
    Route::get('/statistik/kutipan', [StatistikController::class, 'kutipan'])->name('statistik.kutipan');
    Route::get('/statistik/kutipan-coa', [StatistikController::class, 'kutipanCoa'])->name('statistik.kutipan_coa');
    Route::get('/statistik/belanja', [StatistikController::class, 'belanja'])->name('statistik.belanja');
    Route::get('/statistik/belanja-coa', [StatistikController::class, 'belanjaCoa'])->name('statistik.belanja_coa');
    Route::get('/statistik/jumaat', [StatistikController::class, 'jumaat'])->name('statistik.jumaat');

    // Fasa 3 — Penyata Perakaunan
    Route::get('/akaun/coa', [PerakaunanController::class, 'coa'])->name('akaun.coa');
    Route::get('/akaun/jurnal', [PerakaunanController::class, 'jurnal'])->name('akaun.jurnal');
    Route::get('/akaun/laporan-jurnal', [PerakaunanController::class, 'laporanJurnal'])->name('akaun.laporanjurnal');
    Route::get('/akaun/lejer', [PerakaunanController::class, 'lejer'])->name('akaun.lejer');
    Route::get('/akaun/lejer-akaun', [PerakaunanController::class, 'lejerAkaun'])->name('akaun.lejerakaun');
    Route::get('/akaun/imbangan-duga', [PerakaunanController::class, 'imbangan'])->name('akaun.imbangan');
    Route::get('/akaun/untung-rugi', [PerakaunanController::class, 'untungRugi'])->name('akaun.untungrugi');
    Route::get('/akaun/kunci-kira-kira', [PerakaunanController::class, 'kunci'])->name('akaun.kunci');
    Route::get('/akaun/program', [PerakaunanController::class, 'program'])->name('akaun.program');

    // Batal jurnal (VOID) — bendahari sahaja
    Route::middleware('role:bendahari')->group(function () {
        Route::post('/akaun/jurnal/{voucher}/batal', [PerakaunanController::class, 'jurnalBatal'])
            ->whereNumber('voucher')->name('akaun.jurnal.batal');
    });

    // Fasa 4 — Tetapan
    Route::get('/tetapan/wizard', [\App\Http\Controllers\Web\Tetapan\SetupWizardController::class, 'index'])->name('tetapan.wizard');
    Route::get('/tetapan/resit-baucer', [ResitBaucerController::class, 'index'])->name('tetapan.resit');
    Route::get('/tetapan/mapping', [MappingController::class, 'index'])->name('tetapan.mapping');
    Route::get('/tetapan/semak-kod', [CoaSemakController::class, 'index'])->name('tetapan.semak');
    Route::get('/tetapan/masjid', [MasjidController::class, 'index'])->name('tetapan.masjid');

    // Tukar Kata Laluan — self-service akaun SENDIRI (semua peranan log masuk;
    // hanya menyentuh $request->user(), bukan mutasi kewangan dikongsi)
    Route::get('/tetapan/kata-laluan', [KataLaluanController::class, 'borang'])->name('tetapan.katalaluan');
    Route::post('/tetapan/kata-laluan', [KataLaluanController::class, 'kemaskini'])->name('tetapan.katalaluan.kemaskini');

    /*
     | Fasa 4 — Tetapan kewangan masjid: TULIS (POST) & halaman edit — BENDAHARI sahaja.
     | Info Masjid (bendahari+setiausaha) & Pengurusan Pengguna (admin+bendahari) di bawah.
     */
    Route::middleware('role:bendahari')->group(function () {
        // Setting Bank — "Padam" = nyahaktif (rekod mungkin dirujuk transaksi)
        Route::get('/bank/{bank}/edit', [BankController::class, 'edit'])->whereNumber('bank')->name('bank.edit');
        Route::post('/bank', [BankController::class, 'simpan'])->name('bank.simpan');
        Route::post('/bank/{bank}/kemaskini', [BankController::class, 'kemaskini'])->whereNumber('bank')->name('bank.kemaskini');
        Route::post('/bank/{bank}/padam', [BankController::class, 'padam'])->whereNumber('bank')->name('bank.padam');

        // Set Baki Awal — simpan / muktamad & kunci / reset & edit semula
        Route::post('/bank/baki-awal/simpan', [OpeningBalanceController::class, 'simpan'])->name('bank.opening.simpan');
        Route::post('/bank/baki-awal/kunci', [OpeningBalanceController::class, 'kunci'])->name('bank.opening.kunci');
        Route::post('/bank/baki-awal/reset', [OpeningBalanceController::class, 'reset'])->name('bank.opening.reset');

        // Set Resit/Baucer — siri penomboran & logo masjid
        Route::post('/tetapan/resit-baucer/siri', [ResitBaucerController::class, 'setSiri'])->name('tetapan.resit.siri');
        Route::post('/tetapan/resit-baucer/logo', [ResitBaucerController::class, 'logo'])->name('tetapan.resit.logo');

        // Set Kod (mapping) — padam sebenar dibenarkan (bukan rekod kewangan)
        Route::get('/tetapan/mapping/{mapping}/edit', [MappingController::class, 'edit'])->whereNumber('mapping')->name('tetapan.mapping.edit');
        Route::post('/tetapan/mapping', [MappingController::class, 'simpan'])->name('tetapan.mapping.simpan');
        Route::post('/tetapan/mapping/{mapping}/kemaskini', [MappingController::class, 'kemaskini'])->whereNumber('mapping')->name('tetapan.mapping.kemaskini');
        Route::post('/tetapan/mapping/{mapping}/padam', [MappingController::class, 'padam'])->whereNumber('mapping')->name('tetapan.mapping.padam');

        // Setting Penyata — tandatangan & mod paparan
        Route::get('/penyata/setting/tandatangan/{signature}/edit', [PenyataSettingController::class, 'sigEdit'])->whereNumber('signature')->name('penyata.setting.sig.edit');
        Route::post('/penyata/setting/tandatangan', [PenyataSettingController::class, 'sigSimpan'])->name('penyata.setting.sig.simpan');
        Route::post('/penyata/setting/tandatangan/{signature}/kemaskini', [PenyataSettingController::class, 'sigKemaskini'])->whereNumber('signature')->name('penyata.setting.sig.kemaskini');
        Route::post('/penyata/setting/tandatangan/{signature}/padam', [PenyataSettingController::class, 'sigPadam'])->whereNumber('signature')->name('penyata.setting.sig.padam');
        Route::post('/penyata/setting/mod', [PenyataSettingController::class, 'mod'])->name('penyata.setting.mod');
    });

    // Fasa 7 — Pentadbiran: pemantauan, audit, ralat, keselamatan, backup (admin sahaja)
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/pemantauan', [PemantauanController::class, 'index'])->name('admin.pemantauan');

        // Jejak audit: BACA juga dibenarkan juruaudit (kumpulan role:admin,juruaudit di bawah).
        Route::post('/audit/sahkan', [AuditController::class, 'sahkan'])->name('admin.audit.sahkan');

        Route::get('/ralat', [RalatController::class, 'index'])->name('admin.ralat');
        Route::post('/ralat/{ralat}/selesai', [RalatController::class, 'selesai'])->whereNumber('ralat')->name('admin.ralat.selesai');

        Route::get('/keselamatan', [KeselamatanController::class, 'index'])->name('admin.keselamatan');

        Route::get('/backup', [BackupController::class, 'index'])->name('admin.backup');
        Route::post('/backup', [BackupController::class, 'simpan'])->name('admin.backup.simpan');
        Route::post('/backup/sekarang', [BackupController::class, 'sekarang'])->name('admin.backup.sekarang');
        Route::post('/backup/tertunggak', [BackupController::class, 'tertunggak'])->name('admin.backup.tertunggak');

        // Fasa 8 — Dual-write ke SPPKMS lama (jambatan sementara)
        Route::get('/dual-write', [DualWriteController::class, 'index'])->name('admin.dualwrite');
        Route::post('/dual-write/toggle', [DualWriteController::class, 'toggle'])->name('admin.dualwrite.toggle');
        Route::post('/dual-write/kredensial', [DualWriteController::class, 'kredensial'])->name('admin.dualwrite.kredensial');
        Route::post('/dual-write/{sync}/cuba-semula', [DualWriteController::class, 'cubaSemula'])->whereNumber('sync')->name('admin.dualwrite.retry');
        Route::post('/dual-write/tertunggak', [DualWriteController::class, 'tertunggak'])->name('admin.dualwrite.tertunggak');
    });

    // Jejak audit (BACA) — admin + JURUAUDIT (semakan bebas); pengesahan POST kekal admin.
    Route::middleware('role:admin,juruaudit')->prefix('admin')->group(function () {
        Route::get('/audit', [AuditController::class, 'index'])->name('admin.audit');
    });

    // Info Masjid (edit profil) — aras MASJID: bendahari & setiausaha.
    Route::middleware('role:bendahari,setiausaha')->group(function () {
        Route::post('/tetapan/masjid', [MasjidController::class, 'kemaskini'])->name('tetapan.masjid.kemaskini');
    });

    // Pengurusan Pengguna — admin (semua masjid) + bendahari (masjid SENDIRI, diskop dlm controller).
    Route::middleware('role:admin,bendahari')->group(function () {
        Route::get('/tetapan/pengguna', [PenggunaController::class, 'index'])->name('tetapan.pengguna');
        Route::get('/tetapan/pengguna/{pengguna}/edit', [PenggunaController::class, 'edit'])->whereNumber('pengguna')->name('tetapan.pengguna.edit');
        Route::post('/tetapan/pengguna', [PenggunaController::class, 'simpan'])->name('tetapan.pengguna.simpan');
        Route::post('/tetapan/pengguna/{pengguna}/kemaskini', [PenggunaController::class, 'kemaskini'])->whereNumber('pengguna')->name('tetapan.pengguna.kemaskini');
    });

    // Tetapan SISTEM (onboarding masjid + API awam) — ADMIN SAHAJA.
    Route::middleware('role:admin')->group(function () {
        // Phase B — daftar masjid baharu + login bendahari pertama (onboarding multi-masjid)
        Route::get('/tetapan/masjid-baru', [MasjidController::class, 'baru'])->name('tetapan.masjid.baru');
        Route::post('/tetapan/masjid-baru', [MasjidController::class, 'ciptaMasjid'])->name('tetapan.masjid.baru.simpan');
        // Phase B follow-up — semai COA standard untuk masjid aktif yang kosong
        Route::post('/tetapan/masjid/sedia-coa', [MasjidController::class, 'sediaCoa'])->name('tetapan.masjid.sediacoa');

        // Fasa 6 — API Awam: klien API, webhook, log panggilan
        Route::get('/tetapan/api', [TetapanApiController::class, 'index'])->name('tetapan.api');
        Route::get('/tetapan/api/log', [TetapanApiController::class, 'log'])->name('tetapan.api.log');
        Route::post('/tetapan/api/klien', [TetapanApiController::class, 'klienSimpan'])->name('tetapan.api.klien');
        Route::post('/tetapan/api/klien/{client}/toggle', [TetapanApiController::class, 'klienToggle'])->whereNumber('client')->name('tetapan.api.klien.toggle');
        Route::post('/tetapan/api/webhook', [TetapanApiController::class, 'webhookSimpan'])->name('tetapan.api.webhook');
        Route::post('/tetapan/api/webhook/{subscription}/padam', [TetapanApiController::class, 'webhookPadam'])->whereNumber('subscription')->name('tetapan.api.webhook.padam');
    });

    /*
     | Fasa 9 — Perakaunan Lanjutan & UX
     */
    // Carian global topbar + suis bahasa (semua peranan)
    Route::get('/carian', [CarianController::class, 'index'])->name('carian');
    Route::get('/bahasa/{lang}', [CarianController::class, 'bahasa'])->name('bahasa');

    // Belanjawan — paparan semua peranan; /semak = endpoint JSON ringan (amaran borang belanja)
    Route::get('/belanjawan', [BelanjawanController::class, 'index'])->name('belanjawan.index');
    Route::get('/belanjawan/semak', [BelanjawanController::class, 'semak'])->name('belanjawan.semak');

    // Dana & Tabung + Susut Nilai — paparan semua peranan
    Route::get('/dana', [DanaController::class, 'index'])->name('dana.index');
    Route::get('/dana/semak', [DanaController::class, 'semak'])->name('dana.semak');
    Route::get('/susut-nilai', [SusutNilaiController::class, 'index'])->name('susutnilai.index');

    // Rekonsiliasi Bank — senarai (BACA) terbuka; tindakan (POST) = bendahari (bawah).
    Route::get('/rekonsiliasi', [RekonsiliasiController::class, 'index'])->name('rekonsiliasi.index');

    Route::middleware('role:bendahari')->group(function () {
        Route::post('/belanjawan', [BelanjawanController::class, 'simpan'])->name('belanjawan.simpan');
        Route::post('/dana', [DanaController::class, 'simpan'])->name('dana.simpan');

        // Susut nilai — jana & pos bulan semasa + pelupusan aset (modal sebab)
        Route::post('/susut-nilai/jana', [SusutNilaiController::class, 'jana'])->name('susutnilai.jana');
        Route::post('/susut-nilai/{aset}/lupus', [SusutNilaiController::class, 'lupus'])->whereNumber('aset')->name('susutnilai.lupus');

        // Rekonsiliasi Bank (tindakan)
        Route::post('/rekonsiliasi/import', [RekonsiliasiController::class, 'import'])->name('rekonsiliasi.import');
        Route::post('/rekonsiliasi/{line}/padan', [RekonsiliasiController::class, 'padan'])->whereNumber('line')->name('rekonsiliasi.padan');
        Route::post('/rekonsiliasi/{line}/abaikan', [RekonsiliasiController::class, 'abaikan'])->whereNumber('line')->name('rekonsiliasi.abaikan');

        // Bulk sahkan draf AI
        Route::post('/draf/bulk-sahkan', [DrafBulkController::class, 'sahkan'])->name('draf.bulk');
    });

    // Kelulusan Maker-Checker — senarai (BACA) terbuka; LULUS/TOLAK = PENGERUSI sahaja
    // (checker). Admin BUKAN pelulus (pengasingan tugas).
    Route::get('/kelulusan', [KelulusanController::class, 'index'])->name('kelulusan.index');
    Route::middleware('role:pengerusi')->group(function () {
        Route::post('/kelulusan/{approval}/lulus', [KelulusanController::class, 'lulus'])->whereNumber('approval')->name('kelulusan.lulus');
        Route::post('/kelulusan/{approval}/tolak', [KelulusanController::class, 'tolak'])->whereNumber('approval')->name('kelulusan.tolak');
    });

    // Tutup Tahun & Kawalan Dalaman — aras MASJID: senarai (BACA) terbuka; TULIS = bendahari.
    Route::get('/tetapan/tutup-tahun', [TutupTahunController::class, 'index'])->name('tutuptahun.index');
    Route::get('/tetapan/kawalan', [KawalanController::class, 'index'])->name('kawalan.index');
    Route::middleware('role:bendahari')->group(function () {
        Route::post('/tetapan/tutup-tahun', [TutupTahunController::class, 'tutup'])->name('tutuptahun.tutup');
        Route::post('/tetapan/kawalan', [KawalanController::class, 'simpan'])->name('kawalan.simpan');
    });
});
