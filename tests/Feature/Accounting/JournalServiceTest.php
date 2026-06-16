<?php

namespace Tests\Feature\Accounting;

use App\Enums\SourceType;
use App\Exceptions\PeriodLockedException;
use App\Exceptions\UnbalancedJournalException;
use App\Models\JournalVoucher;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\PeriodService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

class JournalServiceTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private JournalService $journal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
        $this->journal = app(JournalService::class);
    }

    private function lines(string $drKod, string $crKod, string $amaun): array
    {
        return [
            ['coa_id' => $this->coaId($drKod), 'debit' => $amaun, 'kredit' => 0, 'memo' => 'ujian dr'],
            ['coa_id' => $this->coaId($crKod), 'debit' => 0, 'kredit' => $amaun, 'memo' => 'ujian cr'],
        ];
    }

    public function test_post_seimbang_berjaya_dan_period_ym_auto(): void
    {
        $voucher = $this->journal->post(
            '2026-06-12', SourceType::JURNAL, null, 'UJIAN POST',
            $this->lines('250-05010', '400-03010', '1.00'),
            'UJI-JV-001'
        );

        $this->assertSame('POSTED', $voucher->status);
        $this->assertSame('2026-06', $voucher->period_ym);
        $this->assertCount(2, $voucher->entries);
        $this->assertSame('1.00', number_format($voucher->entries->sum('debit'), 2, '.', ''));
        $this->assertSame('1.00', number_format($voucher->entries->sum('kredit'), 2, '.', ''));
    }

    public function test_post_tidak_seimbang_DITOLAK_tiada_rekod(): void
    {
        $bilSebelum = JournalVoucher::withoutMasjidScope()->count();

        try {
            $this->journal->post('2026-06-12', SourceType::JURNAL, null, 'UJIAN', [
                ['coa_id' => $this->coaId('250-05010'), 'debit' => '2.00', 'kredit' => 0],
                ['coa_id' => $this->coaId('400-03010'), 'debit' => 0, 'kredit' => '1.99'],
            ], 'UJI-JV-X');
            $this->fail('Sepatutnya UnbalancedJournalException');
        } catch (UnbalancedJournalException $e) {
            $this->assertStringContainsString('tidak seimbang', $e->getMessage());
        }

        $this->assertSame($bilSebelum, JournalVoucher::withoutMasjidScope()->count());
    }

    public function test_baris_debit_dan_kredit_serentak_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->journal->post('2026-06-12', SourceType::JURNAL, null, 'UJIAN', [
            ['coa_id' => $this->coaId('250-05010'), 'debit' => '1.00', 'kredit' => '1.00'],
            ['coa_id' => $this->coaId('400-03010'), 'debit' => 0, 'kredit' => 0],
        ], 'UJI-JV-X2');
    }

    public function test_nilai_negatif_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->journal->post('2026-06-12', SourceType::JURNAL, null, 'UJIAN', [
            ['coa_id' => $this->coaId('250-05010'), 'debit' => '-1.00', 'kredit' => 0],
            ['coa_id' => $this->coaId('400-03010'), 'debit' => 0, 'kredit' => '-1.00'],
        ], 'UJI-JV-X3');
    }

    public function test_kod_kepala_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // 200-01000 HARTANAH DAN PERALATAN ialah kod kepala (is_header=1)
        $this->journal->post('2026-06-12', SourceType::JURNAL, null, 'UJIAN',
            $this->lines('200-01000', '400-03010', '1.00'), 'UJI-JV-X4');
    }

    public function test_jumlah_sifar_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->journal->post('2026-06-12', SourceType::JURNAL, null, 'UJIAN', [
            ['coa_id' => $this->coaId('250-05010'), 'debit' => 0, 'kredit' => 0],
            ['coa_id' => $this->coaId('400-03010'), 'debit' => 0, 'kredit' => 0],
        ], 'UJI-JV-X5');
    }

    public function test_tempoh_terkunci_ditolak(): void
    {
        app(PeriodService::class)->lockUntil('2026-06');

        try {
            $this->expectException(PeriodLockedException::class);
            $this->journal->post('2026-06-12', SourceType::JURNAL, null, 'UJIAN',
                $this->lines('250-05010', '400-03010', '1.00'), 'UJI-JV-X6');
        } finally {
            app(PeriodService::class)->unlock();
        }
    }

    public function test_tempoh_selepas_kunci_dibenarkan(): void
    {
        app(PeriodService::class)->lockUntil('2026-05');

        try {
            $voucher = $this->journal->post('2026-06-12', SourceType::JURNAL, null, 'UJIAN',
                $this->lines('250-05010', '400-03010', '1.00'), 'UJI-JV-OK6');
            $this->assertSame('POSTED', $voucher->status);
        } finally {
            app(PeriodService::class)->unlock();
        }
    }
}
