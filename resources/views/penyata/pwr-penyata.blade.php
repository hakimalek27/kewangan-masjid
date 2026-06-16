@extends('layouts.app')

@section('title', __('Penyata PWR'))

@section('content')
<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <x-coa-select name="pwr_coa_id" :julat="['250-06']" :required="false"
                              placeholder="-- Pilih Akaun PWR --" :selected="request('pwr_coa_id', $coa?->id)" class="mb-0" />
            </div>
            <x-period-filter />
            <div class="col-auto">
                <x-export-buttons :eksport="false" />
            </div>
        </form>
    </div>
</div>

@if (! $coa || ! $penyata)
    <div class="alert alert-info">{{ __('Tiada akaun PWR (julat 250-06) ditemui.') }}</div>
@else
    <div class="card shadow-sm">
        <div class="card-header fw-bold">{{ __('Penyata PWR') }} — {{ $coa->kod }} {{ $coa->nama }} ({{ __('Tempoh') }} {{ $ym }})</div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Tarikh') }}</th>
                        <th>{{ __('Ref') }}</th>
                        <th>{{ __('Keterangan') }}</th>
                        <th class="text-end">{{ __('Masuk (RM)') }}</th>
                        <th class="text-end">{{ __('Keluar (RM)') }}</th>
                        <th class="text-end">{{ __('Baki (RM)') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-secondary">
                        <td colspan="5" class="fw-bold">{{ __('BAKI AWAL (B/B)') }}</td>
                        <td class="text-end fw-bold">{{ number_format((float) $penyata['baki_awal'], 2) }}</td>
                    </tr>
                    @forelse ($penyata['baris'] as $r)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($r->tarikh)->format('d/m/Y') }}</td>
                            <td>{{ $r->voucher_ref }}</td>
                            <td>{{ $r->deskripsi }}</td>
                            <td class="text-end">{{ (float) $r->debit > 0 ? number_format((float) $r->debit, 2) : '' }}</td>
                            <td class="text-end">{{ (float) $r->kredit > 0 ? number_format((float) $r->kredit, 2) : '' }}</td>
                            <td class="text-end">{{ number_format((float) $r->baki, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">{{ __('Tiada urus niaga bagi tempoh ini.') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="table-light">
                    <tr class="fw-bold">
                        <th colspan="3" class="text-end">{{ __('JUMLAH / BAKI AKHIR (B/H)') }}</th>
                        <th class="text-end">{{ number_format($penyata['baris']->sum(fn ($r) => (float) $r->debit), 2) }}</th>
                        <th class="text-end">{{ number_format($penyata['baris']->sum(fn ($r) => (float) $r->kredit), 2) }}</th>
                        <th class="text-end">{{ number_format((float) $penyata['baki_akhir'], 2) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endif
@endsection
