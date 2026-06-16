@extends('layouts.cetak')

@section('tajuk', __('Resit Kutipan').' '.$kutipan->no_resit.($salinan == 2 ? ' — 2 salinan / A4' : ''))

@section('kertas')
    <div class="kertas-a4">
        @if ($salinan == 2)
            {{-- 2 resit IDENTIK pada 1 A4: setiap separuh tepat 148.5mm; koyak di garis tengah --}}
            <div class="resit-separuh">
                @include('cetak._resit', ['salinanLabel' => __('Salinan Pembayar')])
            </div>
            <div class="resit-separuh">
                @include('cetak._resit', ['salinanLabel' => __('Salinan Bendahari')])
            </div>
        @else
            <div class="resit-penuh">
                @include('cetak._resit')
            </div>
        @endif
    </div>
@endsection
