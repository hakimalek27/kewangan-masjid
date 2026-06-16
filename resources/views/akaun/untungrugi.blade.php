@extends('layouts.app')

@section('title', __('Penyata Untung Rugi'))

@php
    $namaBulan = [1=>'Januari','Februari','Mac','April','Mei','Jun','Julai','Ogos','September','Oktober','November','Disember'];
    $wang = fn ($v) => (float) $v < 0
        ? '<span class="text-danger">('.number_format(abs((float) $v), 2).')</span>'
        : number_format((float) $v, 2);
@endphp

@section('content')
<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-0 small">{{ __('Mod') }}</label>
                <select name="mode" class="form-select">
                    <option value="terperinci" @selected($mode === 'terperinci')>{{ __('Terperinci (per akaun)') }}</option>
                    <option value="ringkas" @selected($mode === 'ringkas')>{{ __('Ringkas (jumlah sahaja)') }}</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-0 small">{{ __('Dari Bulan') }}</label>
                <select name="dari_bln" class="form-select">
                    @foreach ($namaBulan as $i => $nama)
                        <option value="{{ $i }}" @selected((int) substr($dari, 5, 2) === $i)>{{ __($nama) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-0 small">{{ __('Hingga Bulan / Tahun') }}</label>
                <div class="row g-1">
                    <x-period-filter />
                </div>
            </div>
            <div class="col-auto">
                <x-export-buttons />
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Penyata Untung Rugi') }} — {{ $dari }} {{ __('hingga') }} {{ $hingga }} ({{ ucfirst($mode) }})</div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-bordered">
            <thead class="table-light">
                <tr><th>{{ __('Kod') }}</th><th>{{ __('Butiran') }}</th><th class="text-end">{{ __('Amaun (RM)') }}</th></tr>
            </thead>
            <tbody>
                <tr class="table-secondary fw-bold"><td colspan="3">{{ __('PENDAPATAN (HASIL)') }}</td></tr>
                @if ($mode === 'terperinci')
                    @foreach ($pl['hasil'] as $r)
                        <tr>
                            <td>{{ $r->kod }}</td>
                            <td class="ps-4">{{ $r->nama }}</td>
                            <td class="text-end">{!! $wang($r->amaun) !!}</td>
                        </tr>
                    @endforeach
                @endif
                <tr class="fw-bold">
                    <td colspan="2" class="text-end">{{ __('JUMLAH PENDAPATAN') }}</td>
                    <td class="text-end">{!! $wang($pl['jumlah_hasil']) !!}</td>
                </tr>

                <tr class="table-secondary fw-bold"><td colspan="3">{{ __('PERBELANJAAN') }}</td></tr>
                @if ($mode === 'terperinci')
                    @foreach ($pl['belanja'] as $r)
                        <tr>
                            <td>{{ $r->kod }}</td>
                            <td class="ps-4">{{ $r->nama }}</td>
                            <td class="text-end">{!! $wang($r->amaun) !!}</td>
                        </tr>
                    @endforeach
                @endif
                <tr class="fw-bold">
                    <td colspan="2" class="text-end">{{ __('JUMLAH PERBELANJAAN') }}</td>
                    <td class="text-end">{!! $wang($pl['jumlah_belanja']) !!}</td>
                </tr>
            </tbody>
            <tfoot class="table-light">
                <tr class="fw-bold fs-6">
                    <td colspan="2" class="text-end">{{ __('LEBIHAN/(KURANGAN)') }}</td>
                    <td class="text-end">{!! $wang($pl['lebihan']) !!}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
