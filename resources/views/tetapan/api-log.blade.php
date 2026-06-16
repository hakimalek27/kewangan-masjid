@extends('layouts.app')

@section('title', __('Log API'))

@section('content')
<div class="card shadow-sm">
    <div class="card-header fw-bold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-columns me-1"></i>{{ __('Log Panggilan API — 100 Terakhir') }}</span>
        <a href="{{ route('tetapan.api') }}" class="btn btn-sm btn-outline-secondary">{{ __('Kembali ke Tetapan API') }}</a>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Masa') }}</th>
                    <th>{{ __('Klien') }}</th>
                    <th>Method</th>
                    <th>Path</th>
                    <th>{{ __('Status') }}</th>
                    <th>IP</th>
                    <th>Idempotency-Key</th>
                    <th class="text-end">{{ __('Latensi (ms)') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($log as $l)
                    <tr>
                        <td><small>{{ $l->created_at }}</small></td>
                        <td>{{ $namaKlien[$l->client_id] ?? '—' }}</td>
                        <td><code>{{ $l->method }}</code></td>
                        <td><small>{{ $l->path }}</small></td>
                        <td>
                            <span class="badge bg-{{ $l->status_code < 300 ? 'success' : ($l->status_code < 500 ? 'warning text-dark' : 'danger') }}">
                                {{ $l->status_code }}
                            </span>
                        </td>
                        <td><small>{{ $l->ip_address }}</small></td>
                        <td><small>{{ $l->idempotency_key ?: '—' }}</small></td>
                        <td class="text-end">{{ $l->latency_ms }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted">{{ __('Tiada panggilan API direkodkan.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
