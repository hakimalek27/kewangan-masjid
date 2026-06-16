<!DOCTYPE html>
<html lang="ms">
<head><meta charset="utf-8"><title>Laporan Mengikut Program</title></head>
<body>
@php
    $wang = fn ($v) => (float) $v < 0
        ? '<span class="merah">('.number_format(abs((float) $v), 2).')</span>'
        : number_format((float) $v, 2);
@endphp
@include('pdf._kepala', [
    'tajukLaporan' => 'LAPORAN MENGIKUT PROGRAM',
    'tempoh'       => ($dari || $hingga) ? (($dari ?? 'awal').' hingga '.($hingga ?? 'kini')) : 'Keseluruhan',
])

<table>
    <thead>
        <tr><th>Program</th><th class="end">Terima (RM)</th><th class="end">Belanja (RM)</th><th class="end">Net (RM)</th></tr>
    </thead>
    <tbody>
        @foreach ($program as $p)
            <tr>
                <td>{{ $p->program }}</td>
                <td class="end">{{ number_format((float) $p->terima, 2) }}</td>
                <td class="end">{{ number_format((float) $p->belanja, 2) }}</td>
                <td class="end">{!! $wang($p->net) !!}</td>
            </tr>
        @endforeach
        <tr class="jumlah">
            <td class="end">JUMLAH</td>
            <td class="end">{{ number_format((float) $jumlah['terima'], 2) }}</td>
            <td class="end">{{ number_format((float) $jumlah['belanja'], 2) }}</td>
            <td class="end">{!! $wang($jumlah['net']) !!}</td>
        </tr>
    </tbody>
</table>
</body>
</html>
