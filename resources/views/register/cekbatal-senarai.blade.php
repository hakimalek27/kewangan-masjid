@extends('layouts.app')

@section('title', __('Laporan Cek Batal'))

@section('content')
@php
    $namaBulan = [1=>'Januari','Februari','Mac','April','Mei','Jun','Julai','Ogos','September','Oktober','November','Disember'];
@endphp

<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" action="{{ route('cekbatal.senarai') }}" class="row g-2 align-items-end">
            <div class="col-auto">
                <select name="m" class="form-select">
                    @foreach ($namaBulan as $i => $nama)
                        <option value="{{ $i }}" @selected($bulan === $i)>{{ __($nama) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <select name="y" class="form-select">
                    @for ($t = 2030; $t >= 2020; $t--)
                        <option value="{{ $t }}" @selected($tahun === $t)>{{ $t }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">{{ __('Cari') }}</button>
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>{{ __('Cetak') }}
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Laporan Cek Batal') }} — {{ isset($namaBulan[$bulan]) ? __($namaBulan[$bulan]) : $bulan }} {{ $tahun }}</div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover table-bordered">
            <thead class="table-light">
                <tr>
                    <th>{{ __('No. Cek Batal') }}</th>
                    <th>{{ __('Tarikh Batal') }}</th>
                    <th>{{ __('Penerima') }}</th>
                    <th class="text-end">{{ __('Amaun (RM)') }}</th>
                    <th>{{ __('Sebab Batal') }}</th>
                    <th>{{ __('Pengesahan Oleh') }}</th>
                    <th>{{ __('No. Cek Ganti') }}</th>
                    <th>{{ __('Tarikh Ganti') }}</th>
                    <th>{{ __('Catatan') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($senarai as $c)
                    <tr>
                        <td>{{ $c->no_cek_batal }}</td>
                        <td>{{ $c->tarikh_batal?->format('d/m/Y') }}</td>
                        <td>{{ $c->penerima ?: '—' }}</td>
                        <td class="text-end">{{ number_format((float) $c->amaun, 2) }}</td>
                        <td>{{ $c->sebab_batal ?: '—' }}</td>
                        <td>{{ $c->pengesahan_oleh ?: '—' }}</td>
                        <td>{{ $c->no_cek_ganti ?: '—' }}</td>
                        <td>{{ $c->tarikh_ganti?->format('d/m/Y') ?: '—' }}</td>
                        <td>{{ $c->catatan ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted">{{ __('Tiada cek batal bagi tempoh ini.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
