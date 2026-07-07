<?php

namespace Tests\Feature\Admin;

use App\Jobs\RunBackupItem;
use App\Jobs\RunDailyDbDump;
use App\Models\AppUser;
use App\Models\BackupConfig;
use App\Models\BackupLog;
use App\Models\BackupQueue;
use App\Services\Integration\Contracts\GdriveClientInterface;
use App\Services\Integration\GoogleDriveBackupService;
use App\Services\Transaksi\KutipanService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Fasa 7 — Backup Google Drive + log/pemantauan admin.
 * Klien Drive di-mock melalui instance palsu pada GdriveClientInterface
 * dalam container — TIADA panggilan rangkaian sebenar.
 */
class BackupPemantauanTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private const MYSQLDUMP = 'C:\\Users\\hakim\\xampp\\mysql\\bin\\mysqldump.exe';

    private AppUser $admin;
    private AppUser $bendahari;
    private FakeGdriveClient $gdrive;

    /** @var array<int,string> fail payload untuk dibersihkan selepas ujian */
    private array $failSementara = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();

        // Halang sebarang panggilan HTTP sebenar (cth amaran Telegram)
        \Illuminate\Support\Facades\Http::fake();

        $buat = fn (string $role) => AppUser::create([
            'masjid_id' => config('spkm.masjid_id'), 'login' => 'uji_'.$role.'_'.uniqid(),
            'nama_penuh' => 'Ujian '.$role, 'role' => $role,
            'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);
        $this->admin = $buat('admin');
        $this->bendahari = $buat('bendahari');

        // Konfigurasi backup aktif (rollback selepas ujian oleh DatabaseTransactions)
        BackupConfig::withoutMasjidScope()->updateOrCreate(
            ['masjid_id' => config('spkm.masjid_id')],
            [
                'provider'         => 'GDRIVE',
                'gdrive_folder_id' => 'folder-uji-123',
                'backup_mode'      => 'PER_TRANSAKSI,HARIAN,LOG',
                'encrypt'          => 1,
                'retention_days'   => 3650,
                'is_active'        => 1,
            ]
        );

        // Klien Drive PALSU — diikat pada interface dalam container
        $this->gdrive = new FakeGdriveClient();
        app()->instance(GdriveClientInterface::class, $this->gdrive);
    }

    protected function tearDown(): void
    {
        foreach ($this->failSementara as $path) {
            Storage::disk('local')->delete($path);
        }
        parent::tearDown();
    }

    // ---------- Pembantu ----------

    private function ciptaKutipan(): \App\Models\Kutipan
    {
        return app(KutipanService::class)->create([
            'tarikh'       => now()->format('Y-m-d'),
            'coa_id'       => $this->coaId('400-01010'),
            'kaedah'       => 'TUNAI',
            'jumlah'       => '123.45',
            'auto_resit'   => true,
            'nama_pemberi' => 'Penderma Ujian Backup',
        ]);
    }

    private function ciptaItemBarisan(string $kandungan = '{"uji":"backup"}'): BackupQueue
    {
        $relPath = 'backup-payload/uji-item-'.uniqid().'.json';
        Storage::disk('local')->put($relPath, $kandungan);
        $this->failSementara[] = $relPath;

        return BackupQueue::withoutMasjidScope()->create([
            'masjid_id'    => config('spkm.masjid_id'),
            'jenis'        => 'TRANSACTION',
            'ref_id'       => 999999,
            'payload_path' => $relPath,
            'status'       => 'PENDING',
        ]);
    }

    // ---------- (E6) Uji-pulih backup ----------

    public function test_uji_pulih_terkini_ok_dan_kesan_kerosakan(): void
    {
        $service = app(GoogleDriveBackupService::class);
        $item = $this->ciptaItemBarisan('{"data":"penting"}');

        // Backup → simpan cipher dalam fake + BackupLog dengan gdrive_file_id
        $log = $service->backupItem($item);
        $this->assertSame('OK', $log->status);

        // Uji-pulih: muat turun dari fake + sahkan → OK
        $hasil = $service->ujiPulihTerkini((int) config('spkm.masjid_id'));
        $this->assertSame('ok', $hasil['status']);

        // Simulasi kerosakan di Drive → uji-pulih mesti kesan 'gagal'
        $this->gdrive->simpanan[FakeGdriveClient::FILE_ID] = 'kandungan-rosak';
        $hasilRosak = $service->ujiPulihTerkini((int) config('spkm.masjid_id'));
        $this->assertSame('gagal', $hasilRosak['status']);
    }

    // ---------- (a) Observer per-transaksi ----------

    public function test_observer_kutipan_memasukkan_barisan_backup_jenis_transaction(): void
    {
        Queue::fake([RunBackupItem::class]);

        $kutipan = $this->ciptaKutipan();

        $item = BackupQueue::withoutMasjidScope()
            ->where('jenis', 'TRANSACTION')
            ->where('ref_id', $kutipan->id)
            ->first();

        $this->assertNotNull($item, 'Baris backup_queue jenis TRANSACTION tidak dicipta oleh observer.');
        $this->assertSame('PENDING', $item->status);
        $this->failSementara[] = $item->payload_path;

        // Payload mengandungi rekod + voucher + entries (snapshot lengkap)
        $payload = json_decode(Storage::disk('local')->get($item->payload_path), true);
        $this->assertSame('kutipan', $payload['entiti']);
        $this->assertSame($kutipan->id, $payload['rekod']['id']);
        $this->assertNotNull($payload['voucher']);
        $this->assertCount(2, $payload['entries']); // Dr + Cr

        Queue::assertPushed(RunBackupItem::class, fn ($job) => $job->backupQueueId === $item->id);
    }

    public function test_observer_tidak_aktif_jika_config_dimatikan(): void
    {
        Queue::fake([RunBackupItem::class]);
        BackupConfig::withoutMasjidScope()
            ->where('masjid_id', config('spkm.masjid_id'))
            ->update(['is_active' => 0]);

        $kutipan = $this->ciptaKutipan();

        $this->assertDatabaseMissing('backup_queue', [
            'jenis'  => 'TRANSACTION',
            'ref_id' => $kutipan->id,
        ]);
        Queue::assertNothingPushed();
    }

    // ---------- (b) RunBackupItem + mock client ----------

    public function test_run_backup_item_berjaya_log_ok_checksum_betul_status_done(): void
    {
        $asal = '{"uji":"kandungan-rahsia-backup"}';
        $item = $this->ciptaItemBarisan($asal);

        (new RunBackupItem($item->id))->handle(app(GoogleDriveBackupService::class));

        $item->refresh();
        $this->assertSame('DONE', $item->status);
        $this->assertSame(1, $item->attempts);

        // Klien palsu menerima kandungan TERSULIT (bukan plaintext) + folder betul
        $this->assertSame('folder-uji-123', $this->gdrive->folderId);
        $this->assertStringNotContainsString('kandungan-rahsia-backup', $this->gdrive->kandungan);
        $this->assertSame($asal, Crypt::decryptString($this->gdrive->kandungan));

        $log = BackupLog::withoutMasjidScope()
            ->where('gdrive_file_id', FakeGdriveClient::FILE_ID)->first();
        $this->assertNotNull($log);
        $this->assertSame('OK', $log->status);
        $this->assertSame('TRANSACTION', $log->jenis);
        $this->assertSame(hash('sha256', $this->gdrive->kandungan), $log->checksum_sha256);
        $this->assertSame(strlen($this->gdrive->kandungan), (int) $log->size_bytes);

        $config = BackupConfig::withoutMasjidScope()->find(config('spkm.masjid_id'));
        $this->assertNotNull($config->last_backup_at, 'last_backup_at sepatutnya dikemaskini.');
    }

    public function test_run_backup_item_gagal_log_failed(): void
    {
        $item = $this->ciptaItemBarisan();
        $this->gdrive->gagalkan = true;

        try {
            (new RunBackupItem($item->id))->handle(app(GoogleDriveBackupService::class));
            $this->fail('Sepatutnya melempar exception apabila upload gagal.');
        } catch (\RuntimeException) {
            // dijangka
        }

        $this->assertSame('PENDING', $item->refresh()->status); // menunggu retry
        $this->assertDatabaseHas('backup_log', [
            'masjid_id' => config('spkm.masjid_id'),
            'jenis'     => 'TRANSACTION',
            'status'    => 'FAILED',
        ]);
    }

    // ---------- (c) ujiPulih ----------

    public function test_uji_pulih_checksum_padan_true_kandungan_diubah_false(): void
    {
        $item = $this->ciptaItemBarisan('{"uji":"pulih"}');
        $service = app(GoogleDriveBackupService::class);
        $log = $service->backupItem($item);

        // Muat turun "dari Drive" = kandungan yang diterima klien palsu
        $this->assertTrue($service->ujiPulih($log, $this->gdrive->kandungan));

        // Kandungan diubah/rosak → false
        $this->assertFalse($service->ujiPulih($log, $this->gdrive->kandungan.'X'));
        $this->assertFalse($service->ujiPulih($log, 'kandungan-palsu'));
    }

    // ---------- (d) RunDailyDbDump (mysqldump sebenar lawan sppkms_test) ----------

    public function test_run_daily_db_dump_memasukkan_barisan_db_dump(): void
    {
        if (!is_file(self::MYSQLDUMP)) {
            $this->markTestSkipped('mysqldump tidak ditemui di '.self::MYSQLDUMP);
        }

        config(['spkm.mysqldump_path' => self::MYSQLDUMP]);
        Queue::fake([RunBackupItem::class]);

        (new RunDailyDbDump)->handle();

        $item = BackupQueue::withoutMasjidScope()
            ->where('masjid_id', config('spkm.masjid_id'))
            ->where('jenis', 'DB_DUMP')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($item, 'Baris backup_queue jenis DB_DUMP tidak dicipta.');
        $this->failSementara[] = $item->payload_path;

        // Dump kini dimampatkan gzip (.sql.gz) — nyahmampat dahulu sebelum semak
        $this->assertStringEndsWith('.sql.gz', $item->payload_path);
        $sql = gzdecode(Storage::disk('local')->get($item->payload_path));
        $this->assertStringContainsString('CREATE TABLE', $sql); // dump sebenar DB sppkms_test
        $this->assertStringContainsString('journal_voucher', $sql);

        Queue::assertPushed(RunBackupItem::class, fn ($job) => $job->backupQueueId === $item->id);
    }

    // ---------- (e) Halaman admin: 200 untuk admin, 403 untuk bendahari ----------

    public function test_halaman_pentadbiran_dipapar_untuk_admin_sahaja(): void
    {
        $halaman = [
            route('admin.pemantauan'),
            route('admin.audit'),
            route('admin.ralat'),
            route('admin.keselamatan'),
            route('admin.keselamatan', ['tab' => 'login']),
            route('admin.backup'),
        ];

        foreach ($halaman as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
            $this->actingAs($this->bendahari)->get($url)->assertForbidden();
        }
    }

    // ---------- (f) verify-audit-chain ----------

    public function test_verify_audit_chain_lulus_exit_0(): void
    {
        // tambah beberapa baris audit baharu (rantai berterusan)
        Queue::fake([RunBackupItem::class]);
        $this->ciptaKutipan();

        $this->assertSame(0, Artisan::call('spkm:verify-audit-chain'));
        $this->assertStringContainsString('OK', Artisan::output());
    }

    // ---------- (g) Retensi: PruneBackups padam Drive + payload lama ----------

    public function test_prune_padam_backup_log_dan_fail_drive_melepasi_retensi(): void
    {
        $mid = config('spkm.masjid_id');
        // Retensi 30 hari untuk masjid ini
        BackupConfig::withoutMasjidScope()->where('masjid_id', $mid)->update(['retention_days' => 30]);

        // Log LAMA (60 hari) → patut dipadam (Drive + baris)
        $lama = BackupLog::withoutMasjidScope()->create([
            'masjid_id' => $mid, 'jenis' => 'DB_DUMP', 'file_name' => 'lama.enc',
            'size_bytes' => 10, 'checksum_sha256' => str_repeat('a', 64),
            'gdrive_file_id' => 'DRIVE-LAMA-1', 'status' => 'OK',
            'created_at' => now()->subDays(60),
        ]);
        // Log BARU (5 hari) → kekal
        $baru = BackupLog::withoutMasjidScope()->create([
            'masjid_id' => $mid, 'jenis' => 'DB_DUMP', 'file_name' => 'baru.enc',
            'size_bytes' => 10, 'checksum_sha256' => str_repeat('b', 64),
            'gdrive_file_id' => 'DRIVE-BARU-1', 'status' => 'OK',
            'created_at' => now()->subDays(5),
        ]);

        (new \App\Jobs\PruneBackups)->handle();

        $this->assertContains('DRIVE-LAMA-1', $this->gdrive->dipadam, 'Fail Drive lama patut dipadam');
        $this->assertNotContains('DRIVE-BARU-1', $this->gdrive->dipadam, 'Fail Drive baru patut kekal');
        $this->assertNull(BackupLog::withoutMasjidScope()->find($lama->id), 'Baris log lama patut dipadam');
        $this->assertNotNull(BackupLog::withoutMasjidScope()->find($baru->id), 'Baris log baru patut kekal');
    }

    public function test_prune_padam_fail_payload_tempatan_lama_yang_done(): void
    {
        $mid = config('spkm.masjid_id');

        // Payload DONE lama (10 hari) → patut dipadam
        $pathLama = 'backup-payload/uji-prune-'.uniqid().'.json';
        Storage::disk('local')->put($pathLama, '{"x":1}');
        BackupQueue::withoutMasjidScope()->create([
            'masjid_id' => $mid, 'jenis' => 'TRANSACTION', 'payload_path' => $pathLama,
            'status' => 'DONE', 'created_at' => now()->subDays(10),
        ]);

        // Payload DONE baru (1 hari) → kekal
        $pathBaru = 'backup-payload/uji-prune-'.uniqid().'.json';
        Storage::disk('local')->put($pathBaru, '{"x":2}');
        $this->failSementara[] = $pathBaru;
        BackupQueue::withoutMasjidScope()->create([
            'masjid_id' => $mid, 'jenis' => 'TRANSACTION', 'payload_path' => $pathBaru,
            'status' => 'DONE', 'created_at' => now()->subDays(1),
        ]);

        (new \App\Jobs\PruneBackups)->handle();

        $this->assertFalse(Storage::disk('local')->exists($pathLama), 'Fail payload DONE lama patut dipadam');
        $this->assertTrue(Storage::disk('local')->exists($pathBaru), 'Fail payload DONE baru patut kekal');
    }
}

/** Klien Google Drive palsu — rekod muat naik terakhir, tiada rangkaian. */
class FakeGdriveClient implements GdriveClientInterface
{
    public const FILE_ID = 'fake-gdrive-file-id-001';

    public ?string $namaFail = null;
    public ?string $kandungan = null;
    public ?string $folderId = null;
    public bool $gagalkan = false;
    /** @var array<int,string> fileId yang dipadam */
    public array $dipadam = [];
    /** @var array<string,string> fileId => kandungan (untuk uji-pulih) */
    public array $simpanan = [];

    public function upload(string $namaFail, string $kandungan, string $folderId): string
    {
        if ($this->gagalkan) {
            throw new \RuntimeException('Simulasi kegagalan Google Drive (ujian).');
        }

        $this->namaFail = $namaFail;
        $this->kandungan = $kandungan;
        $this->folderId = $folderId;
        $this->simpanan[self::FILE_ID] = $kandungan;

        return self::FILE_ID;
    }

    public function deleteFile(string $fileId): void
    {
        $this->dipadam[] = $fileId;
    }

    public function download(string $fileId): string
    {
        return $this->simpanan[$fileId] ?? throw new \RuntimeException('fileId tidak wujud dalam fake.');
    }
}
