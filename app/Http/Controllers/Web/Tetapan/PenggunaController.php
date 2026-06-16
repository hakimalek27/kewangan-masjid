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
 * Pengurusan Pengguna — ADMIN (semua masjid) + BENDAHARI (masjid SENDIRI sahaja).
 * Bendahari: senarai & cipta/edit diskop ke masjid semasa, TIDAK boleh melantik/
 * mengubah akaun 'admin' (anti-IDOR — AppUser tiada skop BelongsToMasjid).
 * Pemerhati boleh ditugaskan beberapa masjid (pivot user_masjid).
 */
class PenggunaController extends Controller
{
    public function __construct(private AuditTrailService $audit)
    {
    }

    public function index(): View
    {
        $isAdmin = (bool) auth()->user()?->isAdmin();
        $masjidSemasa = (int) app('current.masjid_id');

        return view('tetapan.pengguna-index', [
            'senarai' => AppUser::with('masjid')
                ->when(! $isAdmin, fn ($q) => $q->where('masjid_id', $masjidSemasa))
                ->orderBy('masjid_id')->orderBy('login')->get(),
            'roles'   => $this->peranan($isAdmin),
            'masjids' => $this->masjidPilihan($isAdmin, $masjidSemasa),
            'isAdmin' => $isAdmin,
        ]);
    }

    public function simpan(PenggunaRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $masjidId = $this->masjidSasaran($data);

        $user = AppUser::create([
            'masjid_id'     => $masjidId,
            'login'         => $data['login'],
            'nama_penuh'    => $data['nama_penuh'],
            'role'          => $data['role'],
            'password_hash' => Hash::make($data['kata_laluan']),
            'is_active'     => $request->boolean('is_active', true),
        ]);

        $this->segerakTugasan($user, $data['role'], $request->input('masjid_ids', []));

        // Jejak audit dilog di bawah masjid SASARAN (kelihatan dlm jejak masjid berkenaan).
        $this->audit->log('CREATE', 'app_user', null,
            ['login' => $data['login'], 'masjid_id' => $masjidId, 'role' => $data['role']],
            $user->id, null, $masjidId);

        return redirect()->route('tetapan.pengguna')->with('success', 'Pengguna berjaya ditambah');
    }

    public function edit(AppUser $pengguna): View
    {
        $this->pastikanBolehUrus($pengguna);
        $isAdmin = (bool) auth()->user()?->isAdmin();

        return view('tetapan.pengguna-edit', [
            'pengguna' => $pengguna,
            'roles'    => $this->peranan($isAdmin),
            'masjids'  => $this->masjidPilihan($isAdmin, (int) app('current.masjid_id')),
            'ditugas'  => $pengguna->masjids->pluck('id')->all(),
        ]);
    }

    public function kemaskini(PenggunaRequest $request, AppUser $pengguna): RedirectResponse
    {
        $this->pastikanBolehUrus($pengguna);
        $data = $request->validated();
        $masjidId = $this->masjidSasaran($data);

        $sebelum = $pengguna->only(['login', 'nama_penuh', 'role', 'is_active', 'masjid_id']);
        $sebelum['role'] = $sebelum['role']->value;

        $kemaskini = [
            'masjid_id'  => $masjidId,
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

        $selepas = ['login' => $data['login'], 'nama_penuh' => $data['nama_penuh'], 'masjid_id' => $masjidId,
            'role' => $data['role'], 'is_active' => $request->boolean('is_active'),
            'reset_kata_laluan' => ! empty($data['kata_laluan'])];
        $this->audit->log('UPDATE', 'app_user', $sebelum, $selepas, $pengguna->id, null, $masjidId);

        return redirect()->route('tetapan.pengguna')->with('success', 'Pengguna berjaya dikemaskini');
    }

    /** Peranan boleh dilantik: admin = semua; bukan-admin = tanpa 'admin'. */
    private function peranan(bool $isAdmin): array
    {
        return $isAdmin
            ? UserRole::cases()
            : array_values(array_filter(UserRole::cases(), fn (UserRole $r) => $r !== UserRole::ADMIN));
    }

    /** Senarai masjid dropdown: admin = semua; bukan-admin = masjid sendiri sahaja. */
    private function masjidPilihan(bool $isAdmin, int $masjidSemasa)
    {
        return $isAdmin
            ? Masjid::orderBy('nama')->get(['id', 'nama'])
            : Masjid::where('id', $masjidSemasa)->get(['id', 'nama']);
    }

    /** Masjid sasaran rekod: admin ikut borang; bukan-admin dipaksa ke masjid semasa. */
    private function masjidSasaran(array $data): int
    {
        return auth()->user()?->isAdmin()
            ? (int) $data['masjid_id']
            : (int) app('current.masjid_id');
    }

    /**
     * Bendahari hanya boleh urus pengguna masjid SENDIRI & BUKAN akaun admin.
     * AppUser tiada skop BelongsToMasjid → semakan eksplisit elak IDOR.
     */
    private function pastikanBolehUrus(AppUser $pengguna): void
    {
        if (auth()->user()?->isAdmin()) {
            return;
        }
        abort_unless((int) $pengguna->masjid_id === (int) app('current.masjid_id'), 403);
        abort_if($pengguna->role === UserRole::ADMIN, 403, 'Tiada kebenaran mengurus akaun admin.');
    }

    /** Segerak tugasan masjid pemerhati (pivot user_masjid); bukan pemerhati → kosongkan. */
    private function segerakTugasan(AppUser $user, string $role, array $masjidIds): void
    {
        $user->masjids()->sync($role === UserRole::VIEWER->value ? array_map('intval', $masjidIds) : []);
    }
}
