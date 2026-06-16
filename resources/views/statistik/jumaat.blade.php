@extends('layouts.app')

@section('title', __('Statistik Kutipan Jumaat'))

@section('content')
<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <x-period-filter />
            <div class="col-auto">
                <x-export-buttons :eksport="false" />
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header fw-bold">{{ __('Statistik Kutipan Jumaat (Mingguan)') }} — {{ __('Tempoh') }} {{ $ym }}</div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Tarikh') }}</th>
                    <th>{{ __('Keterangan') }}</th>
                    <th>{{ __('No. Resit') }}</th>
                    <th class="text-end">{{ __('Jumlah (RM)') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($senarai as $r)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($r->tarikh)->format('d/m/Y') }}</td>
                        <td>{{ $r->deskripsi }}</td>
                        <td>{{ $r->no_resit }}</td>
                        <td class="text-end">{{ number_format((float) $r->jumlah, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted">{{ __('Tiada kutipan Jumaat bagi tempoh ini.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="table-light">
                <tr class="fw-bold">
                    <th colspan="3" class="text-end">{{ __('JUMLAH') }}</th>
                    <th class="text-end">{{ number_format((float) $jumlah, 2) }}</th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Carta Kutipan Jumaat Mingguan') }}</div>
    <div class="card-body">
        <canvas id="cartaJumaat" height="80"></canvas>
    </div>
</div>
@endsection

@push('scripts')
<script>
window.addEventListener('DOMContentLoaded', () => {
    new window.Chart(document.getElementById('cartaJumaat'), {
        type: 'bar',
        data: {
            labels: @json($senarai->map(fn ($r) => \Illuminate\Support\Carbon::parse($r->tarikh)->format('d/m'))->values()),
            datasets: [{
                label: @json(__('Kutipan Jumaat (RM)')),
                data: @json($senarai->map(fn ($r) => (float) $r->jumlah)->values()),
                backgroundColor: 'rgba(13,110,253,.7)',
            }],
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } },
    });
});
</script>
@endpush
