{{--
    Nota Kepada Penyata — Rincian Program (terimaan / perbelanjaan / net setiap tag program).
    Mendedahkan program yang terkumpul dalam kategori COA (termasuk "Lain-lain").
    Gaya inline → konsisten di skrin & PDF. Param: $nota (Collection {program, terima, belanja, net}).
--}}
@php
    $fmt = fn ($v) => number_format((float) $v, 2);
    $tT = collect($nota)->sum(fn ($n) => (float) $n->terima);
    $tB = collect($nota)->sum(fn ($n) => (float) $n->belanja);
@endphp
<div class="penyata-cetak" style="margin-top:16px; font-size:{{ $font ?? '12px' }};">
    <div style="font-weight:bold; border-bottom:1.5px solid #222; padding-bottom:3px;">NOTA KEPADA PENYATA — RINCIAN PROGRAM</div>
    <div style="font-size:0.82em; color:#777; margin:4px 0 6px;">
        Memperincikan terimaan &amp; perbelanjaan setiap program (termasuk program yang terkumpul dalam kategori "Lain-lain").
    </div>
    <table style="width:100%; border:0; border-collapse:collapse;">
        <tr style="border-bottom:1px solid #999; font-weight:bold;">
            <td style="padding:3px 4px;">Program</td>
            <td style="padding:3px 4px; text-align:right;">Terimaan (RM)</td>
            <td style="padding:3px 4px; text-align:right;">Perbelanjaan (RM)</td>
            <td style="padding:3px 4px; text-align:right;">Net (RM)</td>
        </tr>
        @forelse ($nota as $n)
            <tr>
                <td style="padding:2px 4px;">{{ $n->program }}</td>
                <td style="padding:2px 4px; text-align:right; white-space:nowrap;">{{ $fmt($n->terima) }}</td>
                <td style="padding:2px 4px; text-align:right; white-space:nowrap;">{{ $fmt($n->belanja) }}</td>
                <td style="padding:2px 4px; text-align:right; white-space:nowrap;{{ (float) $n->net < 0 ? ' color:#c00;' : '' }}">{{ $fmt($n->net) }}</td>
            </tr>
        @empty
            <tr><td colspan="4" style="padding:5px 4px; color:#777;">Tiada program bertag bagi tempoh ini.</td></tr>
        @endforelse
        <tr style="border-top:1.5px solid #222; font-weight:bold;">
            <td style="padding:3px 4px;">JUMLAH</td>
            <td style="padding:3px 4px; text-align:right;">{{ $fmt($tT) }}</td>
            <td style="padding:3px 4px; text-align:right;">{{ $fmt($tB) }}</td>
            <td style="padding:3px 4px; text-align:right;">{{ $fmt($tT - $tB) }}</td>
        </tr>
    </table>
</div>
