<?php

namespace Tests\Feature;

use App\Models\Coa;
use App\Models\JournalEntry;
use App\Models\JournalVoucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Semakan asas skema & data sejarah — memastikan aplikasi tersambung
 * ke DB yang betul dan data migrasi tidak terjejas.
 */
class SchemaSmokeTest extends TestCase
{
    public function test_jadual_teras_wujud(): void
    {
        foreach ([
            'masjid', 'app_user', 'coa', 'coa_local_mapping', 'bank_account',
            'number_sequence', 'journal_voucher', 'journal_entry', 'kutipan',
            'pembayaran', 'fixed_asset', 'fd_investment', 'audit_trail',
            'doc_inbox', 'ai_extraction', 'txn_draft', 'api_client',
            'backup_config', 'sppkms_sync', 'secret_vault',
        ] as $jadual) {
            $this->assertTrue(Schema::hasTable($jadual), "Jadual {$jadual} tiada");
        }
    }

    public function test_coa_129_akaun(): void
    {
        app()->instance('current.masjid_id', config('sppkms.masjid_id'));
        $this->assertSame(129, Coa::count());
    }

    public function test_data_sejarah_utuh(): void
    {
        $this->assertGreaterThanOrEqual(3776, JournalVoucher::withoutMasjidScope()->count());
        $this->assertGreaterThanOrEqual(7552, JournalEntry::count());
    }

    public function test_double_entry_seimbang_keseluruhan(): void
    {
        $row = DB::table('journal_entry as je')
            ->join('journal_voucher as jv', 'jv.id', '=', 'je.voucher_id')
            ->where('jv.status', 'POSTED')
            ->selectRaw('ROUND(SUM(je.debit),2) as d, ROUND(SUM(je.kredit),2) as k')
            ->first();

        $this->assertSame($row->d, $row->k, "Jurnal tidak seimbang: Dr {$row->d} vs Cr {$row->k}");
    }

    public function test_tiada_voucher_tidak_seimbang(): void
    {
        $rosak = DB::table('journal_entry')
            ->select('voucher_id')
            ->groupBy('voucher_id')
            ->havingRaw('ROUND(SUM(debit),2) <> ROUND(SUM(kredit),2)')
            ->count();

        $this->assertSame(0, $rosak);
    }
}
