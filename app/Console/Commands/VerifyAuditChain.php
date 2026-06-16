<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyAuditChain extends Command
{
    protected $signature = 'sppkms:verify-audit-chain';
    protected $description = 'Sahkan hash-chain jejak audit — kesan jika ada baris diubah/dipadam';

    public function handle(): int
    {
        $masjids = DB::table('audit_trail')->distinct()->pluck('masjid_id');
        $gagal = 0;

        foreach ($masjids as $mid) {
            $prev = null;
            $rows = DB::table('audit_trail')
                ->where(fn ($q) => $mid === null ? $q->whereNull('masjid_id') : $q->where('masjid_id', $mid))
                ->orderBy('id')
                ->get();

            foreach ($rows as $row) {
                if ($row->prev_hash !== $prev) {
                    $this->error("Rantai PUTUS pada audit #{$row->id} (masjid ".($mid ?? 'sistem').'): prev_hash tidak padan.');
                    $gagal++;
                }

                $dikira = hash('sha256', implode('|', [
                    $row->prev_hash ?? '', $row->masjid_id ?? '', $row->user_id ?? '',
                    $row->action, $row->entity, $row->entity_id ?? '',
                    $row->before_json ?? '', $row->after_json ?? '', $row->created_at,
                ]));

                if ($dikira !== $row->row_hash) {
                    $this->error("Baris audit #{$row->id} TELAH DIUBAH (hash tidak padan).");
                    $gagal++;
                }

                $prev = $row->row_hash;
            }
        }

        if ($gagal === 0) {
            $jumlah = DB::table('audit_trail')->count();
            $this->info("OK — rantai audit utuh ({$jumlah} baris).");

            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
