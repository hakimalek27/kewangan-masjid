@extends('layouts.app')

@section('title', __('Konsol Sistem'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="bi bi-hdd-network me-2"></i>{{ __('Konsol Sistem') }}</h4>
    <a href="{{ route('tetapan.masjid.baru') }}" class="btn btn-success btn-sm">
        <i class="bi bi-building-add me-1"></i>{{ __('Masjid Baru') }}
    </a>
</div>
<p class="text-muted small">{{ __('Pentadbir sistem: urus masjid, pengguna & konfigurasi platform. Merekod kewangan dibuat oleh bendahari setiap masjid.') }}</p>

{{-- Kad kesihatan sistem (merentas semua masjid) --}}
<div class="row g-3 mb-4">
    @foreach ([
        [__('Masjid'), $kad['jumlah_masjid'], 'building', 'primary'],
        [__('Ralat belum selesai'), $kad['ralat_belum'], 'exclamation-triangle', 'danger'],
        [__('Keselamatan (HIGH, 7h)'), $kad['event_high_7hari'], 'shield-exclamation', 'warning'],
        [__('Draf AI menunggu'), $kad['draf_menunggu'], 'inbox', 'info'],
        [__('Kelulusan menunggu'), $kad['kelulusan_pending'], 'check2-square', 'secondary'],
        [__('Login gagal (24j)'), $kad['login_gagal_24j'], 'person-lock', 'dark'],
    ] as [$label, $nilai, $ikon, $warna])
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card shadow-sm h-100 border-{{ $warna }}">
                <div class="card-body text-center py-3">
                    <i class="bi bi-{{ $ikon }} fs-4 text-{{ $warna }}"></i>
                    <div class="fs-4 fw-bold">{{ number_format($nilai) }}</div>
                    <div class="small text-muted">{{ $label }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

{{-- Senarai masjid dalam sistem --}}
<div class="card shadow-sm mb-4">
    <div class="card-header fw-bold d-flex justify-content-between align-items-center">
        <span>{{ __('Masjid dalam Sistem') }}</span>
        <span class="badge bg-secondary">{{ $masjids->count() }}</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Masjid') }}</th>
                    <th>{{ __('Negeri') }}</th>
                    <th class="text-end">{{ __('Pengguna') }}</th>
                    <th class="text-end">{{ __('COA') }}</th>
                    <th class="text-end">{{ __('Bank') }}</th>
                    <th>{{ __('Transaksi Terakhir') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($masjids as $m)
                    <tr>
                        <td>
                            <strong>{{ $m->nama }}</strong>
                            @if ($m->kategori)<div class="small text-muted">{{ $m->kategori }}</div>@endif
                        </td>
                        <td>{{ $m->negeri ?: '—' }}</td>
                        <td class="text-end">{{ number_format($bilPengguna[$m->id] ?? 0) }}</td>
                        <td class="text-end">
                            @php $c = (int) ($bilCoa[$m->id] ?? 0); @endphp
                            @if ($c) {{ number_format($c) }} @else <span class="badge bg-warning text-dark">{{ __('Tiada COA') }}</span> @endif
                        </td>
                        <td class="text-end">{{ number_format($bilBank[$m->id] ?? 0) }}</td>
                        <td>{{ ($txnAkhir[$m->id] ?? null) ? \Illuminate\Support\Carbon::parse($txnAkhir[$m->id])->format('d/m/Y') : '—' }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('masjid.tukar') }}" class="m-0">@csrf
                                <input type="hidden" name="masjid_id" value="{{ $m->id }}">
                                <input type="hidden" name="ke" value="dashboard">
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-box-arrow-in-right me-1"></i>{{ __('Masuk Masjid') }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-3">{{ __('Tiada masjid.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Pautan pentadbiran sistem --}}
<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Pentadbiran Sistem') }}</div>
    <div class="card-body d-flex flex-wrap gap-2">
        <a href="{{ route('admin.pemantauan') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-activity me-1"></i>{{ __('Pemantauan') }}</a>
        <a href="{{ route('admin.audit') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-shield-check me-1"></i>{{ __('Jejak Audit') }}</a>
        <a href="{{ route('admin.ralat') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-bug me-1"></i>{{ __('Log Ralat') }}</a>
        <a href="{{ route('admin.keselamatan') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-shield-lock me-1"></i>{{ __('Keselamatan') }}</a>
        <a href="{{ route('admin.backup') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-cloud-arrow-up me-1"></i>{{ __('Backup') }}@if ($backupTerakhir?->created_at)<span class="small text-muted ms-1">({{ \Illuminate\Support\Carbon::parse($backupTerakhir->created_at)->format('d/m') }})</span>@endif</a>
        <a href="{{ route('tetapan.pengguna') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-people me-1"></i>{{ __('Pengguna') }}</a>
        <a href="{{ route('tetapan.api') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-plug me-1"></i>{{ __('API Awam') }}</a>
        <a href="{{ route('tetapan.ai') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-robot me-1"></i>{{ __('AI & Telegram') }}</a>
    </div>
</div>
@endsection
