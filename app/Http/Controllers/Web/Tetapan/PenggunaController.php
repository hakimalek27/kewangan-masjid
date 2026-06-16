<?php

namespace App\Http\Controllers\Web\Tetapan;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tetapan\PenggunaRequest;
use App\Models\AppUser;
use App\Models\Masjid;
use App\Services\Security\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Pengurusan Pengguna — admin sahaja (route role:admin). Urus pengguna MERENTAS
 * semua masjid: pilih masjid asal semasa cipta; pemerhati boleh ditugaskan
 * beberapa masjid (pivot user_masjid).
 */
class PenggunaController extends Controller
{
    public function __construct(private AuditTrailService $audit)
    {
    }

    public function index(): View
    {
        return view('tetapan.pengguna-index', [
            'senarai' => AppUser::with('masjid')->orderBy('masjid_id')->orderBy('login')->get(),
            'roles'   => UserRole::cases(),
            'masjids' => Masjid::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function simpan(PenggunaRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = AppUser::create([
            'masjid_id'     => (int) $data['masjid_id'],
            'login'         => $data['login'],
            'nama_penuh'    => $data['nama_penuh'],
            'role'          => $data['role'],
            'password_hash' => Hash::make($data['kata_laluan']),
            'is_active'     => $request->boolean('is_active', true),
        ]);

        $this->segerakTugasan($user, $data['role'], $request->input('masjid_ids', []));

        // Jejak audit dilog di bawah masjid SASARAN (kelihatan dlm jejak masjid berkenaan), aktor = admin semasa.
        $this->audit->log('CREATE', 'app_user', null,
            ['login' => $data['login'], 'masjid_id' => $data['masjid_id'], 'role' => $data['role']],
            $user->id, null, (int) $data['masjid_id']);

        return redirect()->route('tetapan.pengguna')->with('success', 'Pengguna berjaya ditambah');
    }

    public function edit(AppUser $pengguna): View
    {
        return view('tetapan.pengguna-edit', [
            'pengguna' => $pengguna,
            'roles'    => UserRole::cases(),
            'masjids'  => Masjid::orderBy('nama')->get(['id', 'nama']),
            'ditugas'  => $pengguna->masjids->pluck('id')->all(),
        ]);
    }

    public function kemaskini(PenggunaRequest $request, AppUser $pengguna): RedirectResponse
    {
        $data = $request->validated();

        $sebelum = $pengguna->only(['login', 'nama_penuh', 'role', 'is_active', 'masjid_id']);
        $sebelum['role'] = $sebelum['role']->value;

        $kemaskini = [
            'masjid_id'  => (int) $data['masjid_id'],
            'login'      => $data['login'],
            'nama_penuh' => $data['nama_penuh'],
            'role'       => $data['role'],
            'is_active'  => $request->boolean('is_active'),
        ];
        if (! empty($data['kata_laluan'])) {
            $kemaskini['password_hash'] = Hash::make($data['kata_laluan']);
        }

        $pengguna->update($kemaskini);
        $this->segerakTugasan($pengguna, $data['role'], $request->input('masjid_ids', []));

        $selepas = ['login' => $data['login'], 'nama_penuh' => $data['nama_penuh'], 'masjid_id' => (int) $data['masjid_id'],
            'role' => $data['role'], 'is_active' => $request->boolean('is_active'),
            'reset_kata_laluan' => ! empty($data['kata_laluan'])];
        $this->audit->log('UPDATE', 'app_user', $sebelum, $selepas, $pengguna->id, null, (int) $data['masjid_id']);

        return redirect()->route('tetapan.pengguna')->with('success', 'Pengguna berjaya dikemaskini');
    }

    /** Segerak tugasan masjid pemerhati (pivot user_masjid); bukan pemerhati → kosongkan. */
    private function segerakTugasan(AppUser $user, string $role, array $masjidIds): void
    {
        $user->masjids()->sync($role === UserRole::VIEWER->value ? array_map('intval', $masjidIds) : []);
    }
}
