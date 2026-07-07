<?php

namespace App\Http\Middleware;

use App\Services\Security\SecurityEventService;
use Closure;
use Illuminate\Http\Request;

/**
 * Sekat laluan ikut peranan: Route::middleware('role:admin,bendahari').
 * Juruaudit/viewer = baca sahaja (sekat semua kaedah tulis secara automatik).
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();
        abort_unless($user, 403);

        $role = $user->role->value;

        // Superadmin (admin) lepas SEMUA pagar peranan — akses penuh baca+tulis
        // semua tenant (keputusan reka bentuk). Tulis melalui pagar bukan-admin
        // direkodkan sebagai ADMIN_OVERRIDE untuk jejak keselamatan.
        if ($role === \App\Enums\UserRole::ADMIN->value) {
            if (!in_array('admin', $roles, true) && !$request->isMethodSafe()) {
                app(SecurityEventService::class)->log(
                    'ADMIN_OVERRIDE',
                    'Admin menulis melalui pagar ['.implode(',', $roles).'] di '.$request->path(),
                    'LOW'
                );
            }

            return $next($request);
        }

        if (!in_array($role, $roles, true)) {
            app(SecurityEventService::class)->log(
                'PERMISSION_DENIED',
                "Peranan {$role} cuba akses ".$request->path(),
                'MEDIUM'
            );
            abort(403, 'Anda tiada kebenaran untuk halaman ini.');
        }

        // Peranan baca-sahaja tidak boleh menulis walau lulus senarai peranan
        if (in_array($role, ['juruaudit', 'viewer'], true) && !$request->isMethodSafe()) {
            abort(403, 'Akses baca sahaja.');
        }

        return $next($request);
    }
}
