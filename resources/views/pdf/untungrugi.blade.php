<!DOCTYPE html>
<html lang="ms">
<head><meta charset="utf-8"><title>Penyata Untung Rugi</title></head>
<body>
@php
    $wang = fn ($v) => (float) $v < 0
        ? '<span class="merah">('.number_format(abs((float) $v), 2).')</span>'
        : number_format((float) $v, 2);
@endphp
@include('pdf._kepala', [
    'tajukLaporan' => 'PENYATA UNTUNG RUGI ('.strtoupper($mode).')',
    'tempoh'       => $dari.' hingga '.$hingga,
])

<table>
    <thead>
        <tr><th>Kod</th><th>Butiran</th><th class="end">Amaun (RM)</th></tr>
    </thead>
    <tbody>
        <tr class="seksyen"><td colspan="3">PENDAPATAN (HASIL)</td></tr>
        @if ($mode === 'terperinci')
            @foreach ($pl['hasil'] as $r)
                <tr><td>{{ $r->kod }}</td><td>{{ $r->nama }}</td><td class="end">{!! $wang($r->amaun) !!}</td></tr>
            @endforeach
        @endif
        <tr class="jumlah"><td colspan="2" class="end">JUMLAH PENDAPATAN</td><td class="end">{!! $wang($pl['jumlah_hasil']) !!}</td></tr>

        <tr class="seksyen"><td colspan="3">PERBELANJAAN</td></tr>
        @if ($mode === 'terperinci')
            @foreach ($pl['belanja'] as $r)
                <tr><td>{{ $r->kod }}</td><td>{{ $r->nama }}</td><td class="end">{!! $wang($r->amaun) !!}</td></tr>
            @endforeach
        @endif
        <tr class="jumlah"><td colspan="2" class="end">JUMLAH PERBELANJAAN</td><td class="end">{!! $wang($pl['jumlah_belanja']) !!}</td></tr>

        <tr class="jumlah"><td colspan="2" class="end">LEBIHAN/(KURANGAN)</td><td class="end">{!! $wang($pl['lebihan']) !!}</td></tr>
    </tbody>
</table>
</body>
</html>
