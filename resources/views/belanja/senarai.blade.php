@extends('layouts.app')

@section('title', __('Buku Tunai Pembayaran'))

@section('content')
@php
    $bolehTulis = (bool) auth()->user()?->bolehTulis();
@endphp

<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" action="{{ route('belanja.senarai') }}" class="row g-2 align-items-end">
            <x-period-filter />
            <div class="col-md-4">
                <x-coa-select name="coa_id" :required="false" :julat="['200','300','600','650']"
                              placeholder="-- Semua Kategori --" :selected="request('coa_id')" class="mb-0" />
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">{{ __('Papar') }}</button>
                <a href="{{ route('belanja.senarai') }}" class="btn btn-outline-secondary">{{ __('Reset') }}</a>
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>{{ __('Cetak') }}
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Buku Tunai Pembayaran') }} — {{ __('Tempoh') }} {{ $periodYm }}</div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover table-bordered">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Tarikh') }}</th>
                    <th>{{ __('Butiran / Penerima') }}</th>
                    <th>{{ __('No. Baucer / Cek') }}</th>
                    <th>{{ __('Kategori') }}</th>
                    <th class="text-end">{{ __('Jumlah (RM)') }}</th>
                    <th class="no-print" style="width:130px">{{ __('Tindakan') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($senarai as $p)
                    <tr>
                        <td>{{ $p->tar_lulus?->format('d/m/Y') }}</td>
                        <td>{{ $p->deskripsi ?: $p->pemohon ?: '—' }}</td>
                        <td>{{ $p->baucer_no }}@if($p->no_cek) / {{ $p->no_cek }}@endif</td>
                        <td>{{ $p->coa?->kod }} {{ $p->coa?->nama }}</td>
                        <td class="text-end">{{ number_format((float) $p->jumlah, 2) }}</td>
                        <td class="no-print">
                            <a href="{{ route('belanja.view', $p) }}" class="btn btn-sm btn-outline-primary">{{ __('Lihat') }}</a>
                            @if ($bolehTulis)
                                <form method="POST" action="{{ route('belanja.padam', $p) }}" class="d-inline"
                                      onsubmit="return confirm('{{ __('Padam pembayaran') }} {{ $p->baucer_no }}? {{ __('Jurnal berkaitan akan turut dibatalkan.') }}')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Padam') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">{{ __('Tiada pembayaran bagi tempoh ini.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="4" class="text-end">{{ __('JUMLAH KESELURUHAN (RM)') }}</th>
                    <th class="text-end">{{ number_format($jumlah, 2) }}</th>
                    <th class="no-print"></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
