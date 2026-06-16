@extends('layouts.app')

@section('title', __('Buku Tunai PWR'))

@section('content')
<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" action="{{ route('pwr.buku') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <x-coa-select name="pwr_coa_id" :required="false" :julat="['250-06']"
                              placeholder="-- Semua Akaun PWR --" :selected="request('pwr_coa_id')" class="mb-0" />
            </div>
            <x-period-filter />
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
    <div class="card-header fw-bold">{{ __('Buku Tunai PWR') }} — {{ __('Tempoh') }} {{ $periodYm }}</div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover table-bordered">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Tarikh') }}</th>
                    <th>{{ __('Butiran') }}</th>
                    <th>{{ __('No. Baucer') }}</th>
                    <th>{{ __('Kategori') }}</th>
                    <th class="text-end">{{ __('Jumlah (RM)') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($senarai as $p)
                    <tr style="cursor:pointer" onclick="window.location='{{ route('belanja.view', $p) }}'">
                        <td>{{ $p->tar_lulus?->format('d/m/Y') }}</td>
                        <td>{{ $p->deskripsi ?: $p->pemohon ?: '—' }}</td>
                        <td>{{ $p->baucer_no }}</td>
                        <td>{{ $p->coa?->kod }} {{ $p->coa?->nama }}</td>
                        <td class="text-end">{{ number_format((float) $p->jumlah, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">{{ __('Tiada bayaran PWR bagi tempoh ini.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="4" class="text-end">{{ __('JUMLAH KESELURUHAN (RM)') }}</th>
                    <th class="text-end">{{ number_format($jumlah, 2) }}</th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
