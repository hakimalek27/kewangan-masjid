{{--
    Kepala dokumen cetak (kongsi resit & baucer):
      $masjidSemasa  — model Masjid (dikongsi global; null-safe)
      $tajukDok      — tajuk kanan, cth "RESIT KUTIPAN" / "BAUCER BAYARAN"
      $butiranKanan  — array [label => nilai] di bawah tajuk
--}}
<table class="tanpa-garis">
    <tr>
        <td style="width:78px;">
            @if ($masjidSemasa?->logoUrl())
                <img src="{{ $masjidSemasa->logoUrl() }}" alt="logo" style="width:70px; height:auto;">
            @endif
        </td>
        <td style="text-align:center;">
            <div class="nama-masjid">{{ $masjidSemasa?->nama ?? config('app.name') }}</div>
            @if ($masjidSemasa?->alamatPenuh())
                <div>{{ $masjidSemasa->alamatPenuh() }}</div>
            @endif
            @if ($masjidSemasa?->telefon || $masjidSemasa?->emel)
                <div>
                    @if ($masjidSemasa->telefon){{ __('No. Tel:') }} {{ $masjidSemasa->telefon }}@endif
                    @if ($masjidSemasa->telefon && $masjidSemasa->emel) &middot; @endif
                    @if ($masjidSemasa->emel){{ $masjidSemasa->emel }}@endif
                </div>
            @endif
        </td>
        <td style="width:34%; text-align:right;">
            <div class="tajuk-dok">{{ $tajukDok }}</div>
            @foreach (($butiranKanan ?? []) as $label => $nilai)
                <div>{{ $label }}: {{ $nilai ?: '—' }}</div>
            @endforeach
        </td>
    </tr>
</table>
<hr class="pemisah">
