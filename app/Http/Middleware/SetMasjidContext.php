<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Ikat masjid_id pengguna semasa ke container — digunakan oleh trait
 * BelongsToMasjid (skop global) supaya semua query tertapis kepada
 * masjid pengguna yang log masuk.
 */
class SetMasjidContext
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user) {
            app()->instance('current.masjid_id', (int) $user->masjid_id);
            app()->instance('current.user_id', (int) $user->id);
        }

        return $next($request);
    }
}
