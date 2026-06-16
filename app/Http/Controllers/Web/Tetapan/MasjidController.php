<?php

namespace App\Http\Controllers\Web\Tetapan;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tetapan\MasjidBaruRequest;
use App\Http\Requests\Tetapan\MasjidRequest;
use App\Models\AppUser;
use App\Models\Masjid;
use App\Services\Security\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/** Info Masjid (replika paparMasjid.php) — papar profil; edit oleh admin sahaja. */
class MasjidController extends Controller
{
    public function __construct(private AuditTrailService $audit)
    {
    }

    public function index(): View
    {
        return view('tetapan.masjid', [
            'masjid'   => Masjid::findOrFail((int) app('current.masjid_id')),
            'kategori' => \App\Http\Requests\Tetapan\MasjidRequest::KATEGORI,
        ]);
    }

    /** Phase B — borang Masjid Baru (admin sahaja). */
    public function baru(): View
    {
        return view('tetapan.masjid-baru', ['kategori' => MasjidRequest::KATEGORI]);
    }

    /**
     * Phase B — cipta masjid baharu + login bendahari pertama dalam SATU transaksi.
     * Tiada penyemaian COA/bank/baki awal — bendahari baharu sediakan sendiri via Wizard.
     */
    public function ciptaMasjid(MasjidBaruRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $bendahari = DB::transaction(function () use ($data) {
            $masjid = Masjid::create([
                'nama'     => $data['nama'],
                'kategori' => $data['kategori'] ?? null,
                'alamat'   => $data['alamat'] ?? null,
                'poskod'   => $data['poskod'] ?? null,
                'bandar'   => $data['bandar'] ?? null,
                'daerah'   => $data['daerah'] ?? null,
                'negeri'   => $data['negeri'] ?? null,
                'telefon'  => $data['telefon'] ?? null,
                'emel'     => $data['emel'] ?? null,
            ]);

            $user = AppUser::create([
                'masjid_id'     => $masjid->id,
                'login'         => $data['login'],
                'nama_penuh'    => $data['nama_penuh'],
                'role'          => UserRole::BENDAHARI->value,
                'password_hash' => Hash::make($data['kata_laluan']),
                'is_active'     => 1,
            ]);

            // Jejak audit di bawah masjid BAHARU (rekod permulaan jejaknya).
            $this->audit->log('CREATE', 'masjid', null, ['nama' => $masjid->nama], $masjid->id, null, $masjid->id);
            $this->audit->log('CREATE', 'app_user', null,
                ['login' => $user->login, 'role' => 'bendahari'], $user->id, null, $masjid->id);

            return $user;
        });

        return redirect()->route('tetapan.pengguna')->with('success',
            "Masjid '".$bendahari->masjid->nama."' dicipta dengan bendahari '".$bendahari->login.
            "'. Sila minta bendahari log masuk & sediakan COA/bank/baki awal melalui Wizard Setup.");
    }

    public function kemaskini(MasjidRequest $request): RedirectResponse
    {
        $masjid = Masjid::findOrFail((int) app('current.masjid_id'));

        $sebelum = $masjid->only(array_keys($request->validated()));
        $masjid->update($request->validated());
        $this->audit->log('UPDATE', 'masjid', $sebelum, $request->validated(), $masjid->id);
        Masjid::lupakanSemasa(); // butiran baharu terus tampak di semua view

        return redirect()->route('tetapan.masjid')->with('success', 'Info masjid berjaya dikemaskini');
    }
}
