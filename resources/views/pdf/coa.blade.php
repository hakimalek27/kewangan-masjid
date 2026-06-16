<!DOCTYPE html>
<html lang="ms">
<head><meta charset="utf-8"><title>Carta Akaun</title></head>
<body>
@include('pdf._kepala', [
    'tajukLaporan' => 'CARTA AKAUN (CHART OF ACCOUNTS)',
    'tempoh'       => 'Senarai penuh akaun',
])

<table>
    <thead>
        <tr><th>Kod</th><th>Nama Akaun</th><th>Jenis</th><th>Baki Normal</th></tr>
    </thead>
    <tbody>
        @foreach ($senarai as $c)
            <tr @class(['seksyen' => $c->is_header])>
                <td>{{ $c->kod }}</td>
                <td>{{ $c->nama }}</td>
                <td>{{ $c->jenis }}</td>
                <td>{{ $c->is_header ? '—' : $c->normal_balance }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
