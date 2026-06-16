@extends('layouts.app')

@section('title', __('Pemantauan Sistem'))

@section('content')
    {{-- Kad ringkasan --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-2">
            <div class="card border-start border-primary border-4 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-primary text-uppercase small fw-bold">{{ __('Transaksi Hari Ini') }}</div>
                    <div class="h4 mb-0 fw-bold">{{ $kad['transaksi_hari_ini'] }}</div>
                    <div class="small text-muted">{{ __('voucher jurnal dicipta') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-2">
            <div class="card border-start border-danger border-4 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-danger text-uppercase small fw-bold">{{ __('Ralat 7 Hari') }}</div>
                    <div class="h4 mb-0 fw-bold">{{ $kad['ralat_7_hari'] }}</div>
                    <div class="small text-muted"><a href="{{ route('admin.ralat') }}">{{ __('belum selesai') }}</a></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-2">
            <div class="card border-start border-warning border-4 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-warning text-uppercase small fw-bold">{{ __('Keselamatan 7 Hari') }}</div>
                    <div class="h4 mb-0 fw-bold">{{ $kad['event_high_7_hari'] }}</div>
                    <div class="small text-muted"><a href="{{ route('admin.keselamatan') }}">{{ __('event HIGH/CRITICAL') }}</a></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-2">
            <div class="card border-start border-{{ !$backupTerakhir ? 'secondary' : ($backupTerakhir->status === 'OK' ? 'success' : 'danger') }} border-4 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small fw-bold text-{{ !$backupTerakhir ? 'secondary' : ($backupTerakhir->status === 'OK' ? 'success' : 'danger') }}">{{ __('Backup Terakhir') }}</div>
                    <div class="h6 mb-0 fw-bold">
                        @if ($backupTerakhir)
                            {{ $backupTerakhir->created_at }}
                            <span class="badge bg-{{ $backupTerakhir->status === 'OK' ? 'success' : 'danger' }}">{{ $backupTerakhir->status }}</span>
                        @else
                            {{ __('Tiada lagi') }}
                        @endif
                    </div>
                    <div class="small text-muted"><a href="{{ route('admin.backup') }}">{{ __('tetapan backup') }}</a></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-2">
            <div class="card border-start border-info border-4 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-info text-uppercase small fw-bold">{{ __('Draf AI Menunggu') }}</div>
                    <div class="h4 mb-0 fw-bold">{{ $kad['draf_ai_menunggu'] }}</div>
                    <div class="small text-muted"><a href="{{ route('draf.index') }}">{{ __('kotak draf') }}</a></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-2">
            <div class="card border-start border-dark border-4 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-dark text-uppercase small fw-bold">{{ __('Login Gagal 24 Jam') }}</div>
                    <div class="h4 mb-0 fw-bold">{{ $kad['login_gagal_24j'] }}</div>
                    <div class="small text-muted"><a href="{{ route('admin.keselamatan', ['tab' => 'login']) }}">{{ __('log percubaan') }}</a></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        {{-- 20 aktiviti audit terkini --}}
        <div class="col-xl-7">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-check me-1"></i>{{ __('20 Aktiviti Audit Terkini') }}</span>
                    <a href="{{ route('admin.audit') }}" class="btn btn-sm btn-outline-secondary">{{ __('Jejak Penuh') }}</a>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead class="table-light">
                            <tr><th>{{ __('Masa') }}</th><th>{{ __('Pengguna') }}</th><th>{{ __('Tindakan') }}</th><th>{{ __('Entiti') }}</th><th>{{ __('ID') }}</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($auditTerkini as $a)
                                <tr>
                                    <td><small>{{ $a->created_at }}</small></td>
                                    <td><small>{{ $namaPengguna[$a->user_id] ?? ($a->user_id ?: __('sistem')) }}</small></td>
                                    <td><span class="badge text-bg-{{ ['CREATE' => 'success', 'UPDATE' => 'primary', 'DELETE' => 'danger', 'VOID' => 'danger'][$a->action] ?? 'secondary' }}">{{ $a->action }}</span></td>
                                    <td><small>{{ $a->entity }}</small></td>
                                    <td><small>{{ $a->entity_id ?? '—' }}</small></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">{{ __('Tiada aktiviti audit.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- 10 security event terkini --}}
        <div class="col-xl-5">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-shield-exclamation me-1"></i>{{ __('10 Security Event Terkini') }}</span>
                    <a href="{{ route('admin.keselamatan') }}" class="btn btn-sm btn-outline-secondary">{{ __('Semua') }}</a>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead class="table-light">
                            <tr><th>{{ __('Masa') }}</th><th>{{ __('Jenis') }}</th><th>{{ __('Tahap') }}</th><th>{{ __('Butiran') }}</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($eventTerkini as $e)
                                <tr>
                                    <td><small>{{ $e->created_at }}</small></td>
                                    <td><small>{{ $e->jenis }}</small></td>
                                    <td><span class="badge text-bg-{{ ['LOW' => 'secondary', 'MEDIUM' => 'info', 'HIGH' => 'warning', 'CRITICAL' => 'danger'][$e->severity] ?? 'secondary' }}">{{ $e->severity }}</span></td>
                                    <td><small>{{ \Illuminate\Support\Str::limit($e->detail, 60) }}</small></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">{{ __('Tiada security event.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
