<?php

namespace App\Http\Controllers\Web\Tetapan;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tetapan\PenggunaRequest;
use App\Models\AppUser;
use App\Services\Security\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/** Pengurusan Pengguna — admin sahaja. Senarai/tambah/edit pengguna masjid semasa. */
class PenggunaController extends Controller
{
    public function __construct(private AuditTrailService $audit)
    {
    }

    public function index(): View
    {
        return view('tetapan.pengguna-index', [
            'senarai' => AppUser::where('masjid_id', (int) app('current.masjid_id'))
                ->orderBy('login')->get(),
            'roles'   => UserRole::cases(),
        ]);
    }

    public function simpan(PenggunaRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = AppUser::create([
            'masjid_id'     => (int) app('current.masjid_id'),
            'login'         => $data['login'],
            'nama_penuh'    => $data['nama_penuh'],
            'role'          => $data['role'],
            'password_hash' => Hash::make($data['kata_laluan']),
            'is_active'     => $request->boolean('is_active', true),
        ]);

        $this->audit->log('CREATE', 'app_user', null,
            ['login' => $data['login'], 'nama_penuh' => $data['nama_penuh'], 'role' => $data['role']], $user->id);

        return redirect()->route('tetapan.pengguna')->with('success', 'Pengguna berjaya ditambah');
    }

    public function edit(AppUser $pengguna): View
    {
        $this->pastikanMasjidSama($pengguna);

        return view('tetapan.pengguna-edit', [
            'pengguna' => $pengguna,
            'roles'    => UserRole::cases(),
        ]);
    }

    public function kemaskini(PenggunaRequest $request, AppUser $pengguna): RedirectResponse
    {
        $this->pastikanMasjidSama($pengguna);
        $data = $request->validated();

        $sebelum = $pengguna->only(['login', 'nama_penuh', 'role', 'is_active']);
        $sebelum['role'] = $sebelum['role']->value;

        $kemaskini = [
            'login'      => $data['login'],
            'nama_penuh' => $data['nama_penuh'],
            'role'       => $data['role'],
            'is_active'  => $request->boolean('is_active'),
        ];
        if (!empty($data['kata_laluan'])) {
            $kemaskini['password_hash'] = Hash::make($data['kata_laluan']);
        }

        $pengguna->update($kemaskini);

        $selepas = ['login' => $data['login'], 'nama_penuh' => $data['nama_penuh'],
            'role' => $data['role'], 'is_active' => $request->boolean('is_active'),
            'reset_kata_laluan' => !empty($data['kata_laluan'])];
        $this->audit->log('UPDATE', 'app_user', $sebelum, $selepas, $pengguna->id);

        return redirect()->route('tetapan.pengguna')->with('success', 'Pengguna berjaya dikemaskini');
    }

    /** AppUser tiada skop global masjid — sekat akses rentas masjid secara manual. */
    private function pastikanMasjidSama(AppUser $pengguna): void
    {
        abort_unless((int) $pengguna->masjid_id === (int) app('current.masjid_id'), 404);
    }
}
