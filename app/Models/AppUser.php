<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppUser extends Authenticatable
{
    protected $table = 'app_user';
    const UPDATED_AT = null;
    protected $guarded = [];
    protected $hidden = ['password_hash'];
    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'role' => UserRole::class,
    ];

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function masjid(): BelongsTo
    {
        return $this->belongsTo(Masjid::class, 'masjid_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function bolehTulis(): bool
    {
        return in_array($this->role, [UserRole::ADMIN, UserRole::BENDAHARI], true);
    }
}
