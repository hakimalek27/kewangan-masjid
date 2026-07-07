<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1d3557">
    <title>@yield('title', __('Dashboard')) — {{ config('app.name') }}</title>
    {{-- Fasa 9 — PWA --}}
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
{{-- Fasa 9 UX — pulihkan mod gelap & saiz font dari localStorage (elak kerdipan) --}}
<script>
    (function () {
        try {
            if (localStorage.getItem('spkm-theme') === 'dark') document.body.classList.add('dark-mode');
            var f = localStorage.getItem('spkm-font');
            if (f === 'sm' || f === 'lg') document.body.classList.add('font-' + f);
        } catch (e) {}
    })();
    function spkmToggleTheme() {
        var gelap = document.body.classList.toggle('dark-mode');
        try { localStorage.setItem('spkm-theme', gelap ? 'dark' : 'light'); } catch (e) {}
    }
    function spkmFont(saiz) {
        document.body.classList.remove('font-sm', 'font-lg');
        if (saiz === 'sm' || saiz === 'lg') document.body.classList.add('font-' + saiz);
        try { localStorage.setItem('spkm-font', saiz); } catch (e) {}
    }
</script>

<div class="d-flex" id="wrapper">

    {{-- ===== Sidebar ===== --}}
    <nav class="sidebar d-flex flex-column flex-shrink-0" id="sidebar">
        <a href="{{ route('dashboard') }}" class="sidebar-brand text-decoration-none text-center py-3 d-block">
            @if ($masjidSemasa?->logoUrl())
                <span class="d-inline-block bg-white rounded p-1">
                    <img src="{{ $masjidSemasa->logoUrl() }}" alt="{{ __('Logo') }}" style="max-height:46px; max-width:140px;">
                </span>
            @else
                <i class="bi bi-moon-stars-fill fs-4"></i>
            @endif
            <div class="fw-bold small mt-1">{{ $masjidSemasa?->nama ?? config('app.name') }}</div>
            <div class="sidebar-subtitle">{{ __('Sistem Pengurusan Kewangan Masjid') }}</div>
        </a>
        <hr class="sidebar-divider my-0">
        <div class="sidebar-menu flex-grow-1 overflow-auto">
            @php
                // Badge Kotak Draf AI — bilangan draf menunggu pengesahan bendahari
                try {
                    $drafAiBelum = auth()->check()
                        ? \App\Models\TxnDraft::where('status', 'PENDING_REVIEW')->count()
                        : 0;
                } catch (\Throwable) { $drafAiBelum = 0; }
                // Fasa 9 — badge Kelulusan maker-checker
                try {
                    $kelulusanBelum = auth()->check()
                        ? \App\Models\Approval::where('status', 'PENDING')->count()
                        : 0;
                } catch (\Throwable) { $kelulusanBelum = 0; }
            @endphp
            @php
                $peranan = auth()->user()?->role?->value;
                $viewerRoutes = ['penyata.bulanan', 'penyata.bank', 'penyata.tahunan'];
                // Item menu DIHADKAN ikut peranan (route sistem/khas). Item lain → semua boleh lihat.
                $menuHad = [
                    'admin.pemantauan'  => ['admin'],
                    'admin.audit'       => ['admin', 'juruaudit'], // juruaudit baca jejak audit
                    'admin.ralat'       => ['admin'],
                    'admin.keselamatan' => ['admin'],
                    'admin.backup'      => ['admin'],
                    'admin.dualwrite'   => ['admin'],
                    'admin.semakpenyata' => ['admin'],
                    'tetapan.ai'        => ['admin'],
                    'tetapan.api'       => ['admin'],
                    'tetapan.pengguna'  => ['admin', 'bendahari', 'pentadbir'],
                ];
                // Superadmin (admin) = akses PENUH semua tenant → nampak menu penuh
                // (kewangan + sistem). Konteks masjid ikut pemilih masjid semasa.
            @endphp
            @foreach (config('spkm.menu') as $i => $group)
                @php
                    $items = $group['items'];
                    // Admin: pautan Konsol Sistem di puncak kumpulan Dashboard.
                    if ($peranan === 'admin' && ($group['label'] ?? '') === 'Dashboard') {
                        $items = array_merge([['Konsol Sistem', 'sistem.console']], $items);
                    }
                    if ($peranan === 'viewer') {
                        $items = array_filter($items, fn ($it) => in_array($it[1], $viewerRoutes, true));
                    } else {
                        $items = array_filter($items, function ($it) use ($peranan, $menuHad) {
                            if (isset($menuHad[$it[1]]) && ! in_array($peranan, $menuHad[$it[1]], true)) {
                                return false;
                            }
                            return true;
                        });
                    }
                    $items = array_values($items);
                @endphp
                @continue(empty($items))
                @if (count($items) === 1)
                    <a class="sidebar-link {{ request()->routeIs($items[0][1]) ? 'active' : '' }}"
                       href="{{ route($items[0][1]) }}">
                        <i class="bi {{ $group['icon'] }} me-2"></i>{{ __($items[0][0]) }}
                    </a>
                @else
                    @php
                        $open = collect($items)->contains(fn ($it) => request()->routeIs($it[1]));
                    @endphp
                    <a class="sidebar-link d-flex justify-content-between align-items-center {{ $open ? '' : 'collapsed' }}"
                       data-bs-toggle="collapse" href="#menu-{{ $i }}" role="button"
                       aria-expanded="{{ $open ? 'true' : 'false' }}">
                        <span><i class="bi {{ $group['icon'] }} me-2"></i>{{ __($group['label']) }}</span>
                        <i class="bi bi-chevron-down small"></i>
                    </a>
                    <div class="collapse {{ $open ? 'show' : '' }}" id="menu-{{ $i }}">
                        @foreach ($items as [$label, $routeName])
                            <a class="sidebar-sublink {{ request()->routeIs($routeName) ? 'active' : '' }}"
                               href="{{ route($routeName) }}">{{ __($label) }}
                                @if ($routeName === 'draf.index' && $drafAiBelum > 0)
                                    <span class="badge text-bg-danger ms-1">{{ $drafAiBelum }}</span>
                                @endif
                                @if ($routeName === 'kelulusan.index' && $kelulusanBelum > 0)
                                    <span class="badge text-bg-danger ms-1">{{ $kelulusanBelum }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </div>
        <div class="p-3 small text-center sidebar-footer">
            &copy; {{ date('Y') }} {{ $masjidSemasa?->nama ?? '' }}
        </div>
    </nav>

    {{-- ===== Kandungan ===== --}}
    <div class="flex-grow-1 d-flex flex-column min-vh-100" id="content">
        <header class="topbar navbar navbar-expand bg-white shadow-sm px-3">
            <button class="btn btn-link text-secondary d-lg-none" id="sidebarToggle" type="button">
                <i class="bi bi-list fs-4"></i>
            </button>

            {{-- Fasa 9 UX — carian global --}}
            @auth
                <form method="GET" action="{{ route('carian') }}" class="d-none d-md-flex ms-2" role="search" style="max-width:330px">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="search" name="q" class="form-control" minlength="2"
                               placeholder="{{ __('Cari resit / baucer / aset...') }}" value="{{ request('q') }}">
                    </div>
                </form>
            @endauth

            <div class="ms-auto d-flex align-items-center gap-1">
                {{-- Fasa 9 UX — saiz font A- / A / A+ --}}
                <div class="btn-group btn-group-sm d-none d-md-inline-flex" role="group" aria-label="{{ __('Saiz font') }}">
                    <button type="button" class="btn btn-outline-secondary" onclick="spkmFont('sm')" title="{{ __('Font kecil') }}">A-</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="spkmFont('')" title="{{ __('Font biasa') }}">A</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="spkmFont('lg')" title="{{ __('Font besar') }}">A+</button>
                </div>

                {{-- Fasa 9 UX — mod gelap --}}
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="spkmToggleTheme()" title="{{ __('Mod gelap / cerah') }}">
                    <i class="bi bi-moon-stars"></i>
                </button>

                {{-- Fasa 9 UX — suis bahasa BM | EN --}}
                @auth
                    <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Bahasa') }}">
                        <a href="{{ route('bahasa', 'ms') }}" class="btn btn-outline-secondary {{ app()->getLocale() === 'ms' ? 'active' : '' }}">BM</a>
                        <a href="{{ route('bahasa', 'en') }}" class="btn btn-outline-secondary {{ app()->getLocale() === 'en' ? 'active' : '' }}">EN</a>
                    </div>

                    {{-- Fasa 9 UX — loceng notifikasi (10 terkini) --}}
                    @php
                        $notifItems = collect();
                        try {
                            $notifItems = $notifItems
                                ->merge(\App\Models\SecurityEvent::whereIn('severity', ['HIGH', 'CRITICAL'])
                                    ->orderByDesc('id')->limit(5)->get()
                                    ->map(fn ($e) => (object) ['ikon' => 'bi-shield-exclamation', 'warna' => 'danger',
                                        'teks' => '['.$e->severity.'] '.$e->jenis.': '.\Illuminate\Support\Str::limit($e->detail, 60),
                                        'url' => route('admin.keselamatan'), 'masa' => $e->created_at]))
                                ->merge(\App\Models\TxnDraft::where('status', 'PENDING_REVIEW')
                                    ->orderByDesc('id')->limit(5)->get()
                                    ->map(fn ($d) => (object) ['ikon' => 'bi-robot', 'warna' => 'primary',
                                        'teks' => __('Draf AI').' #'.$d->id.' '.__('menunggu pengesahan').' (RM '.number_format((float) $d->jumlah, 2).')',
                                        'url' => route('draf.lihat', $d->id), 'masa' => $d->created_at]))
                                ->merge(\App\Models\Approval::where('status', 'PENDING')
                                    ->orderByDesc('id')->limit(5)->get()
                                    ->map(fn ($a) => (object) ['ikon' => 'bi-person-check', 'warna' => 'warning',
                                        'teks' => __('Kelulusan').' #'.$a->id.' '.__('menunggu').' (RM '.number_format((float) $a->amaun, 2).')',
                                        'url' => route('kelulusan.index'), 'masa' => $a->made_at]))
                                ->merge(\App\Models\FdInvestment::whereIn('status', ['AKTIF', 'DIPERBAHARUI'])
                                    ->whereNotNull('maturity_date')
                                    ->whereBetween('maturity_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
                                    ->orderBy('maturity_date')->limit(5)->get()
                                    ->map(fn ($fd) => (object) ['ikon' => 'bi-piggy-bank', 'warna' => 'info',
                                        'teks' => 'FD '.$fd->institusi.' '.__('matang').' '.$fd->maturity_date?->format('d/m/Y').' (RM '.number_format((float) $fd->jumlah, 2).')',
                                        'url' => route('fd.senarai'), 'masa' => now()]))
                                ->merge(collect(app(\App\Services\Lanjutan\BakiRendahService::class)->senaraiRendah())
                                    ->map(fn ($b) => (object) ['ikon' => 'bi-cash-stack', 'warna' => 'warning',
                                        'teks' => __('Baki rendah:').' '.$b->nama.' RM '.number_format((float) $b->baki, 2),
                                        'url' => route('bank.baki'), 'masa' => now()]))
                                ->sortByDesc('masa')->take(10)->values();
                        } catch (\Throwable) { $notifItems = collect(); }
                    @endphp
                    <div class="dropdown">
                        <a class="btn btn-sm btn-outline-secondary position-relative" href="#" role="button" data-bs-toggle="dropdown" title="{{ __('Notifikasi') }}">
                            <i class="bi bi-bell"></i>
                            @if ($notifItems->isNotEmpty())
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger" style="font-size:.6rem">
                                    {{ $notifItems->count() }}
                                </span>
                            @endif
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow" style="width:340px; max-height:420px; overflow-y:auto">
                            <li><h6 class="dropdown-header">{{ __('Notifikasi Terkini') }}</h6></li>
                            @forelse ($notifItems as $n)
                                <li>
                                    <a class="dropdown-item small text-wrap" href="{{ $n->url }}">
                                        <i class="bi {{ $n->ikon }} text-{{ $n->warna }} me-2"></i>{{ $n->teks }}
                                    </a>
                                </li>
                            @empty
                                <li><span class="dropdown-item-text small text-muted">{{ __('Tiada notifikasi baharu.') }} 🎉</span></li>
                            @endforelse
                        </ul>
                    </div>
                @endauth

                @auth
                    @if (($masjidSenarai ?? collect())->count() > 1)
                        <div class="dropdown">
                            <a class="btn btn-sm btn-outline-secondary dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" title="{{ __('Tukar Masjid') }}">
                                <i class="bi bi-building me-1"></i>{{ \Illuminate\Support\Str::limit($masjidSemasa?->nama ?? __('Masjid'), 16) }}
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow" style="max-height:360px; overflow-y:auto; min-width:240px">
                                <li><h6 class="dropdown-header">{{ __('Tukar Masjid Aktif') }}</h6></li>
                                @foreach ($masjidSenarai as $m)
                                    <li>
                                        <form method="POST" action="{{ route('masjid.tukar') }}" class="m-0">@csrf
                                            <input type="hidden" name="masjid_id" value="{{ $m->id }}">
                                            <button type="submit" class="dropdown-item {{ (int) ($masjidSemasa?->id) === (int) $m->id ? 'active' : '' }}">
                                                @if ((int) ($masjidSemasa?->id) === (int) $m->id)<i class="bi bi-check2 me-1"></i>@endif{{ $m->nama }}
                                            </button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endauth

                <div class="dropdown">
                    <a class="nav-link dropdown-toggle text-dark" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i>{{ auth()->user()?->nama_penuh ?? auth()->user()?->login }}
                        <span class="badge text-bg-secondary ms-1">{{ auth()->user()?->role?->label() }}</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('tetapan.katalaluan') }}">
                            <i class="bi bi-key me-2"></i>{{ __('Tukar Kata Laluan') }}</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ asset('GP.pdf') }}" target="_blank" rel="noopener">
                            <i class="bi bi-journal-text me-2"></i>{{ __('Panduan Kewangan') }}</a></li>
                        <li><a class="dropdown-item" href="https://wa.me/60123847167" target="_blank" rel="noopener">
                            <i class="bi bi-whatsapp me-2"></i>{{ __('Hubungi Kami') }}</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
                            <i class="bi bi-box-arrow-right me-2"></i>{{ __('Log Keluar') }}</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="container-fluid p-4 flex-grow-1">
            <h1 class="h4 mb-4 text-gray-800">@yield('title')</h1>

            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="bg-white text-center small text-muted py-3 mt-auto shadow-sm">
            {{ config('app.name') }} — {{ __('dibina untuk ketepatan audit. Semua laporan dikira terus daripada jurnal.') }}
        </footer>
    </div>
</div>

{{-- Modal Log Keluar (replika tingkah laku SPPKMS) --}}
<div class="modal fade" id="logoutModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Sedia untuk log keluar?') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">{{ __('Pilih "Ya, Log Keluar" jika anda sudah selesai.') }}</div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-primary" type="submit">{{ __('Ya, Log Keluar') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>

@stack('scripts')
</body>
</html>
