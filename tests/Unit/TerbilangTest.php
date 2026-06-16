<?php

namespace Tests\Unit;

use App\Support\Terbilang;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TerbilangTest extends TestCase
{
    public static function kesData(): array
    {
        return [
            'satu'              => [1.00, 'SATU SAHAJA'],
            'lima belas + sen'  => [15.50, 'LIMA BELAS DAN LIMA PULUH SEN SAHAJA'],
            'dua puluh satu'    => [21.00, 'DUA PULUH SATU SAHAJA'],
            'seratus lima belas' => [115.00, 'SERATUS LIMA BELAS SAHAJA'],
            'lima ratus'        => [500.00, 'LIMA RATUS SAHAJA'],
            'enam ratus tiga puluh' => [630.00, 'ENAM RATUS TIGA PULUH SAHAJA'],
            'seribu + sen'      => [1234.50, 'SERIBU DUA RATUS TIGA PULUH EMPAT DAN LIMA PULUH SEN SAHAJA'],
            'sejuta'            => [1000000.00, 'SEJUTA SAHAJA'],
            'sen sahaja'        => [0.50, 'LIMA PULUH SEN SAHAJA'],
            'kosong'            => [0.00, 'KOSONG SAHAJA'],
        ];
    }

    #[DataProvider('kesData')]
    public function test_terbilang_ringgit(float $amaun, string $jangkaan): void
    {
        $this->assertSame($jangkaan, Terbilang::ringgit($amaun));
    }
}
