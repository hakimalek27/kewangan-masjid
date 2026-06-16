<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN      = 'admin';
    case BENDAHARI  = 'bendahari';
    case PENGERUSI  = 'pengerusi';
    case SETIAUSAHA = 'setiausaha';
    case JURUAUDIT  = 'juruaudit';
    case VIEWER     = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN      => 'Admin',
            self::BENDAHARI  => 'Bendahari',
            self::PENGERUSI  => 'Pengerusi',
            self::SETIAUSAHA => 'Setiausaha',
            self::JURUAUDIT  => 'Juruaudit',
            self::VIEWER     => 'Pemerhati',
        };
    }
}
