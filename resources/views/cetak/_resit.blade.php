{{--
    Badan resit kutipan (format rujukan):
      $kutipan, $mod (SIGNATURE|DISCLAIMER), $terbilang, $salinanLabel (pilihan)
--}}
<div class="doc">
    @include('cetak._kepala', [
        'tajukDok'     => __('RESIT KUTIPAN'),
        'butiranKanan' => [
            __('No Resit') => $kutipan->no_resit,
            __('Tarikh')   => $kutipan->tarikh?->format('d/m/Y'),
        ],
    ])

    @isset($salinanLabel)
        <div style="text-align:right; font-size:10px; font-style:italic; color:#555; margin-top:-6px;">{{ $salinanLabel }}</div>
    @endisset

    <table class="tanpa-garis butiran" style="margin-bottom:6px;">
        <tr>
            <td style="width:55%;"><strong>{{ __('JENIS:') }}</strong> {{ __('PENERIMAAN') }}@if($kutipan->jenis === 'TABUNG') ({{ __('TABUNG') }})@endif</td>
            <td><strong>{{ __('Kod COA:') }}</strong> {{ $kutipan->coa?->nama }} ({{ $kutipan->coa?->kod }})</td>
        </tr>
        <tr>
            <td style="padding-top:6px;">
                <strong>{{ __('PENERIMAAN DARI:') }}</strong><br>
                <span class="garis-isi">{{ $kutipan->nama_pemberi ?: ' ' }}</span>
            </td>
            <td style="padding-top:6px;"><strong>{{ __('Kaedah:') }}</strong> {{ $kutipan->kaedah }}</td>
        </tr>
        @if ($kutipan->no_slip || $kutipan->tar_bankin)
            <tr>
                <td style="padding-top:6px;">@if ($kutipan->no_slip)<strong>{{ __('No. Slip Bank:') }}</strong> {{ $kutipan->no_slip }}@endif</td>
                <td style="padding-top:6px;">@if ($kutipan->tar_bankin)<strong>{{ __('Tarikh Bank Masuk:') }}</strong> {{ $kutipan->tar_bankin?->format('d/m/Y') }}@endif</td>
            </tr>
        @endif
        @php $saksiSemua = collect([$kutipan->saksi1, $kutipan->saksi2, $kutipan->saksi3])->filter()->implode(', '); @endphp
        @if ($saksiSemua !== '')
            <tr>
                <td colspan="2" style="padding-top:6px;"><strong>{{ __('Saksi:') }}</strong> {{ $saksiSemua }}</td>
            </tr>
        @endif
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:8%; text-align:center;">{{ __('Bil') }}</th>
                <th>{{ __('Penerangan') }}</th>
                <th style="width:24%; text-align:right;">{{ __('Jumlah (RM)') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align:center;">1</td>
                <td>{{ $kutipan->deskripsi ?: $kutipan->coa?->nama }}</td>
                <td style="text-align:right;">{{ number_format((float) $kutipan->jumlah, 2) }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2" style="text-align:right;">{{ __('JUMLAH KESELURUHAN') }}</th>
                <th style="text-align:right;">{{ number_format((float) $kutipan->jumlah, 2) }}</th>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top:8px;"><strong>{{ __('RINGGIT MALAYSIA:') }}</strong> {{ $terbilang }}</div>

    @if ($mod === 'SIGNATURE')
        <div style="margin-top:28px; text-align:right;">
            <div style="display:inline-block; text-align:center; min-width:210px;">
                <div class="ttd-line">{{ __('Tandatangan') }}</div>
                <div style="font-size:11px; margin-top:2px;">{{ __('COP DAN NAMA') }}</div>
            </div>
        </div>
    @else
        <div class="disclaimer" style="margin-top:24px;">
            ***** {{ __('CETAKAN BERKOMPUTER INI TIDAK MEMERLUKAN SEBARANG TANDATANGAN') }} *****
        </div>
    @endif
</div>
