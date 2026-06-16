@extends('layouts.app')

@section('title', __('Menu Perbelanjaan'))

@section('content')
<div class="row g-3">
    @foreach ([
        [__('Bayaran Perbelanjaan'), __('Bayaran biasa melalui Cek/PWR/EFT — Dr Belanja / Cr Bank atau PWR.'), 'bi-cash-stack', route('belanja.baru')],
        [__('Pembelian Aset'), __('Bayar dan daftar aset tetap serentak — Dr Aset / Cr Bank.'), 'bi-pc-display', route('belanja.aset')],
        [__('Perbelanjaan Bukan Tunai'), __('Jurnal manual: susut nilai, akruan, pembetulan — tiada pergerakan tunai.'), 'bi-journal-text', route('belanja.jurnal')],
        [__('Rekupmen PWR'), __('Tambah semula Panjar Wang Runcit — pemindahan Bank ke PWR.'), 'bi-wallet2', route('belanja.rekupmen')],
    ] as [$tajuk, $nota, $ikon, $url])
        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center d-flex flex-column">
                    <i class="bi {{ $ikon }} display-5 text-primary"></i>
                    <h2 class="h6 mt-3">{{ $tajuk }}</h2>
                    <p class="small text-muted flex-grow-1">{{ $nota }}</p>
                    <a href="{{ $url }}" class="btn btn-primary">{{ __('Buka Borang') }}</a>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection
