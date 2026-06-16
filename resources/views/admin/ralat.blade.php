@extends('layouts.app')

@section('title', __('Log Ralat'))

@section('content')
    <div class="card shadow-sm">
        <div class="card-header fw-bold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-bug me-1"></i>{{ __('Log Ralat Aplikasi') }}</span>
            <form method="GET" action="{{ route('admin.ralat') }}" class="d-flex gap-2">
                <select name="level" class="form-select form-select-sm" style="width:auto">
                    <option value="">{{ __('Semua tahap') }}</option>
                    @foreach (['INFO', 'WARNING', 'ERROR', 'FATAL'] as $lvl)
                        <option value="{{ $lvl }}" @selected(request('level') === $lvl)>{{ $lvl }}</option>
                    @endforeach
                </select>
                <select name="papar" class="form-select form-select-sm" style="width:auto">
                    <option value="" @selected(request('papar') !== 'semua')>{{ __('Belum selesai') }}</option>
                    <option value="semua" @selected(request('papar') === 'semua')>{{ __('Semua (termasuk selesai)') }}</option>
                </select>
                <button type="submit" class="btn btn-sm btn-primary">{{ __('Tapis') }}</button>
            </form>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-hover table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th><th>{{ __('Masa') }}</th><th>{{ __('Tahap') }}</th><th>{{ __('Mesej') }}</th><th>{{ __('URL') }}</th><th>{{ __('Status') }}</th><th class="no-print">{{ __('Tindakan') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ralat as $r)
                        <tr class="{{ in_array($r->level, ['ERROR', 'FATAL'], true) && !$r->resolved ? 'table-danger' : '' }}">
                            <td>{{ $r->id }}</td>
                            <td><small>{{ $r->created_at }}</small></td>
                            <td><span class="badge text-bg-{{ ['INFO' => 'info', 'WARNING' => 'warning', 'ERROR' => 'danger', 'FATAL' => 'dark'][$r->level] ?? 'secondary' }}">{{ $r->level }}</span></td>
                            <td>
                                <small>{{ \Illuminate\Support\Str::limit($r->message, 120) }}</small>
                                @if ($r->stack)
                                    <button type="button" class="btn btn-sm btn-link p-0 ms-1" data-bs-toggle="modal" data-bs-target="#modalRalat{{ $r->id }}">stack</button>
                                @endif
                            </td>
                            <td><small>{{ \Illuminate\Support\Str::limit($r->url, 50) ?: '—' }}</small></td>
                            <td>
                                <span class="badge bg-{{ $r->resolved ? 'success' : 'secondary' }}">{{ $r->resolved ? __('SELESAI') : __('BELUM') }}</span>
                            </td>
                            <td class="no-print">
                                @unless ($r->resolved)
                                    <form method="POST" action="{{ route('admin.ralat.selesai', $r->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-check2"></i> {{ __('Selesai') }}
                                        </button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">{{ __('Tiada ralat — sistem sihat.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $ralat->links() }}
        </div>
    </div>

    @foreach ($ralat as $r)
        @if ($r->stack)
            <div class="modal fade" id="modalRalat{{ $r->id }}" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h6 class="modal-title">{{ __('Ralat') }} #{{ $r->id }} [{{ $r->level }}]</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="small fw-bold mb-1">{{ $r->message }}</div>
                            <pre class="small bg-light p-2 border rounded">{{ $r->stack }}</pre>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
@endsection
