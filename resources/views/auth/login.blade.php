<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Log Masuk') }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page d-flex align-items-center justify-content-center min-vh-100">
    <div class="card shadow-lg border-0 login-card">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <i class="bi bi-moon-stars-fill display-5 text-primary"></i>
                <h1 class="h4 mt-3 mb-1">{{ config('app.name') }}</h1>
                <p class="text-muted small mb-0">{{ __('Sistem Pengurusan Kewangan Masjid') }}</p>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}" autocomplete="off">
                @csrf
                {{-- Honeypot — mesti kekal kosong (anti-bot) --}}
                <input type="text" name="hp_field" value="" tabindex="-1" autocomplete="off" class="hp-field" aria-hidden="true">

                <div class="mb-3">
                    <label class="form-label" for="login">{{ __('Nama Log Masuk') }}</label>
                    <input type="text" class="form-control form-control-lg" id="login" name="login"
                           value="{{ old('login') }}" required autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password">{{ __('Kata Laluan') }}</label>
                    <input type="password" class="form-control form-control-lg" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">{{ __('Log Masuk') }}</button>
            </form>
        </div>
    </div>
</body>
</html>
