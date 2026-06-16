@extends('layouts.app')

@section('title', __('Carian Global'))

@section('content')
<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('carian') }}" class="row g-2">
            <div class="col-md-6">
                <input type="search" name="q" class="form-control" placeholder="{{ __('Cari resit, baucer, nama, deskripsi, aset...') }}"
                       value="{{ $q }}" minlength="2" autofocus>
            </div>
            <div class="col-auto"><button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>{{ __('Cari') }}</button></div>
        </form>
    </div>
</div>

@if (mb_strlen($q) >= 2)
    <p class="text-muted small">
        {{ __('Keputusan untuk') }} "<strong>{{ $q }}</strong>" —
        {{ $kutipan->count() + $pembayaran->count() + $aset->count() }} {{ __('rekod dijumpai') }}
        ({{ $kutipan->count() }} {{ __('kutipan') }}, {{ $pembayaran->count() }} {{ __('pembayaran') }}, {{ $aset->count() }} {{ __('aset') }}).
    </p>

    <div class="card shadow-sm mb-3">
        <div class="card-header fw-bold"><i class="bi bi-cash-coin me-1"></i>{{ __('Kutipan') }} <span class="badge text-bg-primary ms-1">{{ $kutipan->count() }}</span></div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-hover table-bordered align-middle">
                <thead class="table-light"><tr><th>{{ __('No. Resit') }}</th><th>{{ __('Tarikh') }}</th><th>{{ __('Pemberi') }}</th><th>{{ __('Deskripsi') }}</th><th class="text-end">{{ __('Jumlah (RM)') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($kutipan as $k)
                        <tr>
                            <td>{{ $k->no_resit }}</td>
                            <td>{{ $k->tarikh?->format('d/m/Y') }}</td>
                            <td class="small">{{ $k->nama_pemberi ?: '—' }}</td>
                            <td class="small">{{ \Illuminate\Support\Str::limit($k->deskripsi, 60) }}</td>
                            <td class="text-rm">{{ number_format((float) $k->jumlah, 2) }}</td>
                            <td><a href="{{ route('kutipan.view', $k->id) }}" class="btn btn-sm btn-outline-primary">{{ __('Lihat') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">{{ __('Tiada kutipan sepadan.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header fw-bold"><i class="bi bi-credit-card me-1"></i>{{ __('Pembayaran') }} <span class="badge text-bg-secondary ms-1">{{ $pembayaran->count() }}</span></div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-hover table-bordered align-middle">
                <thead class="table-light"><tr><th>{{ __('No. Baucer') }}</th><th>{{ __('Tarikh') }}</th><th>{{ __('Pemohon') }}</th><th>{{ __('Deskripsi') }}</th><th class="text-end">{{ __('Jumlah (RM)') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($pembayaran as $p)
                        <tr>
                            <td>{{ $p->baucer_no }}</td>
                            <td>{{ $p->tar_lulus?->format('d/m/Y') }}</td>
                            <td class="small">{{ $p->pemohon ?: '—' }}</td>
                            <td class="small">{{ \Illuminate\Support\Str::limit($p->deskripsi, 60) }}</td>
                            <td class="text-rm">{{ number_format((float) $p->jumlah, 2) }}</td>
                            <td><a href="{{ route('belanja.view', $p->id) }}" class="btn btn-sm btn-outline-primary">{{ __('Lihat') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">{{ __('Tiada pembayaran sepadan.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header fw-bold"><i class="bi bi-building me-1"></i>{{ __('Aset Tetap') }} <span class="badge text-bg-info ms-1">{{ $aset->count() }}</span></div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-hover table-bordered align-middle">
                <thead class="table-light"><tr><th>{{ __('Kod') }}</th><th>{{ __('Nama') }}</th><th class="text-end">{{ __('Kos (RM)') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($aset as $a)
                        <tr>
                            <td>{{ $a->kod_aset }}</td>
                            <td class="small">{{ $a->nama }}</td>
                            <td class="text-rm">{{ number_format((float) $a->kos, 2) }}</td>
                            <td><span class="badge {{ $a->status === 'AKTIF' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $a->status }}</span></td>
                            <td><a href="{{ route('susutnilai.index') }}" class="btn btn-sm btn-outline-primary">{{ __('Susut Nilai') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">{{ __('Tiada aset sepadan.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@else
    <div class="alert alert-secondary">{{ __('Masukkan sekurang-kurangnya 2 aksara untuk mencari.') }}</div>
@endif
@endsection
