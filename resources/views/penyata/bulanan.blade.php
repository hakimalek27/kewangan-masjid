@extends('layouts.app')

@section('title', $bank ? __('Penyata Bulanan Ikut Bank') : __('Penyata Bulanan'))

@php
    $ikutBank = request()->routeIs('penyata.bank');
    $namaBulan = [1=>'Januari','Februari','Mac','April','Mei','Jun','Julai','Ogos','September','Oktober','November','Disember'];
    $tempoh = ($namaBulan[(int) substr($ym, 5, 2)] ?? '').' '.substr($ym, 0, 4);
    $cleanArgs = ['jenis'=>'bulanan', 'p'=>$p, 'pindahan'=>$pindahan, 'jumlahKiri'=>$jumlah_kiri, 'jumlahKanan'=>$jumlah_kanan, 'font'=>$font, 'noteMap'=>$noteMap];
@endphp

@section('content')
@include('penyata._cetak-css')

<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            @if ($ikutBank)
                <div class="col-md-3">
                    <x-bank-select name="bank_account_id" label="Bank" :selected="request('bank_account_id')" class="mb-0" />
                </div>
            @endif
            <x-period-filter />
            <div class="col-auto">
                <label class="form-label mb-0 small">{{ __('Saiz Font') }}</label>
                <select name="font" class="form-select">
                    <option value="kecil" @selected(request('font') === 'kecil')>{{ __('Kecil') }}</option>
                    <option value="sederhana" @selected(request('font', 'sederhana') === 'sederhana')>{{ __('Sederhana') }}</option>
                    <option value="besar" @selected(request('font') === 'besar')>{{ __('Besar') }}</option>
                    <option value="xbesar" @selected(request('font') === 'xbesar')>{{ __('Sangat Besar') }}</option>
                </select>
            </div>
            <div class="col-auto">
                <div class="form-check mt-4">
                    <input type="checkbox" class="form-check-input" id="nota" name="nota" value="1" @checked(request()->boolean('nota'))>
                    <label class="form-check-label small" for="nota">{{ __('Nota Program') }}</label>
                </div>
            </div>
            <div class="col-auto">
                <x-export-buttons />
            </div>
        </form>
        <div class="mt-2 d-flex align-items-center gap-2 flex-wrap">
            <span class="small text-muted">{{ __('Gaya paparan:') }}</span>
            <div class="btn-group btn-group-sm" role="group">
                <a href="{{ request()->fullUrlWithQuery(['gaya' => 'v1']) }}" class="btn {{ $gaya === 'v1' ? 'btn-primary' : 'btn-outline-secondary' }}">{{ __('V1 (Bersih)') }}</a>
                <a href="{{ request()->fullUrlWithQuery(['gaya' => 'semasa']) }}" class="btn {{ $gaya === 'semasa' ? 'btn-primary' : 'btn-outline-secondary' }}">{{ __('Semasa') }}</a>
            </div>
        </div>
    </div>
</div>

@if ($gaya === 'semasa')
    {{-- ===== Gaya SEMASA (Bootstrap berbingkai) — skrin sahaja ===== --}}
    <div class="card shadow-sm d-print-none" style="font-size: {{ $font }}">
        <div class="card-header text-center">
            <div class="fw-bold">{{ $namaMasjid }}</div>
            <div class="fw-bold">
                {{ __('PENYATA KEWANGAN BULANAN') }} @if ($bank) — {{ $bank->nama_bank }} ({{ $bank->no_akaun }}) @endif
            </div>
            <div>{{ __('Tempoh:') }} {{ $ym }}</div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                {{-- LAJUR KIRI : BUTIR TERIMAAN --}}
                <div class="col-md-6">
                    <table class="table table-sm table-bordered mb-0">
                        <thead><tr class="table-dark"><th colspan="2" class="text-center">{{ __('BUTIR TERIMAAN') }}</th></tr></thead>
                        <tbody>
                            <tr class="table-secondary fw-bold"><td colspan="2">{{ __('1. BAKI AWAL (B/B)') }}</td></tr>
                            @foreach ($p['baki_awal'] as $r)
                                <tr><td class="ps-4">{{ $r->kod }} {{ $r->nama }}</td><td class="text-end">{{ number_format((float) $r->baki, 2) }}</td></tr>
                            @endforeach
                            <tr class="fw-bold"><td class="text-end">{{ __('Jumlah Baki Awal') }}</td><td class="text-end">{{ number_format((float) $p['jumlah_baki_awal'], 2) }}</td></tr>

                            <tr class="table-secondary fw-bold"><td colspan="2">{{ __('2. TERIMAAN / KUTIPAN') }}</td></tr>
                            @forelse ($p['terimaan'] as $r)
                                <tr><td class="ps-4">{{ $r->kod }} {{ $r->nama }}@if (!empty($noteMap['T:'.$r->kod]))<sup class="fw-bold" style="font-size:0.72em;">{{ $noteMap['T:'.$r->kod] }}</sup>@endif</td><td class="text-end">{{ number_format((float) $r->jumlah, 2) }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="text-muted ps-4">{{ __('Tiada terimaan.') }}</td></tr>
                            @endforelse
                            <tr class="fw-bold"><td class="text-end">{{ __('Jumlah Terimaan') }}</td><td class="text-end">{{ number_format((float) $p['jumlah_terimaan'], 2) }}</td></tr>

                            <tr class="table-secondary fw-bold"><td colspan="2">{{ __('3. PELARASAN PINDAHAN PWR') }}</td></tr>
                            @forelse ($p['pindahan_pwr'] as $r)
                                <tr><td class="ps-4">{{ $r->kod }} {{ $r->nama }}</td><td class="text-end">{{ number_format((float) $r->jumlah, 2) }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="text-muted ps-4">{{ __('Tiada pindahan.') }}</td></tr>
                            @endforelse
                            <tr class="fw-bold"><td class="text-end">{{ __('Jumlah Pindahan PWR') }}</td><td class="text-end">{{ number_format((float) $pindahan, 2) }}</td></tr>
                        </tbody>
                        <tfoot>
                            <tr class="table-dark fw-bold"><td class="text-end">{{ __('JUMLAH') }}</td><td class="text-end">{{ number_format((float) $jumlah_kiri, 2) }}</td></tr>
                        </tfoot>
                    </table>
                </div>

                {{-- LAJUR KANAN : BUTIR PERBELANJAAN --}}
                <div class="col-md-6">
                    <table class="table table-sm table-bordered mb-0">
                        <thead><tr class="table-dark"><th colspan="2" class="text-center">{{ __('BUTIR PERBELANJAAN') }}</th></tr></thead>
                        <tbody>
                            <tr class="table-secondary fw-bold"><td colspan="2">{{ __('1. PERBELANJAAN') }}</td></tr>
                            @forelse ($p['belanja'] as $r)
                                <tr><td class="ps-4">{{ $r->kod }} {{ $r->nama }}@if (!empty($noteMap['B:'.$r->kod]))<sup class="fw-bold" style="font-size:0.72em;">{{ $noteMap['B:'.$r->kod] }}</sup>@endif</td><td class="text-end">{{ number_format((float) $r->jumlah, 2) }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="text-muted ps-4">{{ __('Tiada perbelanjaan.') }}</td></tr>
                            @endforelse
                            <tr class="fw-bold"><td class="text-end">{{ __('Jumlah Perbelanjaan') }}</td><td class="text-end">{{ number_format((float) $p['jumlah_belanja'], 2) }}</td></tr>

                            <tr class="table-secondary fw-bold"><td colspan="2">{{ __('2. BAKI AKHIR (B/H)') }}</td></tr>
                            @foreach ($p['baki_akhir'] as $r)
                                <tr><td class="ps-4">{{ $r->kod }} {{ $r->nama }}</td><td class="text-end">{{ number_format((float) $r->baki, 2) }}</td></tr>
                            @endforeach
                            <tr class="fw-bold"><td class="text-end">{{ __('Jumlah Baki Akhir') }}</td><td class="text-end">{{ number_format((float) $p['jumlah_baki_akhir'], 2) }}</td></tr>

                            <tr class="table-secondary fw-bold"><td colspan="2">{{ __('3. PELARASAN PINDAHAN PWR (KONTRA)') }}</td></tr>
                            <tr class="fw-bold"><td class="text-end">{{ __('Jumlah Pindahan PWR') }}</td><td class="text-end">{{ number_format((float) $pindahan, 2) }}</td></tr>
                        </tbody>
                        <tfoot>
                            <tr class="table-dark fw-bold"><td class="text-end">{{ __('JUMLAH') }}</td><td class="text-end">{{ number_format((float) $jumlah_kanan, 2) }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="mt-3 text-center">
                @if ($jumlah_kiri === $jumlah_kanan)
                    <span class="badge text-bg-success">{{ __('SEIMBANG') }} &#10004; — {{ number_format((float) $jumlah_kiri, 2) }} = {{ number_format((float) $jumlah_kanan, 2) }}</span>
                @else
                    <span class="badge text-bg-danger">{{ __('TIDAK SEIMBANG') }} — {{ number_format((float) $jumlah_kiri, 2) }} &ne; {{ number_format((float) $jumlah_kanan, 2) }}</span>
                @endif
            </div>
            @if ($nota)
                @include('penyata._nota-kaki', ['notaList' => $notaList, 'font' => $font])
                @include('penyata._nota-program', ['nota' => $notaData, 'font' => $font])
            @endif
        </div>
    </div>
@endif

{{-- ===== Gaya V1 (bersih) — skrin (bila dipilih) + SENTIASA untuk cetak ===== --}}
<div class="card shadow-sm penyata-cetak {{ $gaya === 'semasa' ? 'd-none d-print-block' : '' }}" style="font-size: {{ $font }}">
    <div class="card-header text-center">
        <div class="fw-bold">{{ $namaMasjid }}</div>
        <div class="fw-bold">
            {{ __('PENYATA RINGKASAN TERIMAAN & PERBELANJAAN') }} @if ($bank) — {{ $bank->nama_bank }} ({{ $bank->no_akaun }}) @endif
        </div>
        <div>{{ __('BAGI BULAN:') }} {{ $tempoh }}</div>
    </div>
    <div class="card-body">
        @include('penyata._penyata2lajur', $cleanArgs)
        @if ($nota)
            @include('penyata._nota-kaki', ['notaList' => $notaList, 'font' => $font])
            @include('penyata._nota-program', ['nota' => $notaData, 'font' => $font])
        @endif
    </div>
</div>
@endsection
