<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN      = 'admin';
    case PENTADBIR  = 'pentadbir';   // Pentadbir Masjid — pentadbir SATU masjid (tetapan + pengguna), bukan rekod kewangan
    case BENDAHARI  = 'bendahari';
    case PENGERUSI  = 'pengerusi';
    case SETIAUSAHA = 'setiausaha';
    case JURUAUDIT  = 'juruaudit';
    case VIEWER     = 'viewer';

    /**
     * Peranan yang DITAWARKAN dalam model SaaS (borang cipta/edit pengguna):
     *   admin (provider), bendahari (tenant — kewangan + tetapan penuh),
     *   juruaudit (baca penuh), viewer (JAWI/MAIWP — laporan sahaja).
     * PENTADBIR/PENGERUSI/SETIAUSAHA = LEGASI: akaun sedia ada kekal berfungsi,
     * tetapi tidak lagi ditawarkan untuk akaun baharu (keputusan 8 Jul 2026).
     */
    public static function ditawarkan(): array
    {
        return [self::ADMIN, self::BENDAHARI, self::JURUAUDIT, self::VIEWER];
    }

    public function label(): string
    {
        return match ($this) {
            self::ADMIN      => 'Admin Sistem',
            self::PENTADBIR  => 'Pentadbir Masjid',
            self::BENDAHARI  => 'Bendahari',
            self::PENGERUSI  => 'Pengerusi',
            self::SETIAUSAHA => 'Setiausaha',
            self::JURUAUDIT  => 'Juruaudit',
            self::VIEWER     => 'Pemerhati',
        };
    }
}
