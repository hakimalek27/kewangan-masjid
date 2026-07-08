<?php

namespace Tests\Concerns;

use App\Models\Coa;

trait MasjidContext
{
    protected function aktifkanKonteksMasjid(): void
    {
        app()->instance('current.masjid_id', (int) config('spkm.masjid_id'));
        app()->instance('current.user_id', 1);
    }

    /**
     * Simulasi superadmin "Masuk" sesebuah masjid (mod dalam-tenant) melalui penukar
     * masjid sebenar → menetapkan selected_masjid_id dalam sesi (kekal utk permintaan
     * seterusnya dlm ujian yg sama). Tanpa ini, admin berada dalam MOD PENYEDIA dan
     * laluan kewangan tenant dialih ke Konsol (RestrictAdminProvider).
     */
    protected function adminMasuk(\App\Models\AppUser $admin, ?int $masjidId = null): void
    {
        $this->actingAs($admin)->post(route('masjid.tukar'), [
            'masjid_id' => $masjidId ?? (int) config('spkm.masjid_id'),
        ]);
    }

    protected function coaId(string $kod): int
    {
        return (int) Coa::withoutMasjidScope()
            ->where('masjid_id', config('spkm.masjid_id'))
            ->where('kod', $kod)
            ->value('id');
    }

    /** Baki bersih (Dr − Cr) sesuatu COA daripada jurnal POSTED. */
    protected function bakiCoa(string $kod): string
    {
        $baki = \DB::table('journal_entry as je')
            ->join('journal_voucher as jv', 'jv.id', '=', 'je.voucher_id')
            ->where('jv.status', 'POSTED')
            ->where('je.coa_id', $this->coaId($kod))
            ->selectRaw('COALESCE(SUM(je.debit - je.kredit),0) as baki')
            ->value('baki');

        return number_format((float) $baki, 2, '.', '');
    }
}
