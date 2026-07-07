{{--
    Badan baucer bayaran (format rujukan):
      $pembayaran, $mod (SIGNATURE|DISCLAIMER), $terbilang
--}}
@php
    $melalui = $pembayaran->bank
        ? $pembayaran->bank->nama_bank.' ('.$pembayaran->bank->no_akaun.')'
        : ($pembayaran->pwrCoa ? $pembayaran->pwrCoa->nama.' ('.$pembayaran->pwrCoa->kod.')' : null);
@endphp
<div class="doc">
    @include('cetak._kepala', [
        'tajukDok'     => __('BAUCER BAYARAN'),
        'butiranKanan' => array_filter([
            __('No Baucer') => $pembayaran->baucer_no,
            __('No Invois') => $pembayaran->no_baucer,
        ]),
    ])

    <table class="tanpa-garis butiran" style="margin-bottom:6px;">
        <tr>
            <td style="width:55%;">
                <strong>{{ __('KEPADA:') }}</strong><br>
                <span class="garis-isi">{{ $pembayaran->pemohon ?: ' ' }}</span>
                @if ($pembayaran->no_acct)
                    <br><span style="font-size:11px;">{{ __('No. Akaun:') }} {{ $pembayaran->no_acct }}</span>
                @endif
            </td>
            <td>
                <div><strong>{{ __('Tarikh:') }}</strong> {{ ($pembayaran->tar_lulus ?? $pembayaran->tar_mohon)?->format('d/m/Y') }}</div>
                <div><strong>{{ __('Kaedah:') }}</strong> {{ $pembayaran->cara_bayar }}</div>
                @if ($pembayaran->no_cek)
                    <div><strong>{{ __('No. Cek:') }}</strong> {{ $pembayaran->no_cek }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:8%; text-align:center;">{{ __('Bil.') }}</th>
                <th>{{ __('Perkara') }}</th>
                <th style="width:24%; text-align:right;">{{ __('Jumlah (RM)') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align:center;">1</td>
                <td>{{ $pembayaran->deskripsi ?: $pembayaran->coa?->nama }}</td>
                <td style="text-align:right;">{{ number_format((float) $pembayaran->jumlah, 2) }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2" style="text-align:right;">{{ __('Jumlah') }}</th>
                <th style="text-align:right;">{{ number_format((float) $pembayaran->jumlah, 2) }}</th>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top:8px;"><strong>{{ __('RINGGIT MALAYSIA:') }}</strong> {{ $terbilang }}</div>
    @if ($melalui)
        <div style="margin-top:3px;"><strong>{{ __('Melalui') }} {{ $pembayaran->cara_bayar }}:</strong> {{ $melalui }}</div>
    @endif

    @if ($mod === 'SIGNATURE')
        <table class="tanpa-garis" style="margin-top:36px; text-align:center;">
            <tr>
                <td style="width:33%;"><div class="ttd-line" style="margin:0 12px;">{{ __('Disediakan Oleh') }}</div><div style="font-size:11px;">{{ __('BENDAHARI') }}</div></td>
                <td style="width:33%;"><div class="ttd-line" style="margin:0 12px;">{{ __('Disahkan Oleh') }}</div><div style="font-size:11px;">{{ __('PENGERUSI') }}</div></td>
                <td style="width:33%;"><div class="ttd-line" style="margin:0 12px;">{{ __('Diterima Oleh') }}</div><div style="font-size:11px;">{{ __('PENERIMA') }}</div></td>
            </tr>
        </table>
    @else
        <div class="disclaimer" style="margin-top:28px;">
            ***** {{ __('CETAKAN BERKOMPUTER INI TIDAK MEMERLUKAN SEBARANG TANDATANGAN') }} *****
        </div>
    @endif
</div>
