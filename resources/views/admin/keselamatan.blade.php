@extends('layouts.app')

@section('title', __('Keselamatan'))

@section('content')
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link {{ $tab !== 'login' ? 'active' : '' }}" href="{{ route('admin.keselamatan') }}">
                <i class="bi bi-shield-exclamation me-1"></i>{{ __('Security Event') }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'login' ? 'active' : '' }}" href="{{ route('admin.keselamatan', ['tab' => 'login']) }}">
                <i class="bi bi-door-closed me-1"></i>{{ __('Percubaan Log Masuk') }}
            </a>
        </li>
    </ul>

    @if ($tab !== 'login')
        <div class="card shadow-sm">
            <div class="card-header fw-bold d-flex justify-content-between align-items-center">
                <span>{{ __('Security Event') }}</span>
                <form method="GET" action="{{ route('admin.keselamatan') }}" class="d-flex gap-2">
                    <select name="severity" class="form-select form-select-sm" style="width:auto">
                        <option value="">{{ __('Semua tahap') }}</option>
                        @foreach (['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'] as $sev)
                            <option value="{{ $sev }}" @selected(request('severity') === $sev)>{{ $sev }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Tapis') }}</button>
                </form>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-sm table-hover table-bordered align-middle">
                    <thead class="table-light">
                        <tr><th>#</th><th>{{ __('Masa') }}</th><th>{{ __('Jenis') }}</th><th>{{ __('Tahap') }}</th><th>{{ __('Pengguna') }}</th><th>{{ __('IP') }}</th><th>{{ __('Butiran') }}</th><th>{{ __('Amaran') }}</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($events as $e)
                            <tr class="{{ in_array($e->severity, ['HIGH', 'CRITICAL'], true) ? 'table-warning' : '' }}">
                                <td>{{ $e->id }}</td>
                                <td><small>{{ $e->created_at }}</small></td>
                                <td><small>{{ $e->jenis }}</small></td>
                                <td><span class="badge text-bg-{{ ['LOW' => 'secondary', 'MEDIUM' => 'info', 'HIGH' => 'warning', 'CRITICAL' => 'danger'][$e->severity] ?? 'secondary' }}">{{ $e->severity }}</span></td>
                                <td><small>{{ $namaPengguna[$e->user_id] ?? ($e->user_id ?: '—') }}</small></td>
                                <td><small>{{ $e->ip_address ?? '—' }}</small></td>
                                <td><small>{{ $e->detail }}</small></td>
                                <td>
                                    <span class="badge bg-{{ $e->alerted ? 'success' : 'secondary' }}">{{ $e->alerted ? __('DIHANTAR') : '—' }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted">{{ __('Tiada security event.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                {{ $events->appends(['tab' => 'event'])->links() }}
            </div>
        </div>
    @else
        <div class="card shadow-sm">
            <div class="card-header fw-bold">{{ __('Percubaan Log Masuk') }}</div>
            <div class="card-body table-responsive">
                <table class="table table-sm table-hover table-bordered align-middle">
                    <thead class="table-light">
                        <tr><th>#</th><th>{{ __('Masa') }}</th><th>{{ __('Login') }}</th><th>{{ __('IP') }}</th><th>{{ __('Status') }}</th><th>{{ __('User-Agent') }}</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($percubaan as $p)
                            <tr class="{{ $p->success ? '' : 'table-danger' }}">
                                <td>{{ $p->id }}</td>
                                <td><small>{{ $p->created_at }}</small></td>
                                <td><small>{{ $p->login }}</small></td>
                                <td><small>{{ $p->ip_address }}</small></td>
                                <td><span class="badge bg-{{ $p->success ? 'success' : 'danger' }}">{{ $p->success ? __('BERJAYA') : __('GAGAL') }}</span></td>
                                <td><small>{{ \Illuminate\Support\Str::limit($p->user_agent, 60) }}</small></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">{{ __('Tiada percubaan log masuk.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                {{ $percubaan->appends(['tab' => 'login'])->links() }}
            </div>
        </div>
    @endif
@endsection
