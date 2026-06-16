@extends('layouts.app')

@section('title', __('Senarai Aset'))

@section('content')
<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" action="{{ route('aset.senarai') }}" class="row g-2 align-items-end">
            <div class="col-auto">
                <select name="jenis" class="form-select">
                    <option value="" @selected(!$jenis)>{{ __('Semua Aset') }}</option>
                    <option value="ALIH" @selected($jenis === 'ALIH')>{{ __('Aset Alih') }}</option>
                    <option value="TIDAK_ALIH" @selected($jenis === 'TIDAK_ALIH')>{{ __('Aset Tidak Alih') }}</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">{{ __('Papar') }}</button>
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>{{ __('Cetak') }}
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Senarai Aset Tetap') }}</div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover table-bordered">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Kod') }}</th>
                    <th>{{ __('Nama') }}</th>
                    <th>{{ __('COA') }}</th>
                    <th>{{ __('Tarikh Perolehan') }}</th>
                    <th class="text-end">{{ __('Kos (RM)') }}</th>
                    <th class="text-end">{{ __('SNT Terkumpul (RM)') }}</th>
                    <th class="text-end">{{ __('Nilai Bersih (RM)') }}</th>
                    <th>{{ __('Lokasi') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="no-print">{{ __('Tindakan') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($senarai as $a)
                    @php $coa = $namaCoa->get($a->coa_id); @endphp
                    <tr>
                        <td>{{ $a->kod_aset }}</td>
                        <td>{{ $a->nama }} <span class="text-muted small">({{ $a->jenis_aset === 'ALIH' ? __('Alih') : __('Tidak Alih') }})</span></td>
                        <td>{{ $coa ? $coa->kod.' '.$coa->nama : '—' }}</td>
                        <td>{{ $a->tarikh_perolehan?->format('d/m/Y') }}</td>
                        <td class="text-end">{{ number_format((float) $a->kos, 2) }}</td>
                        <td class="text-end">{{ number_format((float) $a->accumulated_depn, 2) }}</td>
                        <td class="text-end">{{ number_format((float) $a->kos - (float) $a->accumulated_depn, 2) }}</td>
                        <td>{{ $a->lokasi ?: '—' }}</td>
                        <td><span class="badge {{ $a->status === 'AKTIF' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $a->status }}</span></td>
                        <td class="no-print">
                            <a href="{{ route('aset.lihat', $a) }}" class="btn btn-sm btn-outline-primary">{{ __('Lihat') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted">{{ __('Tiada aset didaftarkan.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
