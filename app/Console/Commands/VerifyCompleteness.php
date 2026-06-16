<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Laporan kelengkapan medan kebolehkesanan (advisory — TIDAK gagal).
 * Medan ini OPTIONAL (setia SPPKMS) tetapi penting untuk jejak audit:
 * siapa pemberi/penerima, saksi, tarikh masuk bank, no slip/cek.
 * Guna untuk pantau supaya rekod BARU dilengkapkan; rekod sejarah migrasi
 * mungkin kekal kosong jika tiada dalam sumber V1.
 */
class VerifyCompleteness extends Command
{
    protected $signature = 'sppkms:verify-completeness';
    protected $description = 'Lapor kelengkapan medan kebolehkesanan (nama pemberi/saksi/tar bank-in/pemohon/no cek) — advisory';

    public function handle(): int
    {
        $this->baris('KUTIPAN (Resit Penerimaan)', $this->kutipan());
        $this->newLine();
        $this->baris('PEMBAYARAN (Baucer)', $this->pembayaran());

        $this->newLine();
        $this->comment('Nota: medan ini OPTIONAL (tidak wajib). Rekod sejarah migrasi mungkin kosong '
            .'jika tiada dalam sumber V1. Lengkapkan rekod BARU untuk kebolehkesanan audit penuh.');

        return self::SUCCESS; // advisory — sentiasa lulus
    }

    /** @return array<int, array{0:string,1:int,2:int}> [label, berisi, denominator] */
    private function kutipan(): array
    {
        $total = (int) DB::table('kutipan')->where('status', 'ACTIVE')->count();

        return [
            ['nama_pemberi', $this->isi('kutipan', 'nama_pemberi'), $total],
            ['tar_bankin', (int) DB::table('kutipan')->where('status', 'ACTIVE')->whereNotNull('tar_bankin')->count(), $total],
            ['no_slip', $this->isi('kutipan', 'no_slip'), $total],
            ['saksi1', $this->isi('kutipan', 'saksi1'), $total],
        ];
    }

    /** @return array<int, array{0:string,1:int,2:int}> */
    private function pembayaran(): array
    {
        $total = (int) DB::table('pembayaran')->where('status', 'ACTIVE')->count();
        $cek = (int) DB::table('pembayaran')->where('status', 'ACTIVE')->where('cara_bayar', 'CEK')->count();

        return [
            ['pemohon', $this->isi('pembayaran', 'pemohon'), $total],
            ['no_cek (cara=CEK)', (int) DB::table('pembayaran')->where('status', 'ACTIVE')->where('cara_bayar', 'CEK')->whereNotNull('no_cek')->where('no_cek', '<>', '')->count(), $cek],
        ];
    }

    private function isi(string $jadual, string $medan): int
    {
        return (int) DB::table($jadual)->where('status', 'ACTIVE')
            ->whereNotNull($medan)->where($medan, '<>', '')->count();
    }

    /** @param array<int, array{0:string,1:int,2:int}> $medan */
    private function baris(string $tajuk, array $medan): void
    {
        $this->info($tajuk);
        foreach ($medan as [$label, $isi, $denom]) {
            $kosong = max(0, $denom - $isi);
            $pct = $denom > 0 ? round($isi / $denom * 100) : 0;
            $this->line(sprintf('  %-26s %5d berisi / %5d kosong  (%d%% dari %d)', $label, $isi, $kosong, $pct, $denom));
        }
    }
}
