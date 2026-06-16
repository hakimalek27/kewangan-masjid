@extends('layouts.app')

@section('title', __('Baki Di Tangan PWR'))

@php
    $namaBulan = [1=>'Januari','Februari','Mac','April','Mei','Jun','Julai','Ogos','September','Oktober','November','Disember'];
@endphp

@section('content')
<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <x-coa-select name="pwr_coa_id" :julat="['250-06']" :required="false"
                              placeholder="-- Pilih Akaun PWR --" :selected="request('pwr_coa_id', $coa?->id)" class="mb-0" />
            </div>
            <x-period-filter :bulan="false" />
            <div class="col-auto">
                <x-export-buttons :eksport="false" />
            </div>
        </form>
    </div>
</div>

@if (! $coa)
    <div class="alert alert-info">{{ __('Tiada akaun PWR (julat 250-06) ditemui.') }}</div>
@else
    <div class="card shadow-sm">
        <div class="card-header fw-bold">{{ __('Baki Di Tangan PWR') }} — {{ $coa->kod }} {{ $coa->nama }} ({{ __('Tahun') }} {{ $tahun }})</div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-bordered table-hover w-auto">
                <thead class="table-light">
                    <tr><th style="min-width:200px">{{ __('Bulan') }}</th><th class="text-end" style="min-width:160px">{{ __('Baki Akhir (RM)') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($baki as $ym => $b)
                        <tr>
                            <td>{{ __($namaBulan[(int) substr($ym, 5, 2)]) }} {{ $tahun }}</td>
                            <td class="text-end {{ (float) $b < 0 ? 'text-danger' : '' }}">{{ number_format((float) $b, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
