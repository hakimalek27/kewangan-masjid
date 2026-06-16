<!DOCTYPE html>
<html lang="ms">
<head><meta charset="utf-8"><title>{{ $tajuk }}</title></head>
<body>
@include('pdf._kepala', [
    'tajukLaporan' => mb_strtoupper($tajuk),
    'tempoh'       => $dari.' hingga '.$hingga.' ('.$vouchers->count().' jurnal)',
])

@forelse ($vouchers as $entries)
    @php $v = $entries->first(); @endphp
    <table style="margin-bottom:10px">
        <thead>
            <tr class="seksyen">
                <td colspan="2">
                    <strong>{{ $v->voucher_ref }}</strong>
                    — {{ \Illuminate\Support\Carbon::parse($v->tarikh)->format('d/m/Y') }}
                    [{{ $v->source_type }}] {{ $v->deskripsi }}
                </td>
                <td class="end">Debit (RM)</td>
                <td class="end">Kredit (RM)</td>
            </tr>
        </thead>
        <tbody>
            @foreach ($entries as $e)
                <tr>
                    <td colspan="2">{{ $e->kod }} {{ $e->nama }} @if ($e->memo)<em>({{ $e->memo }})</em>@endif</td>
                    <td class="end">{{ (float) $e->debit > 0 ? number_format((float) $e->debit, 2) : '' }}</td>
                    <td class="end">{{ (float) $e->kredit > 0 ? number_format((float) $e->kredit, 2) : '' }}</td>
                </tr>
            @endforeach
            <tr class="jumlah">
                <td colspan="2" class="end">JUMLAH</td>
                <td class="end">{{ number_format($entries->sum(fn ($e) => (float) $e->debit), 2) }}</td>
                <td class="end">{{ number_format($entries->sum(fn ($e) => (float) $e->kredit), 2) }}</td>
            </tr>
        </tbody>
    </table>
@empty
    <p style="text-align:center">Tiada jurnal bagi tempoh ini.</p>
@endforelse
</body>
</html>
