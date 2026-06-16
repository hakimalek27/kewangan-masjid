<?php

namespace Database\Seeders;

use App\Models\AppUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AppUserSeeder extends Seeder
{
    public function run(): void
    {
        $masjidId = config('sppkms.masjid_id');

        $users = [
            ['login' => 'admin',       'nama_penuh' => 'Admin Sistem',           'role' => 'admin',     'password' => 'admin12345'],
            ['login' => 'malmutaqqin', 'nama_penuh' => 'Bendahari Al-Muttaqin',  'role' => 'bendahari', 'password' => 'alm12345'],
        ];

        foreach ($users as $u) {
            AppUser::updateOrCreate(
                ['login' => $u['login']],
                [
                    'masjid_id'     => $masjidId,
                    'nama_penuh'    => $u['nama_penuh'],
                    'role'          => $u['role'],
                    'password_hash' => Hash::make($u['password']),
                    'is_active'     => 1,
                ]
            );
        }
    }
}
