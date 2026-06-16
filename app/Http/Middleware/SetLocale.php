<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Fasa 9 — UX: bahasa antaramuka daripada sesi ('lang': ms|en, lalai ms).
 * Terjemahan melalui lang/ms.json & lang/en.json — kunci ialah teks BM,
 * jadi kunci yang tiada terjemahan kekal dipaparkan dalam BM.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $lang = $request->session()->get('lang', 'ms');
        app()->setLocale(in_array($lang, ['ms', 'en'], true) ? $lang : 'ms');

        return $next($request);
    }
}
