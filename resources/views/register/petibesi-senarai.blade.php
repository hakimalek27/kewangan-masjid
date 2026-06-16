@extends('layouts.app')

@section('title', __('Senarai Peti Besi'))

@section('content')
@php
    $namaBulan = [1=>'Januari','Februari','Mac','April','Mei','Jun','Julai','Ogos','September','Oktober','November','Disember'];
@endphp

<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" action="{{ route('petibesi.senarai') }}" class="row g-2 align-items-end">
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
    <div class="card-header fw-bold">{{ __('Senarai Peti Besi') }} — {{ isset($namaBulan[$bulan]) ? __($namaBulan[$bulan]) : $bulan }} {{ $tahun }}</div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover table-bordered">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Perkara') }}</th>
                    <th>{{ __('Nama Pemasuk') }}</th>
                    <th>{{ __('Tarikh Masuk') }}</th>
                    <th>{{ __('Nama Pengeluar') }}</th>
                    <th>{{ __('Tarikh Keluar') }}</th>
                    <th>{{ __('Catatan') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($senarai as $p)
                    <tr>
                        <td>{{ $p->perkara }}</td>
                        <td>{{ $p->nama_masuk }}</td>
                        <td>{{ $p->tarikh_masuk?->format('d/m/Y') }}</td>
                        <td>{{ $p->nama_keluar ?: '—' }}</td>
                        <td>{{ $p->tarikh_keluar?->format('d/m/Y') ?: '—' }}</td>
                        <td>{{ $p->catatan ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">{{ __('Tiada rekod peti besi bagi tempoh ini.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
