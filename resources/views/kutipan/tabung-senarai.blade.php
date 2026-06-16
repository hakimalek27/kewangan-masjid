@extends('layouts.app')

@section('title', $tajuk)

@section('content')
<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
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
    <div class="card-header fw-bold">{{ $tajuk }} — {{ __('Tempoh') }} {{ $periodYm }}</div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover table-bordered">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Tarikh') }}</th>
                    <th>{{ __('Butiran') }}</th>
                    <th class="text-end">{{ __('Jumlah (RM)') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($senarai as $k)
                    <tr style="cursor:pointer" onclick="window.location='{{ route('kutipan.view', $k) }}'">
                        <td>{{ $k->tarikh?->format('d/m/Y') }}</td>
                        <td>{{ $k->deskripsi ?: 'Kutipan tabung (resit '.$k->no_resit.')' }}</td>
                        <td class="text-end">{{ number_format((float) $k->jumlah, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted">{{ __('Tiada kutipan bagi tempoh ini.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="2" class="text-end">{{ __('JUMLAH BESAR (RM)') }}</th>
                    <th class="text-end">{{ number_format($jumlah, 2) }}</th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
