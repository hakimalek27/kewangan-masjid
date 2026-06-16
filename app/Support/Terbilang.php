<?php

namespace App\Support;

/**
 * Terbilang — nombor → perkataan Bahasa Melayu (huruf besar) untuk resit/baucer.
 * Format akhir mengikut resit rasmi: "LIMA RATUS SAHAJA" (dilabel
 * "RINGGIT MALAYSIA:" oleh paparan). Sen disebut "... DAN LIMA PULUH SEN SAHAJA".
 */
final class Terbilang
{
    private const SATUAN = [
        0 => '', 1 => 'satu', 2 => 'dua', 3 => 'tiga', 4 => 'empat', 5 => 'lima',
        6 => 'enam', 7 => 'tujuh', 8 => 'lapan', 9 => 'sembilan',
        10 => 'sepuluh', 11 => 'sebelas',
    ];

    /**
     * Frasa terbilang untuk amaun ringgit, mis. 1234.50 →
     * "SERIBU DUA RATUS TIGA PULUH EMPAT DAN LIMA PULUH SEN SAHAJA".
     */
    public static function ringgit(float $amaun): string
    {
        $amaun = round($amaun, 2);
        $ringgit = (int) floor($amaun);
        $sen = (int) round(($amaun - $ringgit) * 100);

        if ($ringgit > 0 && $sen > 0) {
            $teks = self::perkataan($ringgit).' DAN '.self::perkataan($sen).' SEN';
        } elseif ($ringgit > 0) {
            $teks = self::perkataan($ringgit);
        } elseif ($sen > 0) {
            $teks = self::perkataan($sen).' SEN';
        } else {
            $teks = 'KOSONG';
        }

        return $teks.' SAHAJA';
    }

    /** Perkataan huruf besar bagi satu integer bukan negatif. */
    public static function perkataan(int $n): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', self::eja($n))));
    }

    /** Penjanaan rekursif (huruf kecil) — guna awalan "se" gaya seratus/seribu/sejuta. */
    private static function eja(int $n): string
    {
        if ($n < 12) {
            return self::SATUAN[$n];
        }
        if ($n < 20) {
            return self::eja($n - 10).' belas';
        }
        if ($n < 100) {
            return self::eja(intdiv($n, 10)).' puluh'.self::baki($n % 10);
        }
        if ($n < 200) {
            return 'seratus'.self::baki($n % 100);
        }
        if ($n < 1000) {
            return self::eja(intdiv($n, 100)).' ratus'.self::baki($n % 100);
        }
        if ($n < 2000) {
            return 'seribu'.self::baki($n % 1000);
        }
        if ($n < 1_000_000) {
            return self::eja(intdiv($n, 1000)).' ribu'.self::baki($n % 1000);
        }
        if ($n < 2_000_000) {
            return 'sejuta'.self::baki($n % 1_000_000);
        }
        if ($n < 1_000_000_000) {
            return self::eja(intdiv($n, 1_000_000)).' juta'.self::baki($n % 1_000_000);
        }
        if ($n < 1_000_000_000_000) {
            return self::eja(intdiv($n, 1_000_000_000)).' bilion'.self::baki($n % 1_000_000_000);
        }

        return self::eja(intdiv($n, 1_000_000_000_000)).' trilion'.self::baki($n % 1_000_000_000_000);
    }

    /** Sambungan baki (dengan ruang) atau kosong jika 0. */
    private static function baki(int $n): string
    {
        return $n > 0 ? ' '.self::eja($n) : '';
    }
}
