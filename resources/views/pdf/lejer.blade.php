<!DOCTYPE html>
<html lang="ms">
<head><meta charset="utf-8"><title>{{ $tajuk }}</title></head>
<body>
@include('pdf._kepala', [
    'tajukLaporan' => mb_strtoupper($tajuk),
    'tempoh'       => ($coa ? $coa->kod.' '.$coa->nama.' — ' : '').$dari.' hingga '.$hingga,
])

<table>
    <thead>
        <tr>
            <th>Tarikh</th><th>Ref</th><th>Keterangan</th>
            <th class="end">Debit (RM)</th><th class="end">Kredit (RM)</th><th class="end">Baki (RM)</th>
        </tr>
    </thead>
    <tbody>
        <tr class="seksyen">
            <td colspan="5">BAKI AWAL (B/B)</td>
            <td class="end">{{ number_format((float) $lejer['baki_awal'], 2) }}</td>
        </tr>
        @foreach ($lejer['baris'] as $r)
            <tr>
                <td>{{ \Illuminate\Support\Carbon::parse($r->tarikh)->format('d/m/Y') }}</td>
                <td>{{ $r->voucher_ref }}</td>
                <td>{{ $r->deskripsi ?: $r->memo }}</td>
                <td class="end">{{ (float) $r->debit > 0 ? number_format((float) $r->debit, 2) : '' }}</td>
                <td class="end">{{ (float) $r->kredit > 0 ? number_format((float) $r->kredit, 2) : '' }}</td>
                <td class="end">{{ number_format((float) $r->baki, 2) }}</td>
            </tr>
        @endforeach
        <tr class="jumlah">
            <td colspan="3" class="end">JUMLAH / BAKI AKHIR (B/H)</td>
            <td class="end">{{ number_format($lejer['baris']->sum(fn ($r) => (float) $r->debit), 2) }}</td>
            <td class="end">{{ number_format($lejer['baris']->sum(fn ($r) => (float) $r->kredit), 2) }}</td>
            <td class="end">{{ number_format((float) $lejer['baki_akhir'], 2) }}</td>
        </tr>
    </tbody>
</table>
</body>
</html>
