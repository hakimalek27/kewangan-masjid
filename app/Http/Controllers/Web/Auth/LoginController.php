<?php

namespace App\Http\Controllers\Web\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AppUser;
use App\Models\LoginAttempt;
use App\Services\Security\SecurityEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request, SecurityEventService $security): RedirectResponse
    {
        $request->validate([
            'login'    => 'required|string|max:60',
            'password' => 'required|string',
        ]);

        // Honeypot — bot mengisi medan tersembunyi ini; manusia tidak
        if ($request->filled('hp_field')) {
            $security->log('LOGIN_FAIL', 'Honeypot dicetus untuk login: '.$request->input('login'), 'HIGH');
            return back()->withErrors(['login' => 'Log masuk gagal.']);
        }

        $throttleKey = 'login:'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $security->log('LOGIN_FAIL', 'Had percubaan login dicapai: '.$request->input('login'), 'HIGH');
            return back()->withErrors(['login' => 'Terlalu banyak percubaan. Cuba lagi sebentar.']);
        }

        $user = AppUser::where('login', $request->input('login'))->where('is_active', 1)->first();
        $ok = $user && Hash::check($request->input('password'), $user->password_hash);

        LoginAttempt::create([
            'login'      => substr($request->input('login'), 0, 60),
            'ip_address' => $request->ip(),
            'success'    => $ok ? 1 : 0,
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        if (!$ok) {
            RateLimiter::hit($throttleKey, 60);
            $security->log('LOGIN_FAIL', 'Kata laluan salah untuk: '.$request->input('login'));
            return back()->withErrors(['login' => 'Nama log masuk atau kata laluan salah.'])->onlyInput('login');
        }

        RateLimiter::clear($throttleKey);
        Auth::login($user);
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        // Pemerhati = penyata sahaja → mendarat terus di Penyata Bulanan (elak lantunan ke dashboard).
        $landing = $user->role === UserRole::VIEWER ? route('penyata.bulanan') : route('dashboard');

        return redirect()->intended($landing);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
