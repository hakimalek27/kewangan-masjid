<!DOCTYPE html>
<html lang="ms">
<head><meta charset="utf-8"><title>Penyata Tahunan</title></head>
<body>
@php
    $namaBulan = [1=>'Januari','Februari','Mac','April','Mei','Jun','Julai','Ogos','September','Oktober','November','Disember'];
@endphp
@include('pdf._kepala', [
    'tajukLaporan' => 'PENYATA TERIMAAN & PERBELANJAAN (TAHUNAN)',
    'tempoh'       => 'BAGI TAHUN BERAKHIR: 31 DISEMBER '.$tahun,
])

{{-- Reset border global _kepala untuk blok penyata bersih (gaya inline kekal) --}}
<style>.bersih td, .bersih th { border: 0; }</style>
<div class="bersih">
    @include('penyata._penyata2lajur', [
        'jenis' => 'tahunan', 'p' => $ps,
        'jumlahKiri' => $ps['jumlah_kiri'], 'jumlahKanan' => $ps['jumlah_kanan'],
        'tahun' => $tahun, 'font' => '12px', 'noteMap' => ($noteMap ?? []),
    ])
    @if (($nota ?? false) && isset($notaData))
        @include('penyata._nota-kaki', ['notaList' => ($notaList ?? []), 'font' => '12px'])
        @include('penyata._nota-program', ['nota' => $notaData, 'font' => '12px'])
    @endif
</div>

@if (($grid ?? false) && isset($t))
    <div style="margin-top:16px;">
        <div style="font-weight:bold; border-bottom:1.5px solid #222; padding-bottom:3px; margin-bottom:6px;">
            Ringkasan Bulanan {{ $tahun }}
        </div>
        <table style="width:100%; border-collapse:collapse; font-size:11px;">
            <thead>
                <tr>
                    <th>Bulan</th><th class="end">Terima (RM)</th><th class="end">Bayar Bank (RM)</th>
                    <th class="end">Bayar PWR (RM)</th><th class="end">Bayar Tunai (RM)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($t['bulanan'] as $ymb => $r)
                    <tr>
                        <td>{{ ($namaBulan[(int) substr($ymb, 5, 2)] ?? '') }} {{ $tahun }}</td>
                        <td class="end">{{ number_format((float) $r['terima'], 2) }}</td>
                        <td class="end">{{ number_format((float) $r['bayar_bank'], 2) }}</td>
                        <td class="end">{{ number_format((float) $r['bayar_pwr'], 2) }}</td>
                        <td class="end">{{ number_format((float) $r['bayar_tunai'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="jumlah">
                    <td class="end">JUMLAH</td>
                    <td class="end">{{ number_format((float) $t['jumlah']['terima'], 2) }}</td>
                    <td class="end">{{ number_format((float) $t['jumlah']['bayar_bank'], 2) }}</td>
                    <td class="end">{{ number_format((float) $t['jumlah']['bayar_pwr'], 2) }}</td>
                    <td class="end">{{ number_format((float) $t['jumlah']['bayar_tunai'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
@endif
</body>
</html>
