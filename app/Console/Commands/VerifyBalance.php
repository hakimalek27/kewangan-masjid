<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyBalance extends Command
{
    protected $signature = 'sppkms:verify-balance';
    protected $description = 'Semak setiap voucher jurnal: Σdebit mesti = Σkredit (integriti double-entry)';

    public function handle(): int
    {
        $rosak = DB::table('journal_entry as je')
            ->join('journal_voucher as jv', 'jv.id', '=', 'je.voucher_id')
            ->select('jv.id', 'jv.voucher_ref')
            ->selectRaw('ROUND(SUM(je.debit),2) as d, ROUND(SUM(je.kredit),2) as k')
            ->groupBy('jv.id', 'jv.voucher_ref')
            ->havingRaw('ROUND(SUM(je.debit),2) <> ROUND(SUM(je.kredit),2)')
            ->get();

        if ($rosak->isEmpty()) {
            $jumlah = DB::table('journal_voucher')->count();
            $this->info("OK — semua {$jumlah} voucher seimbang (Σdebit = Σkredit).");

            return self::SUCCESS;
        }

        $this->error($rosak->count().' voucher TIDAK SEIMBANG:');
        foreach ($rosak as $r) {
            $this->line("  #{$r->id} {$r->voucher_ref}: Dr {$r->d} vs Cr {$r->k}");
        }

        return self::FAILURE;
    }
}
