<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class VerifyCompletenessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_command_jalan_dan_lapor_advisory(): void
    {
        $this->artisan('sppkms:verify-completeness')
            ->assertSuccessful() // advisory — sentiasa exit 0
            ->expectsOutputToContain('KUTIPAN')
            ->expectsOutputToContain('nama_pemberi')
            ->expectsOutputToContain('PEMBAYARAN')
            ->expectsOutputToContain('pemohon');
    }
}
