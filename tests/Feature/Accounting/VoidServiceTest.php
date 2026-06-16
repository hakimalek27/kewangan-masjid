<?php

namespace Tests\Feature\Accounting;

use App\Enums\SourceType;
use App\Exceptions\AlreadyVoidedException;
use App\Models\JournalVoucher;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\VoidService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

class VoidServiceTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private JournalService $journal;
    private VoidService $void;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
        $this->journal = app(JournalService::class);
        $this->void = app(VoidService::class);
    }

    private function postUjian(string $amaun = '5.00'): JournalVoucher
    {
        return $this->journal->post(
            '2026-06-12', SourceType::KUTIPAN, null, 'UJIAN VOID', [
                ['coa_id' => $this->coaId('250-05010'), 'debit' => $amaun, 'kredit' => 0],
                ['coa_id' => $this->coaId('400-03010'), 'debit' => 0, 'kredit' => $amaun],
            ], 'UJI-VD-'.uniqid()
        );
    }

    public function test_void_cipta_pembalik_dan_baki_pulih_tepat(): void
    {
        $bakiBankAsal  = $this->bakiCoa('250-05010');
        $bakiHasilAsal = $this->bakiCoa('400-03010');

        $voucher = $this->postUjian('7.77');
        $this->assertSame(bcadd($bakiBankAsal, '7.77', 2), $this->bakiCoa('250-05010'));

        $pembalik = $this->void->voidVoucher($voucher, 'ujian');

        // Voucher asal & pembalik kedua-duanya VOID (dikecualikan dari laporan)
        $this->assertSame('VOID', $voucher->fresh()->status);
        $this->assertSame('VOID', $pembalik->status);
        $this->assertSame('RV-'.$voucher->voucher_ref, $pembalik->voucher_ref);
        $this->assertSame($voucher->period_ym, $pembalik->period_ym);
        $this->assertSame($voucher->tarikh->format('Y-m-d'), $pembalik->tarikh->format('Y-m-d'));

        // Dr/Cr diterbalikkan
        $entriPembalik = $pembalik->entries;
        $this->assertSame('7.77', (string) $entriPembalik->where('coa_id', $this->coaId('250-05010'))->first()->kredit);
        $this->assertSame('7.77', (string) $entriPembalik->where('coa_id', $this->coaId('400-03010'))->first()->debit);

        // Residual KOSONG — baki kembali tepat ke nilai asal
        $this->assertSame($bakiBankAsal, $this->bakiCoa('250-05010'));
        $this->assertSame($bakiHasilAsal, $this->bakiCoa('400-03010'));
    }

    public function test_void_dua_kali_ditolak(): void
    {
        $voucher = $this->postUjian();
        $this->void->voidVoucher($voucher, 'kali pertama');

        $this->expectException(AlreadyVoidedException::class);
        $this->void->voidVoucher($voucher->fresh(), 'kali kedua');
    }

    public function test_void_direkod_dalam_audit_trail(): void
    {
        $voucher = $this->postUjian();
        $this->void->voidVoucher($voucher, 'ujian audit');

        $this->assertDatabaseHas('audit_trail', [
            'action'    => 'VOID',
            'entity'    => 'journal_voucher',
            'entity_id' => $voucher->id,
        ]);
    }
}
