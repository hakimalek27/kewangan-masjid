@extends('layouts.app')

@section('title', __('Jejak Audit'))

@section('content')
    @if (session('audit_gagal'))
        <div class="alert alert-danger shadow-sm">{{ session('audit_gagal') }}</div>
    @endif
    @if (session('audit_output'))
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-bold">{{ __('Output Pengesahan Rantai (sppkms:verify-audit-chain)') }}</div>
            <div class="card-body"><pre class="mb-0 small">{{ session('audit_output') }}</pre></div>
        </div>
    @endif

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-bold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-funnel me-1"></i>{{ __('Tapisan') }}</span>
            <form method="POST" action="{{ route('admin.audit.sahkan') }}"
                  onsubmit="return confirm('{{ __('Jalankan pengesahan hash-chain ke atas keseluruhan jejak audit?') }}')">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-patch-check me-1"></i>{{ __('Sahkan Rantai') }}
                </button>
            </form>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.audit') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small" for="user_id">{{ __('Pengguna') }}</label>
                    <select name="user_id" id="user_id" class="form-select form-select-sm">
                        <option value="">{{ __('— semua —') }}</option>
                        @foreach ($pengguna as $p)
                            <option value="{{ $p->id }}" @selected(request('user_id') == $p->id)>{{ $p->nama_penuh }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small" for="entity">{{ __('Entiti') }}</label>
                    <select name="entity" id="entity" class="form-select form-select-sm">
                        <option value="">{{ __('— semua —') }}</option>
                        @foreach ($entities as $e)
                            <option value="{{ $e }}" @selected(request('entity') === $e)>{{ $e }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small" for="action">{{ __('Tindakan') }}</label>
                    <select name="action" id="action" class="form-select form-select-sm">
                        <option value="">{{ __('— semua —') }}</option>
                        @foreach ($actions as $a)
                            <option value="{{ $a }}" @selected(request('action') === $a)>{{ $a }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small" for="dari">{{ __('Dari') }}</label>
                    <input type="date" name="dari" id="dari" class="form-control form-control-sm" value="{{ request('dari') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small" for="hingga">{{ __('Hingga') }}</label>
                    <input type="date" name="hingga" id="hingga" class="form-control form-control-sm" value="{{ request('hingga') }}">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100">{{ __('Tapis') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header fw-bold"><i class="bi bi-list-check me-1"></i>{{ __('Jejak Audit (hash-chain, append-only)') }}</div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-hover table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th><th>{{ __('Masa') }}</th><th>{{ __('Pengguna') }}</th><th>{{ __('Tindakan') }}</th>
                        <th>{{ __('Entiti') }}</th><th>{{ __('ID') }}</th><th>{{ __('IP') }}</th><th>{{ __('Data') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($audit as $a)
                        <tr>
                            <td>{{ $a->id }}</td>
                            <td><small>{{ $a->created_at }}</small></td>
                            <td><small>{{ $pengguna->firstWhere('id', $a->user_id)?->nama_penuh ?? ($a->user_id ?: __('sistem')) }}</small></td>
                            <td><span class="badge text-bg-{{ ['CREATE' => 'success', 'UPDATE' => 'primary', 'DELETE' => 'danger', 'VOID' => 'danger'][$a->action] ?? 'secondary' }}">{{ $a->action }}</span></td>
                            <td><small>{{ $a->entity }}</small></td>
                            <td><small>{{ $a->entity_id ?? '—' }}</small></td>
                            <td><small>{{ $a->ip_address ?? '—' }}</small></td>
                            <td>
                                @if ($a->before_json || $a->after_json)
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal" data-bs-target="#modalAudit{{ $a->id }}">
                                        <i class="bi bi-braces"></i> {{ __('Lihat') }}
                                    </button>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted">{{ __('Tiada rekod audit sepadan tapisan.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $audit->links() }}
        </div>
    </div>

    {{-- Modal before/after JSON --}}
    @foreach ($audit as $a)
        @if ($a->before_json || $a->after_json)
            <div class="modal fade" id="modalAudit{{ $a->id }}" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h6 class="modal-title">Audit #{{ $a->id }} — {{ $a->action }} {{ $a->entity }}</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="fw-bold small text-danger mb-1">{{ __('SEBELUM') }}</div>
                                    <pre class="small bg-light p-2 border rounded">{{ $a->before_json ? json_encode($a->before_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : __('(tiada)') }}</pre>
                                </div>
                                <div class="col-md-6">
                                    <div class="fw-bold small text-success mb-1">{{ __('SELEPAS') }}</div>
                                    <pre class="small bg-light p-2 border rounded">{{ $a->after_json ? json_encode($a->after_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : __('(tiada)') }}</pre>
                                </div>
                            </div>
                            <div class="small text-muted mt-2">
                                row_hash: <code>{{ $a->row_hash }}</code><br>
                                prev_hash: <code>{{ $a->prev_hash ?? __('(permulaan rantai)') }}</code>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
@endsection
