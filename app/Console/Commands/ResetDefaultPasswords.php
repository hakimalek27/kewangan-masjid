<?php

namespace App\Console\Commands;

use App\Models\AppUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Go-live: set kata laluan rawak + flag must_change_password untuk akaun benih lalai
 * (admin/malmutaqqin) atau senarai login diberi. Kata laluan rawak dipaparkan SEKALI
 * supaya pentadbir boleh sampaikan; login pertama dipaksa tukar (PaksaTukarKataLaluan).
 */
class ResetDefaultPasswords extends Command
{
    protected $signature = 'sppkms:reset-default-passwords {logins?* : Senarai login (lalai: admin, malmutaqqin)}';
    protected $description = 'Set kata laluan rawak + paksa tukar untuk akaun benih lalai';

    public function handle(): int
    {
        $logins = $this->argument('logins') ?: ['admin', 'malmutaqqin'];

        $this->warn('Kata laluan sementara (paparan SEKALI — simpan & sampaikan dengan selamat):');
        foreach ($logins as $login) {
            $user = AppUser::where('login', $login)->first();
            if (! $user) {
                $this->error("  - {$login}: TIADA");
                continue;
            }

            $sementara = Str::password(14);
            $user->update([
                'password_hash' => Hash::make($sementara),
                'must_change_password' => true,
            ]);
            $this->line(sprintf('  - %-16s %s', $login, $sementara));
        }

        $this->info('Selesai. Login pertama akan dipaksa menukar kata laluan.');

        return self::SUCCESS;
    }
}
