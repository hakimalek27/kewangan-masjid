@extends('layouts.app')

@section('title', __('Baki Terkini'))

@section('content')
@php
    $namaBulan = [1=>'Januari','Februari','Mac','April','Mei','Jun','Julai','Ogos','September','Oktober','November','Disember'];
@endphp

<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" action="{{ route('bank.baki') }}" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-0" for="bank_id">{{ __('Bank') }}</label>
                <select name="bank_id" id="bank_id" class="form-select">
                    @foreach ($banks as $b)
                        <option value="{{ $b->id }}" @selected($bank?->id === $b->id)>
                            {{ __('Slot') }} {{ $b->slot }} : {{ $b->nama_bank }} ({{ $b->no_akaun }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-0" for="tahun">{{ __('Tahun') }}</label>
                <select name="tahun" id="tahun" class="form-select">
                    @for ($t = now()->year + 1; $t >= 2020; $t--)
                        <option value="{{ $t }}" @selected($tahun === $t)>{{ $t }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">{{ __('Papar Laporan') }}</button>
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>{{ __('Cetak') }}
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-bold">
        {{ __('Baki Bulanan') }} {{ $tahun }} @if ($bank) — {{ $bank->nama_bank }} ({{ $bank->no_akaun }}) @endif
    </div>
    <div class="card-body table-responsive">
        @if (!$bank)
            <p class="text-muted mb-0">{{ __('Tiada akaun bank didaftarkan. Sila daftar bank dahulu di Setting Bank.') }}</p>
        @else
            <table class="table table-sm table-hover table-bordered align-middle" style="max-width:520px">
                <thead class="table-light">
                    <tr><th>{{ __('Bulan') }}</th><th class="text-end">{{ __('Baki (RM)') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($bulanan as $bln => $baki)
                        <tr>
                            <td>{{ __($namaBulan[$bln]) }} {{ $tahun }}</td>
                            <td class="text-end">{{ number_format((float) $baki, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="small text-muted">{{ __('Baki dikira terus daripada jurnal (POSTED) sehingga akhir setiap bulan.') }}</div>
        @endif
    </div>
</div>
@endsection
