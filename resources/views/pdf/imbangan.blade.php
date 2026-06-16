<!DOCTYPE html>
<html lang="ms">
<head><meta charset="utf-8"><title>Imbangan Duga</title></head>
<body>
@include('pdf._kepala', [
    'tajukLaporan' => 'IMBANGAN DUGA',
    'tempoh'       => 'Sehingga '.$ym.' — '.($tb['jumlah_debit'] === $tb['jumlah_kredit'] ? 'SEIMBANG' : 'TIDAK SEIMBANG'),
])

<table>
    <thead>
        <tr><th>Kod</th><th>Nama Akaun</th><th class="end">Debit (RM)</th><th class="end">Kredit (RM)</th></tr>
    </thead>
    <tbody>
        @foreach ($tb['baris'] as $r)
            <tr>
                <td>{{ $r->kod }}</td>
                <td>{{ $r->nama }}</td>
                <td class="end">{{ $r->debit !== '0.00' ? number_format((float) $r->debit, 2) : '' }}</td>
                <td class="end">{{ $r->kredit !== '0.00' ? number_format((float) $r->kredit, 2) : '' }}</td>
            </tr>
        @endforeach
        <tr class="jumlah">
            <td colspan="2" class="end">JUMLAH</td>
            <td class="end">{{ number_format((float) $tb['jumlah_debit'], 2) }}</td>
            <td class="end">{{ number_format((float) $tb['jumlah_kredit'], 2) }}</td>
        </tr>
    </tbody>
</table>
</body>
</html>
