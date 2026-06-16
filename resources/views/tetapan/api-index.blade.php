@extends('layouts.app')

@section('title', __('API Awam'))

@section('content')

@if (session('api_secret'))
    <div class="alert alert-warning shadow-sm">
        <h6 class="fw-bold"><i class="bi bi-key me-1"></i>{{ __('Kredensial Klien Baharu — SALIN SEKARANG (ditunjuk SEKALI sahaja)') }}</h6>
        <div class="mb-1"><strong>client_key:</strong> <code>{{ session('api_client_key') }}</code></div>
        <div><strong>secret:</strong> <code>{{ session('api_secret') }}</code></div>
        <small class="text-muted">{{ __('Secret tidak disimpan dalam sistem (hash sahaja). Jika hilang, cipta klien baharu.') }}</small>
    </div>
@endif

<div class="card shadow-sm mb-4">
    <div class="card-header fw-bold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-plug me-1"></i>{{ __('Klien API Berdaftar') }}</span>
        <a href="{{ route('tetapan.api.log') }}" class="btn btn-sm btn-outline-secondary">{{ __('Log Panggilan API') }}</a>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Nama') }}</th>
                    <th>client_key</th>
                    <th>{{ __('Scopes') }}</th>
                    <th>{{ __('Had/min') }}</th>
                    <th>{{ __('IP Allowlist') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Terakhir Guna') }}</th>
                    <th class="no-print" style="width:120px">{{ __('Tindakan') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($klien as $k)
                    <tr>
                        <td>{{ $k->name }}</td>
                        <td><code>{{ $k->client_key }}</code></td>
                        <td><small>{{ str_replace(',', ', ', $k->scopes) }}</small></td>
                        <td>{{ $k->rate_limit_per_min }}</td>
                        <td><small>{{ $k->ip_allowlist ?: '—' }}</small></td>
                        <td>
                            <span class="badge bg-{{ $k->is_active ? 'success' : 'secondary' }}">
                                {{ $k->is_active ? __('AKTIF') : __('TIDAK AKTIF') }}
                            </span>
                        </td>
                        <td><small>{{ $k->last_used_at?->format('Y-m-d H:i') ?? '—' }}</small></td>
                        <td class="no-print">
                            <form method="POST" action="{{ route('tetapan.api.klien.toggle', $k->id) }}" class="d-inline"
                                  onsubmit="return confirm('{{ $k->is_active ? __('Nyahaktifkan') : __('Aktifkan') }} {{ __('klien') }} \'{{ $k->name }}\'?')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-{{ $k->is_active ? 'danger' : 'success' }}">
                                    {{ $k->is_active ? __('Nyahaktif') : __('Aktifkan') }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted">{{ __('Tiada klien API didaftarkan.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header fw-bold">{{ __('Cipta Klien API Baharu') }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('tetapan.api.klien') }}">
            @csrf
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="name">{{ __('Nama Klien') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
                               required maxlength="120" value="{{ old('name') }}" placeholder="{{ __('cth: Sistem Portal MAIWP') }}">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="rate_limit_per_min">{{ __('Had Kadar (panggilan/minit)') }} <span class="text-danger">*</span></label>
                        <input type="number" name="rate_limit_per_min" id="rate_limit_per_min" class="form-control"
                               required min="1" max="10000" value="{{ old('rate_limit_per_min', 60) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="ip_allowlist">{{ __('IP Allowlist (opsional, pisah koma)') }}</label>
                        <input type="text" name="ip_allowlist" id="ip_allowlist" class="form-control"
                               maxlength="255" value="{{ old('ip_allowlist') }}" placeholder="cth: 203.0.113.10, 203.0.113.11">
                    </div>
                </div>
                <div class="col-md-8">
                    <label class="form-label">{{ __('Scopes (kebenaran)') }} <span class="text-danger">*</span></label>
                    @error('scopes')<div class="text-danger small">{{ $message }}</div>@enderror
                    <div class="row">
                        @foreach ($scopes as $scope)
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="scopes[]" value="{{ $scope }}"
                                           id="scope_{{ $loop->index }}" @checked(in_array($scope, old('scopes', []), true))>
                                    <label class="form-check-label" for="scope_{{ $loop->index }}"><code>{{ $scope }}</code></label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-key me-1"></i>{{ __('Cipta Klien & Jana Kredensial') }}</button>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header fw-bold"><i class="bi bi-broadcast me-1"></i>{{ __('Langganan Webhook (peristiwa keluar)') }}</div>
    <div class="card-body">
        <table class="table table-sm table-hover table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Event') }}</th>
                    <th>{{ __('Target URL') }}</th>
                    <th>{{ __('Secret (HMAC)') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="no-print" style="width:100px">{{ __('Tindakan') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($webhooks as $w)
                    <tr>
                        <td><code>{{ $w->event }}</code></td>
                        <td><small>{{ $w->target_url }}</small></td>
                        <td><small>{{ $w->secret ? str_repeat('•', 8) : '—' }}</small></td>
                        <td><span class="badge bg-{{ $w->is_active ? 'success' : 'secondary' }}">{{ $w->is_active ? __('AKTIF') : __('TIDAK AKTIF') }}</span></td>
                        <td class="no-print">
                            <form method="POST" action="{{ route('tetapan.api.webhook.padam', $w->id) }}" class="d-inline"
                                  onsubmit="return confirm('{{ __('Padam langganan webhook') }} {{ $w->event }}?')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Padam') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">{{ __('Tiada langganan webhook.') }}</td></tr>
                @endforelse
            </tbody>
        </table>

        <form method="POST" action="{{ route('tetapan.api.webhook') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-3">
                <label class="form-label" for="event">{{ __('Event') }} <span class="text-danger">*</span></label>
                <select name="event" id="event" class="form-select" required>
                    @foreach ($events as $e)
                        <option value="{{ $e }}" @selected(old('event') === $e)>{{ $e }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="target_url">{{ __('Target URL') }} <span class="text-danger">*</span></label>
                <input type="url" name="target_url" id="target_url" class="form-control" required
                       maxlength="255" value="{{ old('target_url') }}" placeholder="https://sistem-luar.example/webhook">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="secret">{{ __('Secret HMAC (opsional)') }}</label>
                <input type="text" name="secret" id="secret" class="form-control" maxlength="120" value="{{ old('secret') }}">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">{{ __('Tambah') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="text-muted small">
    {{ __('Dokumentasi pembangun luar:') }} <a href="{{ url('/v1/docs') }}" target="_blank">OpenAPI 3.0 (/v1/docs)</a> ·
    {{ __('Dapatkan token:') }} <code>POST /v1/auth/token</code> dengan <code>{client_key, secret}</code>.
</div>
@endsection
