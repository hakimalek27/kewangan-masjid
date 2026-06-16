<!DOCTYPE html>
<html lang="ms">
<head><meta charset="utf-8"><title>Penyata Bulanan</title></head>
<body>
@php
    $namaBulan = [1=>'Januari','Februari','Mac','April','Mei','Jun','Julai','Ogos','September','Oktober','November','Disember'];
    $tempohTeks = ($namaBulan[(int) substr($ym, 5, 2)] ?? '').' '.substr($ym, 0, 4);
@endphp
@include('pdf._kepala', [
    'tajukLaporan' => 'PENYATA RINGKASAN TERIMAAN & PERBELANJAAN'.($bank ? ' — '.$bank->nama_bank.' ('.$bank->no_akaun.')' : ''),
    'tempoh'       => 'BAGI BULAN: '.$tempohTeks,
])

{{-- Reset border global _kepala untuk blok penyata bersih (gaya inline kekal) --}}
<style>.bersih td, .bersih th { border: 0; }</style>
<div class="bersih">
    @include('penyata._penyata2lajur', [
        'jenis' => 'bulanan', 'p' => $p, 'pindahan' => $pindahan,
        'jumlahKiri' => $jumlah_kiri, 'jumlahKanan' => $jumlah_kanan, 'font' => '12px',
        'noteMap' => ($noteMap ?? []),
    ])
    @if (($nota ?? false) && isset($notaData))
        @include('penyata._nota-kaki', ['notaList' => ($notaList ?? []), 'font' => '12px'])
        @include('penyata._nota-program', ['nota' => $notaData, 'font' => '12px'])
    @endif
</div>
</body>
</html>
