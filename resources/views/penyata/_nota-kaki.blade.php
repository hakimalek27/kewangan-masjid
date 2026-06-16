{{--
    Nota kaki bernombor — dipaut dari tanda superskrip pada baris COA penyata.
    Setiap nota = satu COA + rincian program di dalamnya. Param: $notaList ([{no, kod, nama, progs}]).
--}}
@if (!empty($notaList) && count($notaList))
    @php $fmt = fn ($v) => number_format((float) $v, 2); @endphp
    <div class="penyata-cetak" style="margin-top:14px; font-size:{{ ($font ?? '12px') }};">
        <div style="font-weight:bold; border-bottom:1.5px solid #222; padding-bottom:3px; margin-bottom:5px;">NOTA KAKI</div>
        @foreach ($notaList as $n)
            <div style="margin-bottom:3px; line-height:1.45;">
                <sup style="font-weight:bold;">{{ $n->no }}</sup>
                <strong>{{ $n->kod }} {{ $n->nama }}</strong>&nbsp;&mdash;
                @foreach ($n->progs as $pr){{ $pr->program }}: {{ $fmt($pr->jumlah) }}@if (!$loop->last);&nbsp;@endif @endforeach
            </div>
        @endforeach
    </div>
@endif
