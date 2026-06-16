@extends('layouts.cetak')

@section('tajuk', __('Baucer Bayaran').' '.$pembayaran->baucer_no)

@section('kertas')
    <div class="kertas-a4">
        <div class="resit-penuh">
            @include('cetak._baucer')
        </div>
    </div>
@endsection
