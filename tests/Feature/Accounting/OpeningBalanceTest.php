<?php

namespace Tests\Feature\Accounting;

use App\Models\JournalVoucher;
use App\Services\Accounting\OpeningBalanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

class OpeningBalanceTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private OpeningBalanceService $ob;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
        $this->ob = app(OpeningBalanceService::class);
    }

    public function test_set_baki_awal_jana_jurnal_dr_bank_cr_dana_terkumpul(): void
    {
        $voucher = $this->ob->set(2030, [
            ['coa_id' => $this->coaId('250-05010'), 'amaun' => '1000.00', 'side' => 'D'],
        ]);

        $this->assertSame('OB-2030', $voucher->voucher_ref);
        $this->assertSame('2030-00', $voucher->period_ym);

        $entries = $voucher->entries;
        $this->assertSame('1000.00', (string) $entries->where('coa_id', $this->coaId('250-05010'))->first()->debit);
        $this->assertSame('1000.00', (string) $entries->where('coa_id', $this->coaId('100-10000'))->first()->kredit);
        $this->assertSame((string) $entries->sum('debit'), (string) $entries->sum('kredit'));
    }

    public function test_terkunci_tidak_boleh_set_semula(): void
    {
        $this->ob->set(2030, [
            ['coa_id' => $this->coaId('250-05010'), 'amaun' => '500.00', 'side' => 'D'],
        ]);
        $this->ob->lock(2030);

        $this->expectException(RuntimeException::class);
        $this->ob->set(2030, [
            ['coa_id' => $this->coaId('250-05010'), 'amaun' => '999.00', 'side' => 'D'],
        ]);
    }

    public function test_reset_membatalkan_voucher_dan_buka_kunci(): void
    {
        $this->ob->set(2030, [
            ['coa_id' => $this->coaId('250-05010'), 'amaun' => '500.00', 'side' => 'D'],
        ]);
        $this->ob->lock(2030);

        $this->ob->resetAndUnlock(2030);

        $this->assertSame('VOID', JournalVoucher::withoutMasjidScope()
            ->where('voucher_ref', 'like', 'OB-2030-V%')->value('status'));

        // Boleh set semula selepas reset
        $baru = $this->ob->set(2030, [
            ['coa_id' => $this->coaId('250-05010'), 'amaun' => '750.00', 'side' => 'D'],
        ]);
        $this->assertSame('POSTED', $baru->status);
    }
}
