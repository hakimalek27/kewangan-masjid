<?php

namespace Tests\Feature\Ai;

use App\Models\BankAccount;
use App\Models\PenyataSemakan;
use App\Services\Ai\KuotaPenyataService;
use App\Support\Setting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Kuota Semak Penyata (AI): had lalai 3/bulan, override per-tenant (0=mati),
 * top-up bulan semasa, semantik tempahan (GAGAL tidak dikira), rollover bulan.
 */
class KuotaPenyataTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private KuotaPenyataService $kuota;
    private int $masjid;
    private int $bankId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
        $this->kuota = app(KuotaPenyataService::class);
        $this->masjid = (int) config('spkm.masjid_id');
        $this->bankId = (int) BankAccount::withoutMasjidScope()
            ->where('masjid_id', $this->masjid)->value('id');

        Setting::set('semak_penyata_enabled', 'on', KuotaPenyataService::MASJID_GLOBAL);
    }

    private function buatBatch(string $status = 'SEDIA'): PenyataSemakan
    {
        return PenyataSemakan::create([
            'bank_account_id' => $this->bankId,
            'file_path' => 'x', 'mime' => 'application/pdf',
            'file_hash' => hash('sha256', uniqid('', true)),
            'status' => $status,
        ]);
    }

    public function test_had_lalai_tiga(): void
    {
        $this->assertSame(3, $this->kuota->effectiveLimit($this->masjid));
        $this->assertTrue($this->kuota->boleh($this->masjid));
    }

    public function test_had_habis_menyekat(): void
    {
        $this->buatBatch();
        $this->buatBatch();
        $this->buatBatch();

        $this->assertSame(0, $this->kuota->remaining($this->masjid));
        $this->assertFalse($this->kuota->boleh($this->masjid));
    }

    public function test_topup_membenarkan_semula(): void
    {
        $this->buatBatch();
        $this->buatBatch();
        $this->buatBatch();
        $this->assertFalse($this->kuota->boleh($this->masjid));

        Setting::set('sp_topup_'.now()->format('Y-m'), '2', $this->masjid);

        $this->assertSame(2, $this->kuota->remaining($this->masjid));
        $this->assertTrue($this->kuota->boleh($this->masjid));
    }

    public function test_had_sifar_mematikan_ciri_tenant(): void
    {
        Setting::set('sp_kuota_bulanan', '0', $this->masjid);

        $this->assertSame(0, $this->kuota->effectiveLimit($this->masjid));
        $this->assertFalse($this->kuota->boleh($this->masjid));
    }

    public function test_batch_gagal_tidak_makan_kuota(): void
    {
        $this->buatBatch('GAGAL');
        $this->buatBatch('GAGAL');
        $this->buatBatch('GAGAL');

        $this->assertSame(0, $this->kuota->usedThisMonth($this->masjid));
        $this->assertSame(3, $this->kuota->remaining($this->masjid));
    }

    public function test_rollover_bulan_membebaskan_kuota(): void
    {
        $b1 = $this->buatBatch();
        $b2 = $this->buatBatch();
        $this->assertSame(2, $this->kuota->usedThisMonth($this->masjid));

        // Tarikh cipta ke bulan lepas → keluar daripada kiraan bulan semasa.
        \DB::table('penyata_semakan')->whereIn('id', [$b1->id, $b2->id])
            ->update(['created_at' => now()->subMonth()->startOfMonth()]);

        $this->assertSame(0, $this->kuota->usedThisMonth($this->masjid));
    }

    public function test_toggle_global_mematikan(): void
    {
        Setting::set('semak_penyata_enabled', 'off', KuotaPenyataService::MASJID_GLOBAL);

        $this->assertFalse($this->kuota->globallyEnabled());
        $this->assertFalse($this->kuota->boleh($this->masjid));
    }
}
