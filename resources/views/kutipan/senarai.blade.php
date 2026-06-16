@extends('layouts.app')

@section('title', __('Senarai Kutipan'))

@section('content')
<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" action="{{ route('kutipan.senarai') }}" class="row g-2 align-items-end">
            <x-period-filter />
            <div class="col-md-4">
                <x-coa-select name="coa_id" :required="false" :julat="['100','300','400','450']"
                              placeholder="-- Semua Kategori --" :selected="request('coa_id')" class="mb-0" />
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">{{ __('Papar') }}</button>
                <a href="{{ route('kutipan.senarai') }}" class="btn btn-outline-secondary">{{ __('Reset') }}</a>
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>{{ __('Cetak') }}
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Senarai Kutipan') }} — {{ __('Tempoh') }} {{ $periodYm }}</div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover table-bordered">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Tarikh') }}</th>
                    <th>{{ __('Butiran / Pemberi') }}</th>
                    <th>{{ __('No. Resit') }}</th>
                    <th>{{ __('Kaedah') }}</th>
                    <th>{{ __('Kategori') }}</th>
                    <th class="text-end">{{ __('Jumlah (RM)') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($senarai as $k)
                    <tr style="cursor:pointer" onclick="window.location='{{ route('kutipan.view', $k) }}'">
                        <td>{{ $k->tarikh?->format('d/m/Y') }}</td>
                        <td>{{ $k->deskripsi ?: $k->nama_pemberi ?: '—' }}</td>
                        <td>{{ $k->no_resit }}</td>
                        <td>{{ $k->kaedah }}</td>
                        <td>{{ $k->coa?->kod }} {{ $k->coa?->nama }}</td>
                        <td class="text-end">{{ number_format((float) $k->jumlah, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">{{ __('Tiada kutipan bagi tempoh ini.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="5" class="text-end">{{ __('JUMLAH KESELURUHAN (RM)') }}</th>
                    <th class="text-end">{{ number_format($jumlah, 2) }}</th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
