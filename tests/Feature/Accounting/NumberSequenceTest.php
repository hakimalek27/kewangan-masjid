<?php

namespace Tests\Feature\Accounting;

use App\Enums\SequenceType;
use App\Services\Accounting\NumberSequenceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

class NumberSequenceTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private NumberSequenceService $seq;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
        $this->seq = app(NumberSequenceService::class);
    }

    public function test_format_dan_kenaikan_kaunter(): void
    {
        $this->seq->setStart(SequenceType::PV, 4, 466);

        $this->assertSame('PV0466', $this->seq->peek(SequenceType::PV));
        $this->assertSame('PV0466', $this->seq->next(SequenceType::PV));
        $this->assertSame('PV0467', $this->seq->next(SequenceType::PV));
        $this->assertSame('PV0468', $this->seq->peek(SequenceType::PV));
    }

    public function test_siri_resit_tiada_prefix(): void
    {
        $this->seq->setStart(SequenceType::RESIT, 4, 925);
        $this->assertSame('0925', $this->seq->next(SequenceType::RESIT));
    }

    public function test_siri_jnl_lima_digit(): void
    {
        $this->seq->setStart(SequenceType::JNL, 5, 9664);
        $this->assertSame('JNL09664', $this->seq->next(SequenceType::JNL));
    }

    public function test_peek_tidak_menambah_kaunter(): void
    {
        $this->seq->setStart(SequenceType::PWR, 4, 407);

        $this->seq->peek(SequenceType::PWR);
        $this->seq->peek(SequenceType::PWR);

        $this->assertSame('PWR0407', $this->seq->next(SequenceType::PWR));
    }

    public function test_rollback_mengembalikan_kaunter(): void
    {
        $this->seq->setStart(SequenceType::PV, 4, 100);

        try {
            DB::transaction(function () {
                $this->seq->next(SequenceType::PV); // PV0100
                throw new \RuntimeException('paksa rollback');
            });
        } catch (\RuntimeException) {
        }

        // Kaunter berundur bersama rollback — tiada jurang nombor
        $this->assertSame('PV0100', $this->seq->next(SequenceType::PV));
    }

    public function test_siri_berasingan_tidak_bercampur(): void
    {
        $this->seq->setStart(SequenceType::PV, 4, 10);
        $this->seq->setStart(SequenceType::PWR, 4, 20);

        $this->assertSame('PV0010', $this->seq->next(SequenceType::PV));
        $this->assertSame('PWR0020', $this->seq->next(SequenceType::PWR));
        $this->assertSame('PV0011', $this->seq->next(SequenceType::PV));
    }
}
