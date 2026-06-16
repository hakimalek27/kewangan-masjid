@extends('layouts.app')

@section('title', __('Butiran Aset'))

@section('content')
<div class="card shadow-sm mb-3 no-print">
    <div class="card-body d-flex justify-content-between align-items-center">
        <a href="{{ route('aset.senarai') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>{{ __('Kembali ke Senarai') }}
        </a>
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>{{ __('Cetak') }}
        </button>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header fw-bold">
        {{ __('Butiran Aset') }} — {{ $aset->kod_aset }}
        <span class="badge {{ $aset->status === 'AKTIF' ? 'text-bg-success' : 'text-bg-secondary' }} ms-2">{{ $aset->status }}</span>
    </div>
    <div class="card-body">
        <table class="table table-sm table-bordered align-middle mb-0">
            <tbody>
                <tr>
                    <th style="width:25%">{{ __('Kod Aset') }}</th><td style="width:25%">{{ $aset->kod_aset }}</td>
                    <th style="width:25%">{{ __('Nama Aset') }}</th><td>{{ $aset->nama }}</td>
                </tr>
                <tr>
                    <th>{{ __('Jenis Aset') }}</th><td>{{ $aset->jenis_aset === 'ALIH' ? __('Aset Alih') : __('Aset Tidak Alih') }}</td>
                    <th>{{ __('Tarikh Perolehan') }}</th><td>{{ $aset->tarikh_perolehan?->format('d/m/Y') ?: '—' }}</td>
                </tr>
                <tr>
                    <th>{{ __('COA Aset') }}</th><td>{{ $coaAset ? $coaAset->kod.' '.$coaAset->nama : '—' }}</td>
                    <th>{{ __('COA SNT (Kontra)') }}</th><td>{{ $coaSnt ? $coaSnt->kod.' '.$coaSnt->nama : '—' }}</td>
                </tr>
                <tr>
                    <th>{{ __('Kos (RM)') }}</th><td class="text-end">{{ number_format((float) $aset->kos, 2) }}</td>
                    <th>{{ __('SNT Terkumpul (RM)') }}</th><td class="text-end">{{ number_format((float) $aset->accumulated_depn, 2) }}</td>
                </tr>
                <tr>
                    <th>{{ __('Nilai Bersih (RM)') }}</th><td class="text-end fw-bold">{{ number_format((float) $nilaiBersih, 2) }}</td>
                    <th>{{ __('Hayat Berguna (tahun)') }}</th><td>{{ $aset->useful_life_years ?: '—' }}</td>
                </tr>
                <tr>
                    <th>{{ __('Kadar Susut (%)') }}</th><td>{{ $aset->depn_rate_pct !== null ? number_format((float) $aset->depn_rate_pct, 2) : '—' }}</td>
                    <th>{{ __('Lokasi') }}</th><td>{{ $aset->lokasi ?: '—' }}</td>
                </tr>
                <tr>
                    <th>{{ __('Status Tagging') }}</th><td>{{ $aset->tagging_status === 'TAGGED' ? __('Sudah Tag') : __('Belum Tag') }}</td>
                    <th>{{ __('Cara Perolehan') }}</th><td>{{ $aset->acquisition_type ?: '—' }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Sejarah Susut Nilai') }}</div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-bordered table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Tahun') }}</th>
                    <th>{{ __('Bulan') }}</th>
                    <th class="text-end">{{ __('Amaun (RM)') }}</th>
                    <th>{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sejarah as $s)
                    <tr>
                        <td>{{ $s->tahun }}</td>
                        <td>{{ str_pad((string) $s->bulan, 2, '0', STR_PAD_LEFT) }}</td>
                        <td class="text-end">{{ number_format((float) $s->amaun, 2) }}</td>
                        <td>
                            @if ($s->posted)
                                <span class="badge text-bg-success">{{ __('Diposkan') }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ __('Belum Pos') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted">{{ __('Tiada rekod susut nilai.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
