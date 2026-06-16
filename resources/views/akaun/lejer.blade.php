@extends('layouts.app')

@section('title', $tajuk)

@section('content')
<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <x-coa-select name="coa_id" :required="true" placeholder="-- Pilih Akaun --"
                              :selected="request('coa_id')" class="mb-0" />
            </div>
            <div class="col-auto">
                <label class="form-label mb-0 small">{{ __('Dari Tarikh') }}</label>
                <input type="date" name="date_from" value="{{ $dari }}" class="form-control">
            </div>
            <div class="col-auto">
                <label class="form-label mb-0 small">{{ __('Hingga Tarikh') }}</label>
                <input type="date" name="date_to" value="{{ $hingga }}" class="form-control">
            </div>
            <div class="col-auto">
                <x-export-buttons />
            </div>
        </form>
    </div>
</div>

@if (! $lejer)
    <div class="alert alert-info">{{ __('Sila pilih akaun untuk memaparkan lejer.') }}</div>
@else
    <div class="card shadow-sm">
        <div class="card-header fw-bold">
            {{ $tajuk }} — {{ $coa?->kod }} {{ $coa?->nama }} ({{ $dari }} {{ __('hingga') }} {{ $hingga }})
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Tarikh') }}</th>
                        <th>{{ __('Ref') }}</th>
                        <th>{{ __('Keterangan') }}</th>
                        <th class="text-end">{{ __('Debit (RM)') }}</th>
                        <th class="text-end">{{ __('Kredit (RM)') }}</th>
                        <th class="text-end">{{ __('Baki (RM)') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-secondary">
                        <td colspan="5" class="fw-bold">{{ __('BAKI AWAL (B/B)') }}</td>
                        <td class="text-end fw-bold">{{ number_format((float) $lejer['baki_awal'], 2) }}</td>
                    </tr>
                    @forelse ($lejer['baris'] as $r)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($r->tarikh)->format('d/m/Y') }}</td>
                            <td>{{ $r->voucher_ref }}</td>
                            <td>{{ $r->deskripsi ?: $r->memo }}</td>
                            <td class="text-end">{{ (float) $r->debit > 0 ? number_format((float) $r->debit, 2) : '' }}</td>
                            <td class="text-end">{{ (float) $r->kredit > 0 ? number_format((float) $r->kredit, 2) : '' }}</td>
                            <td class="text-end">{{ number_format((float) $r->baki, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">{{ __('Tiada urus niaga bagi tempoh ini.') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="3" class="text-end">{{ __('JUMLAH / BAKI AKHIR (B/H)') }}</th>
                        <th class="text-end">{{ number_format($lejer['baris']->sum(fn ($r) => (float) $r->debit), 2) }}</th>
                        <th class="text-end">{{ number_format($lejer['baris']->sum(fn ($r) => (float) $r->kredit), 2) }}</th>
                        <th class="text-end">{{ number_format((float) $lejer['baki_akhir'], 2) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endif
@endsection
