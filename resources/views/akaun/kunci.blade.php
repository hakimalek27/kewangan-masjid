@extends('layouts.app')

@section('title', __('Kunci Kira-Kira'))

@php
    $wang = fn ($v) => (float) $v < 0
        ? '<span class="text-danger">('.number_format(abs((float) $v), 2).')</span>'
        : number_format((float) $v, 2);
@endphp

@section('content')
<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <x-period-filter />
            <div class="col-auto">
                <x-export-buttons />
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-bold d-flex justify-content-between align-items-center">
        <span>{{ __('Kunci Kira-Kira pada') }} {{ $ym }}</span>
        @if ($bs['seimbang'])
            <span class="badge text-bg-success">{{ __('SEIMBANG') }} &#10004;</span>
        @else
            <span class="badge text-bg-danger">{{ __('TIDAK SEIMBANG') }}</span>
        @endif
    </div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-bordered">
            <thead class="table-light">
                <tr><th>{{ __('Kod') }}</th><th>{{ __('Butiran') }}</th><th class="text-end">{{ __('Amaun (RM)') }}</th></tr>
            </thead>
            <tbody>
                <tr class="table-secondary fw-bold"><td colspan="3">{{ __('ASET') }}</td></tr>
                @foreach ($bs['aset'] as $r)
                    <tr>
                        <td>{{ $r->kod }}</td>
                        <td class="ps-4">{{ $r->nama }}</td>
                        <td class="text-end">{!! $wang($r->amaun) !!}</td>
                    </tr>
                @endforeach
                <tr class="fw-bold">
                    <td colspan="2" class="text-end">{{ __('JUMLAH ASET') }}</td>
                    <td class="text-end">{!! $wang($bs['total_aset']) !!}</td>
                </tr>

                <tr class="table-secondary fw-bold"><td colspan="3">{{ __('LIABILITI') }}</td></tr>
                @foreach ($bs['liabiliti'] as $r)
                    <tr>
                        <td>{{ $r->kod }}</td>
                        <td class="ps-4">{{ $r->nama }}</td>
                        <td class="text-end">{!! $wang($r->amaun) !!}</td>
                    </tr>
                @endforeach
                <tr class="fw-bold">
                    <td colspan="2" class="text-end">{{ __('JUMLAH LIABILITI') }}</td>
                    <td class="text-end">{!! $wang($bs['total_liabiliti']) !!}</td>
                </tr>

                <tr class="table-secondary fw-bold"><td colspan="3">{{ __('EKUITI') }}</td></tr>
                @foreach ($bs['ekuiti'] as $r)
                    <tr>
                        <td>{{ $r->kod }}</td>
                        <td class="ps-4">{{ $r->nama }}</td>
                        <td class="text-end">{!! $wang($r->amaun) !!}</td>
                    </tr>
                @endforeach
                <tr>
                    <td></td>
                    <td class="ps-4">{{ __('Lebihan/(Kurangan) Terkumpul') }}</td>
                    <td class="text-end">{!! $wang($bs['lebihan_terkumpul']) !!}</td>
                </tr>
                <tr class="fw-bold">
                    <td colspan="2" class="text-end">{{ __('JUMLAH EKUITI') }}</td>
                    <td class="text-end">{!! $wang($bs['total_ekuiti']) !!}</td>
                </tr>
            </tbody>
            <tfoot class="table-light">
                <tr class="fw-bold">
                    <td colspan="2" class="text-end">{{ __('JUMLAH LIABILITI + EKUITI') }}</td>
                    <td class="text-end">{!! $wang((float) $bs['total_liabiliti'] + (float) $bs['total_ekuiti']) !!}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
