@extends('layouts.app')

@section('title', $tajuk)

@php
    $singkatBulan = [1=>'Jan','Feb','Mac','Apr','Mei','Jun','Jul','Ogo','Sep','Okt','Nov','Dis'];
    $julat = $jenis === 'kutipan' ? ['100','300','400','450'] : ['600','650'];
    $warnaCarta = $jenis === 'kutipan' ? 'rgba(25,135,84,.7)' : 'rgba(220,53,69,.7)';
@endphp

@section('content')
<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <x-period-filter :bulan="false" />
            <div class="col-md-4">
                <x-coa-select name="coa_id" :required="false" :julat="$julat"
                              placeholder="-- Semua Kategori --" :selected="request('coa_id')" class="mb-0" />
            </div>
            <div class="col-auto">
                <x-export-buttons :eksport="false" />
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header fw-bold">{{ $tajuk }} — {{ $tahun }}</div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-bordered table-hover" style="font-size:.8rem">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Kod') }}</th>
                    <th>{{ __('Kategori') }}</th>
                    @foreach ($singkatBulan as $nama)
                        <th class="text-end">{{ __($nama) }}</th>
                    @endforeach
                    <th class="text-end">{{ __('Jumlah') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($data['kategori'] as $k)
                    <tr>
                        <td>{{ $k->kod }}</td>
                        <td>{{ $k->nama }}</td>
                        @for ($b = 1; $b <= 12; $b++)
                            <td class="text-end">{{ $k->bulanan[$b] !== '0.00' ? number_format((float) $k->bulanan[$b], 2) : '' }}</td>
                        @endfor
                        <td class="text-end fw-bold">{{ number_format((float) $k->jumlah, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="15" class="text-center text-muted">{{ __('Tiada data bagi tahun ini.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="table-light">
                <tr class="fw-bold">
                    <th colspan="2" class="text-end">{{ __('JUMLAH') }}</th>
                    @for ($b = 1; $b <= 12; $b++)
                        <th class="text-end">{{ $bulanan[$b] != 0 ? number_format($bulanan[$b], 2) : '' }}</th>
                    @endfor
                    <th class="text-end">{{ number_format((float) $data['jumlah'], 2) }}</th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Carta Jumlah Bulanan') }} {{ $tahun }}</div>
    <div class="card-body">
        <canvas id="cartaStatistik" height="80"></canvas>
    </div>
</div>
@endsection

@push('scripts')
<script>
window.addEventListener('DOMContentLoaded', () => {
    new window.Chart(document.getElementById('cartaStatistik'), {
        type: 'bar',
        data: {
            labels: @json(array_map(fn ($n) => __($n), array_values($singkatBulan))),
            datasets: [{
                label: @json(__('Jumlah Bulanan (RM)')),
                data: @json(array_values($bulanan)),
                backgroundColor: @json($warnaCarta),
            }],
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } },
    });
});
</script>
@endpush
