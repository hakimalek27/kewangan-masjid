@extends('layouts.app')

@section('title', __('Penyata Tahunan'))

@php
    $namaBulan = [1=>'Januari','Februari','Mac','April','Mei','Jun','Julai','Ogos','September','Oktober','November','Disember'];
    $font = '12px';
    $cleanArgs = ['jenis'=>'tahunan', 'p'=>$ps, 'jumlahKiri'=>$ps['jumlah_kiri'], 'jumlahKanan'=>$ps['jumlah_kanan'], 'tahun'=>$tahun, 'font'=>$font, 'noteMap'=>$noteMap];
@endphp

@section('content')
@include('penyata._cetak-css')

<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <x-period-filter :bulan="false" />
            <div class="col-auto">
                <div class="form-check mt-4">
                    <input type="checkbox" class="form-check-input" id="grid" name="grid" value="1" @checked(request()->boolean('grid'))>
                    <label class="form-check-label small" for="grid">{{ __('Grid 12-bulan') }}</label>
                </div>
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
    <div class="card shadow-sm mb-4 d-print-none">
        <div class="card-header text-center">
            <div class="fw-bold">{{ $namaMasjid }}</div>
            <div class="fw-bold">{{ __('PENYATA TERIMAAN & PERBELANJAAN (TAHUNAN)') }}</div>
            <div>{{ __('BAGI TAHUN BERAKHIR: 31 DISEMBER') }} {{ $tahun }}</div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <table class="table table-sm table-bordered mb-0">
                        <thead><tr class="table-dark"><th colspan="2" class="text-center">{{ __('BUTIR TERIMAAN (RM)') }}</th></tr></thead>
                        <tbody>
                            <tr class="table-secondary fw-bold"><td colspan="2">{{ __('1. BAKI AWAL') }} (01/01/{{ $tahun }})</td></tr>
                            @foreach ($ps['baki_awal'] as $r)
                                <tr><td class="ps-4">{{ $r->kod }} {{ $r->nama }}</td><td class="text-end">{{ number_format((float) $r->baki, 2) }}</td></tr>
                            @endforeach
                            <tr class="fw-bold"><td class="text-end">{{ __('Jumlah Baki Awal') }}</td><td class="text-end">{{ number_format((float) $ps['jumlah_baki_awal'], 2) }}</td></tr>

                            <tr class="table-secondary fw-bold"><td colspan="2">{{ __('2. TERIMAAN TAHUN') }} {{ $tahun }}</td></tr>
                            @forelse ($ps['terimaan'] as $r)
                                <tr><td class="ps-4">{{ $r->kod }} {{ $r->nama }}@if (!empty($noteMap['T:'.$r->kod]))<sup class="fw-bold" style="font-size:0.72em;">{{ $noteMap['T:'.$r->kod] }}</sup>@endif</td><td class="text-end">{{ number_format((float) $r->jumlah, 2) }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="text-muted ps-4">{{ __('Tiada terimaan.') }}</td></tr>
                            @endforelse
                            <tr class="fw-bold"><td class="text-end">{{ __('Jumlah Terimaan') }}</td><td class="text-end">{{ number_format((float) $ps['jumlah_terimaan'], 2) }}</td></tr>

                            <tr class="table-secondary fw-bold"><td colspan="2">{{ __('PELARASAN PINDAHAN TUNAI (KONTRA)') }}</td></tr>
                            <tr class="fw-bold"><td class="text-end">{{ __('Jumlah Pindahan PWR') }}</td><td class="text-end">{{ number_format((float) $ps['jumlah_pindahan'], 2) }}</td></tr>
                        </tbody>
                        <tfoot><tr class="table-dark fw-bold"><td class="text-end">{{ __('JUMLAH') }}</td><td class="text-end">{{ number_format((float) $ps['jumlah_kiri'], 2) }}</td></tr></tfoot>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm table-bordered mb-0">
                        <thead><tr class="table-dark"><th colspan="2" class="text-center">{{ __('BUTIR PERBELANJAAN (RM)') }}</th></tr></thead>
                        <tbody>
                            <tr class="table-secondary fw-bold"><td colspan="2">{{ __('1. PERBELANJAAN TAHUN') }} {{ $tahun }}</td></tr>
                            @forelse ($ps['belanja'] as $r)
                                <tr><td class="ps-4">{{ $r->kod }} {{ $r->nama }}@if (!empty($noteMap['B:'.$r->kod]))<sup class="fw-bold" style="font-size:0.72em;">{{ $noteMap['B:'.$r->kod] }}</sup>@endif</td><td class="text-end">{{ number_format((float) $r->jumlah, 2) }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="text-muted ps-4">{{ __('Tiada perbelanjaan.') }}</td></tr>
                            @endforelse
                            <tr class="fw-bold"><td class="text-end">{{ __('Jumlah Perbelanjaan') }}</td><td class="text-end">{{ number_format((float) $ps['jumlah_belanja'], 2) }}</td></tr>

                            <tr class="table-secondary fw-bold"><td colspan="2">{{ __('2. BAKI AKHIR') }} (31/12/{{ $tahun }})</td></tr>
                            @foreach ($ps['baki_akhir'] as $r)
                                <tr><td class="ps-4">{{ $r->kod }} {{ $r->nama }}</td><td class="text-end">{{ number_format((float) $r->baki, 2) }}</td></tr>
                            @endforeach
                            <tr class="fw-bold"><td class="text-end">{{ __('Jumlah Baki Akhir') }}</td><td class="text-end">{{ number_format((float) $ps['jumlah_baki_akhir'], 2) }}</td></tr>

                            <tr class="table-secondary fw-bold"><td colspan="2">{{ __('PELARASAN PINDAHAN TUNAI (KONTRA)') }}</td></tr>
                            <tr class="fw-bold"><td class="text-end">{{ __('Jumlah Pindahan PWR') }}</td><td class="text-end">{{ number_format((float) $ps['jumlah_pindahan'], 2) }}</td></tr>
                        </tbody>
                        <tfoot><tr class="table-dark fw-bold"><td class="text-end">{{ __('JUMLAH') }}</td><td class="text-end">{{ number_format((float) $ps['jumlah_kanan'], 2) }}</td></tr></tfoot>
                    </table>
                </div>
            </div>
            <div class="mt-3 text-center">
                @if ($ps['jumlah_kiri'] === $ps['jumlah_kanan'])
                    <span class="badge text-bg-success">{{ __('SEIMBANG') }} &#10004; — {{ number_format((float) $ps['jumlah_kiri'], 2) }} = {{ number_format((float) $ps['jumlah_kanan'], 2) }}</span>
                @else
                    <span class="badge text-bg-danger">{{ __('TIDAK SEIMBANG') }} — {{ number_format((float) $ps['jumlah_kiri'], 2) }} &ne; {{ number_format((float) $ps['jumlah_kanan'], 2) }}</span>
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
<div class="card shadow-sm mb-4 penyata-cetak {{ $gaya === 'semasa' ? 'd-none d-print-block' : '' }}" style="font-size: {{ $font }}">
    <div class="card-header text-center">
        <div class="fw-bold">{{ $namaMasjid }}</div>
        <div class="fw-bold">{{ __('PENYATA TERIMAAN & PERBELANJAAN (TAHUNAN)') }}</div>
        <div>{{ __('BAGI TAHUN BERAKHIR: 31 DISEMBER') }} {{ $tahun }}</div>
    </div>
    <div class="card-body">
        @include('penyata._penyata2lajur', $cleanArgs)
        @if ($nota)
            @include('penyata._nota-kaki', ['notaList' => $notaList, 'font' => $font])
            @include('penyata._nota-program', ['nota' => $notaData, 'font' => $font])
        @endif
    </div>
</div>

{{-- ===== Grid Ringkasan Bulanan (pilihan: ?grid=1) ===== --}}
@if ($grid)
    <div class="card shadow-sm penyata-cetak">
        <div class="card-header fw-bold">{{ __('Ringkasan Bulanan') }} {{ $tahun }}</div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Bulan') }}</th>
                        <th class="text-end">{{ __('Terima (RM)') }}</th>
                        <th class="text-end">{{ __('Bayar Bank (RM)') }}</th>
                        <th class="text-end">{{ __('Bayar PWR (RM)') }}</th>
                        <th class="text-end">{{ __('Bayar Tunai (RM)') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($t['bulanan'] as $ym => $r)
                        <tr>
                            <td>{{ __($namaBulan[(int) substr($ym, 5, 2)]) }} {{ $tahun }}</td>
                            <td class="text-end">{{ number_format((float) $r['terima'], 2) }}</td>
                            <td class="text-end">{{ number_format((float) $r['bayar_bank'], 2) }}</td>
                            <td class="text-end">{{ number_format((float) $r['bayar_pwr'], 2) }}</td>
                            <td class="text-end">{{ number_format((float) $r['bayar_tunai'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light">
                    <tr class="fw-bold">
                        <th class="text-end">{{ __('JUMLAH') }}</th>
                        <th class="text-end">{{ number_format((float) $t['jumlah']['terima'], 2) }}</th>
                        <th class="text-end">{{ number_format((float) $t['jumlah']['bayar_bank'], 2) }}</th>
                        <th class="text-end">{{ number_format((float) $t['jumlah']['bayar_pwr'], 2) }}</th>
                        <th class="text-end">{{ number_format((float) $t['jumlah']['bayar_tunai'], 2) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endif
@endsection
