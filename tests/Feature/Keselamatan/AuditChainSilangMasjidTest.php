<?php

namespace Tests\Feature\Keselamatan;

use App\Models\AuditTrail;
use App\Services\Security\AuditTrailService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * B1 — rantai hash audit MESTI kekal berantai walaupun log() dipanggil dengan
 * masjidId sasaran BERBEZA daripada masjid sesi semasa (cth onboarding masjid
 * baharu oleh admin yang bersesi di masjid lain). Sebelum fix, skop global
 * memecahkan query prev_hash → semua prev_hash NULL.
 */
class AuditChainSilangMasjidTest extends TestCase
{
    use DatabaseTransactions;

    public function test_rantai_utuh_bila_log_ke_masjid_lain_daripada_sesi(): void
    {
        // Sesi semasa "berada" di masjid A
        app()->instance('current.masjid_id', config('spkm.masjid_id'));
        app()->instance('current.user_id', 1);

        $svc = app(AuditTrailService::class);
        $mid = 990001; // masjid sasaran BERBEZA daripada sesi

        DB::transaction(function () use ($svc, $mid) {
            $svc->log('CREATE', 'masjid', null, ['n' => 't'], $mid, null, $mid);
            $svc->log('CREATE', 'app_user', null, ['l' => 'x'], 1, null, $mid);
            $svc->log('CREATE', 'coa', null, ['d' => 5], null, null, $mid);
        });

        $rows = AuditTrail::withoutMasjidScope()->where('masjid_id', $mid)->orderBy('id')->get();

        $this->assertCount(3, $rows);
        // Baris pertama: prev NULL (permulaan rantai masjid itu)
        $this->assertNull($rows[0]->prev_hash);
        // Baris seterusnya MESTI berantai kepada row_hash sebelumnya
        $this->assertSame($rows[0]->row_hash, $rows[1]->prev_hash);
        $this->assertSame($rows[1]->row_hash, $rows[2]->prev_hash);
    }
}
