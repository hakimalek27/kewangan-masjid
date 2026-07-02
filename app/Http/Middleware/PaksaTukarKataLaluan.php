<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Jika pengguna log masuk mempunyai flag must_change_password (kredensial lalai/reset),
 * paksa ke halaman Tukar Kata Laluan sebelum sebarang akses lain — kecuali laluan
 * tukar kata laluan itu sendiri & logout.
 */
class PaksaTukarKataLaluan
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            $laluanDibenarkan = [
                'tetapan.katalaluan',
                'tetapan.katalaluan.kemaskini',
                'logout',
            ];

            if (! $request->routeIs($laluanDibenarkan)) {
                return redirect()->route('tetapan.katalaluan')
                    ->with('amaran', 'Anda mesti menukar kata laluan lalai sebelum meneruskan.');
            }
        }

        return $next($request);
    }
}
