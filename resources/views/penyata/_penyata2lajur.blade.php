{{--
    Penyata 2-lajur gaya "V1" (replika mesrasuci — bersih, tanpa border penuh).
    Guna gaya inline sahaja supaya konsisten di skrin & dompdf (PDF).
    Param:
      $jenis      'bulanan' | 'tahunan'
      $p          tatasusunan penyata (monthly $p atau yearly $ps): baki_awal, terimaan,
                  pindahan_pwr, belanja, baki_akhir, jumlah_* (objek baris: kod, nama, baki|jumlah)
      $jumlahKiri $jumlahKanan   rentetan 2-dp ('.'), cth '202723.23'
      $pindahan   (pilihan) jumlah pindahan PWR untuk bulanan
      $tahun      (pilihan) untuk label tahunan
      $font       saiz font, cth '12px'
--}}
@php
    $isTahun   = ($jenis ?? 'bulanan') === 'tahunan';
    $thn       = $tahun ?? null;
    $jPindahan = $p['jumlah_pindahan'] ?? ($pindahan ?? collect($p['pindahan_pwr'] ?? [])->sum('jumlah'));
    $satuBank  = $p['satu_bank'] ?? false;
    $fmt       = fn ($v) => number_format((float) $v, 2);
    $seimbang  = $fmt($jumlahKiri) === $fmt($jumlahKanan);

    $kiri = [
        ['tajuk' => $isTahun ? '1. BAKI AWAL (01/01/'.$thn.')' : '1. BAKI AWAL (B/B)',
         'baris' => collect($p['baki_awal']), 'amaun' => 'baki',
         'jLabel' => 'Jumlah Baki Awal', 'jumlah' => $p['jumlah_baki_awal']],
        ['tajuk' => $isTahun ? '2. TERIMAAN TAHUN '.$thn : '2. TERIMAAN / KUTIPAN', 'sisi' => 'T',
         'baris' => collect($p['terimaan']), 'amaun' => 'jumlah', 'kosong' => 'Tiada rekod kutipan',
         'jLabel' => 'Jumlah Terimaan', 'jumlah' => $p['jumlah_terimaan']],
    ];
    // Rekupmen di KIRI hanya untuk penyata GABUNGAN (kontra neutral); satu-bank → KANAN sahaja (B2).
    if (! $satuBank) {
        $kiri[] = ['tajuk' => 'PELARASAN PINDAHAN TUNAI (PWR)', 'italic' => true,
            'baris' => collect($p['pindahan_pwr'] ?? []), 'amaun' => 'jumlah',
            'jLabel' => 'Jumlah Pindahan PWR', 'jumlah' => $jPindahan];
    }
    $kanan = [
        ['tajuk' => $isTahun ? '1. PERBELANJAAN TAHUN '.$thn : '1. PERBELANJAAN', 'sisi' => 'B',
         'baris' => collect($p['belanja']), 'amaun' => 'jumlah', 'kosong' => 'Tiada rekod belanja',
         'jLabel' => 'Jumlah Perbelanjaan', 'jumlah' => $p['jumlah_belanja']],
        ['tajuk' => $isTahun ? '2. BAKI AKHIR (31/12/'.$thn.')' : '2. BAKI AKHIR (B/H)',
         'baris' => collect($p['baki_akhir']), 'amaun' => 'baki',
         'jLabel' => 'Jumlah Baki Akhir', 'jumlah' => $p['jumlah_baki_akhir']],
        ['tajuk' => $isTahun ? 'PELARASAN PINDAHAN TUNAI (KONTRA)' : '3. PELARASAN PINDAHAN PWR (KONTRA)',
         'italic' => true, 'baris' => collect(), 'amaun' => 'jumlah',
         'jLabel' => 'Jumlah Pindahan PWR', 'jumlah' => $jPindahan],
    ];
@endphp

<table style="width:100%; border:0; border-collapse:collapse; font-size:{{ $font ?? '12px' }};">
    <tr>
        @foreach (['kiri' => $kiri, 'kanan' => $kanan] as $sisi => $seksyen)
            <td style="width:50%; vertical-align:top; border:0; padding:0 {{ $sisi === 'kiri' ? '10px' : '0' }} 0 {{ $sisi === 'kiri' ? '0' : '10px' }};">
                <table style="width:100%; border:0; border-collapse:collapse;">
                    <tr>
                        <td colspan="2" style="text-align:center; font-weight:bold; border-bottom:1.5px solid #222; padding:3px 0;">
                            {{ $sisi === 'kiri' ? 'BUTIR TERIMAAN (RM)' : 'BUTIR PERBELANJAAN (RM)' }}
                        </td>
                    </tr>
                    @foreach ($seksyen as $s)
                        <tr><td colspan="2" style="font-weight:bold; padding:9px 0 1px;{{ !empty($s['italic']) ? ' font-style:italic;' : '' }}">{{ $s['tajuk'] }}</td></tr>
                        @forelse ($s['baris'] as $r)
                            @php $mk = !empty($s['sisi']) ? (($noteMap ?? [])[$s['sisi'].':'.($r->kod ?? '')] ?? null) : null; @endphp
                            <tr>
                                <td style="padding:1px 0 1px 16px;">{{ $r->kod }} {{ $r->nama }}@if ($mk)<sup style="font-weight:bold; font-size:0.72em;">{{ $mk }}</sup>@endif</td>
                                <td style="text-align:right; padding:1px 0; white-space:nowrap;">{{ $fmt($r->{$s['amaun']}) }}</td>
                            </tr>
                        @empty
                            @if (!empty($s['kosong']))
                                <tr><td colspan="2" style="padding:1px 0 1px 16px; font-size:0.85em; color:#777;">{{ $s['kosong'] }}</td></tr>
                            @endif
                        @endforelse
                        <tr>
                            <td style="text-align:right; border-top:1px solid #999; padding:1px 0;{{ !empty($s['italic']) ? ' font-style:italic;' : '' }}"></td>
                            <td style="text-align:right; border-top:1px solid #999; padding:1px 0; font-weight:bold; white-space:nowrap;{{ !empty($s['italic']) ? ' font-style:italic;' : '' }}">{{ $fmt($s['jumlah']) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td style="text-align:right; font-weight:bold; padding:10px 0 0;">JUMLAH:</td>
                        <td style="text-align:right; font-weight:bold; padding:10px 0 0; white-space:nowrap;">{{ $fmt($sisi === 'kiri' ? $jumlahKiri : $jumlahKanan) }}</td>
                    </tr>
                </table>
            </td>
        @endforeach
    </tr>
</table>

<div style="text-align:center; margin-top:10px; font-weight:bold; {{ $seimbang ? 'color:#1a7f37;' : 'color:#c00;' }}">
    @if ($seimbang)
        SEIMBANG &#10004; — RM {{ $fmt($jumlahKiri) }} = RM {{ $fmt($jumlahKanan) }}
    @else
        TIDAK SEIMBANG — RM {{ $fmt($jumlahKiri) }} &ne; RM {{ $fmt($jumlahKanan) }}
    @endif
</div>
