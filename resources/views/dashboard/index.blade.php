@extends('layouts.app')

@section('title', __('Dashboard'))

@section('content')
    {{-- Kepala masjid: logo + nama + alamat + tel (terus dari Info Masjid) --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body d-flex align-items-center gap-3 flex-wrap">
            @if ($masjidSemasa?->logoUrl())
                <img src="{{ $masjidSemasa->logoUrl() }}" alt="{{ __('Logo') }}" style="height:64px; width:auto;">
            @else
                <i class="bi bi-bank fs-1 text-secondary"></i>
            @endif
            <div>
                <div class="h5 mb-1 fw-bold">{{ $masjidSemasa?->nama ?? config('app.name') }}</div>
                @if ($masjidSemasa?->alamatPenuh())
                    <div class="small text-muted">{{ $masjidSemasa->alamatPenuh() }}</div>
                @endif
                @if ($masjidSemasa?->telefon || $masjidSemasa?->emel)
                    <div class="small text-muted">
                        @if ($masjidSemasa->telefon)<i class="bi bi-telephone me-1"></i>{{ $masjidSemasa->telefon }}@endif
                        @if ($masjidSemasa->emel)<i class="bi bi-envelope ms-3 me-1"></i>{{ $masjidSemasa->emel }}@endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6 col-xl-3">
            <div class="card border-start border-success border-4 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-success text-uppercase small fw-bold">{{ __('Penerimaan Tahunan') }} {{ $tahun }} (RM)</div>
                    <div class="h5 mb-0 fw-bold">{{ number_format((float) $ringkasan['penerimaan'], 2) }}</div>
                    <div class="small text-muted">{{ __('Jumlah kutipan tunai masuk') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-start border-danger border-4 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-danger text-uppercase small fw-bold">{{ __('Perbelanjaan Tahunan') }} {{ $tahun }} (RM)</div>
                    <div class="h5 mb-0 fw-bold">{{ number_format((float) $ringkasan['perbelanjaan'], 2) }}</div>
                    <div class="small text-muted">{{ __('Jumlah tunai keluar (bayaran + aset + PWR)') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-6">
            <div class="card border-start border-info border-4 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-info text-uppercase small fw-bold"><i class="bi bi-megaphone me-1"></i>{{ __('Pengumuman') }}</div>
                    <div class="fw-bold mb-1">{{ __('Selamat datang ke SPKM') }}</div>
                    <div class="small text-muted">
                        {{ __('Sila rujuk') }} <strong>{{ __('Panduan Kewangan') }}</strong> {{ __('(menu profil di penjuru atas kanan) untuk bantuan.') }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header fw-bold">{{ __('Trend Kutipan & Perbelanjaan Bulanan') }} {{ $tahun }}</div>
                <div class="card-body">
                    <canvas id="cartaTrend" height="90"></canvas>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
window.addEventListener('DOMContentLoaded', () => {
    new window.Chart(document.getElementById('cartaTrend'), {
        type: 'bar',
        data: {
            labels: @json($trend['label']),
            datasets: [
                { label: @json(__('Terimaan (RM)')), data: @json($trend['terima']), backgroundColor: 'rgba(25,135,84,.7)' },
                { label: @json(__('Perbelanjaan (RM)')), data: @json($trend['bayar']), backgroundColor: 'rgba(220,53,69,.7)' },
            ],
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true } },
        },
    });
});
</script>
@endpush
