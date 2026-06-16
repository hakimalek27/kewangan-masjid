<!DOCTYPE html>
<html lang="ms">
<head><meta charset="utf-8"><title>Kunci Kira-Kira</title></head>
<body>
@php
    $wang = fn ($v) => (float) $v < 0
        ? '<span class="merah">('.number_format(abs((float) $v), 2).')</span>'
        : number_format((float) $v, 2);
@endphp
@include('pdf._kepala', [
    'tajukLaporan' => 'KUNCI KIRA-KIRA',
    'tempoh'       => 'Pada '.$ym.' — '.($bs['seimbang'] ? 'SEIMBANG' : 'TIDAK SEIMBANG'),
])

<table>
    <thead>
        <tr><th>Kod</th><th>Butiran</th><th class="end">Amaun (RM)</th></tr>
    </thead>
    <tbody>
        <tr class="seksyen"><td colspan="3">ASET</td></tr>
        @foreach ($bs['aset'] as $r)
            <tr><td>{{ $r->kod }}</td><td>{{ $r->nama }}</td><td class="end">{!! $wang($r->amaun) !!}</td></tr>
        @endforeach
        <tr class="jumlah"><td colspan="2" class="end">JUMLAH ASET</td><td class="end">{!! $wang($bs['total_aset']) !!}</td></tr>

        <tr class="seksyen"><td colspan="3">LIABILITI</td></tr>
        @foreach ($bs['liabiliti'] as $r)
            <tr><td>{{ $r->kod }}</td><td>{{ $r->nama }}</td><td class="end">{!! $wang($r->amaun) !!}</td></tr>
        @endforeach
        <tr class="jumlah"><td colspan="2" class="end">JUMLAH LIABILITI</td><td class="end">{!! $wang($bs['total_liabiliti']) !!}</td></tr>

        <tr class="seksyen"><td colspan="3">EKUITI</td></tr>
        @foreach ($bs['ekuiti'] as $r)
            <tr><td>{{ $r->kod }}</td><td>{{ $r->nama }}</td><td class="end">{!! $wang($r->amaun) !!}</td></tr>
        @endforeach
        <tr><td></td><td>Lebihan/(Kurangan) Terkumpul</td><td class="end">{!! $wang($bs['lebihan_terkumpul']) !!}</td></tr>
        <tr class="jumlah"><td colspan="2" class="end">JUMLAH EKUITI</td><td class="end">{!! $wang($bs['total_ekuiti']) !!}</td></tr>

        <tr class="jumlah"><td colspan="2" class="end">JUMLAH LIABILITI + EKUITI</td>
            <td class="end">{!! $wang((float) $bs['total_liabiliti'] + (float) $bs['total_ekuiti']) !!}</td></tr>
    </tbody>
</table>
</body>
</html>
