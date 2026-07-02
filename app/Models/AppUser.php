<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class AppUser extends Authenticatable
{
    protected $table = 'app_user';
    const UPDATED_AT = null;
    protected $guarded = [];
    protected $hidden = ['password_hash'];
    protected $casts = [
        'is_active' => 'boolean',
        'must_change_password' => 'boolean',
        'last_login_at' => 'datetime',
        'role' => UserRole::class,
    ];

    /** Cache per-permintaan untuk accessibleMasjidIds — elak query berulang (SetMasjidContext + composer). */
    private ?array $accessibleCache = null;

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function masjid(): BelongsTo
    {
        return $this->belongsTo(Masjid::class, 'masjid_id');
    }

    /** Masjid TAMBAHAN yang pengguna ini ditugaskan lihat (pemerhati) — pivot user_masjid. */
    public function masjids(): BelongsToMany
    {
        return $this->belongsToMany(Masjid::class, 'user_masjid', 'user_id', 'masjid_id');
    }

    /**
     * ID masjid yang BOLEH DICAPAI pengguna ini:
     *  - admin     → SEMUA masjid
     *  - pemerhati → masjid asal (home) + masjid ditugaskan (pivot)
     *  - lain-lain → masjid asal sahaja
     */
    public function accessibleMasjidIds(): array
    {
        if ($this->accessibleCache !== null) {
            return $this->accessibleCache;
        }

        $ids = match (true) {
            $this->isAdmin()                 => Masjid::query()->pluck('id'),
            $this->role === UserRole::VIEWER => collect([$this->masjid_id])->merge($this->masjids->pluck('id')),
            default                          => collect([$this->masjid_id]),
        };

        return $this->accessibleCache = $ids->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    public function canAccessMasjid(int $masjidId): bool
    {
        return in_array($masjidId, $this->accessibleMasjidIds(), true);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    /** Boleh TULIS kewangan (maker) — BENDAHARI sahaja. Admin = sistem (tiada tulis kewangan). */
    public function bolehTulis(): bool
    {
        return $this->role === UserRole::BENDAHARI;
    }

    /** Boleh urus TETAPAN masjid (bank/COA/resit/tandatangan/kawalan/tutup-tahun) — bendahari & Pentadbir Masjid. */
    public function bolehUrusMasjid(): bool
    {
        return in_array($this->role, [UserRole::BENDAHARI, UserRole::PENTADBIR], true);
    }

    /** Boleh tulis daftar BUKAN-kewangan (sewa, peti besi, info masjid) — bendahari, setiausaha & pentadbir. */
    public function bolehTulisDaftar(): bool
    {
        return in_array($this->role, [UserRole::BENDAHARI, UserRole::SETIAUSAHA, UserRole::PENTADBIR], true);
    }
}
