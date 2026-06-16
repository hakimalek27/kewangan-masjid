@extends('layouts.app')

@section('title', $tajuk)

@php
    $bolehBatal = in_array(auth()->user()?->role?->value, ['admin', 'bendahari'], true);
@endphp

@section('content')
<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-0 small">{{ __('Dari Tarikh') }}</label>
                <input type="date" name="date_from" value="{{ $dari }}" class="form-control">
            </div>
            <div class="col-auto">
                <label class="form-label mb-0 small">{{ __('Hingga Tarikh') }}</label>
                <input type="date" name="date_to" value="{{ $hingga }}" class="form-control">
            </div>
            <div class="col-md-4">
                <x-coa-select name="coa_id" :required="$coaWajib"
                              :placeholder="$coaWajib ? '-- Pilih Akaun --' : '-- Semua Akaun --'"
                              :selected="request('coa_id')" class="mb-0" />
            </div>
            <div class="col-auto">
                <x-export-buttons />
            </div>
        </form>
    </div>
</div>

@if ($coaWajib && ! request('coa_id'))
    <div class="alert alert-info">{{ __('Sila pilih akaun untuk memaparkan laporan jurnal.') }}</div>
@endif

<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ $tajuk }} — {{ $dari }} {{ __('hingga') }} {{ $hingga }} ({{ $vouchers->count() }} {{ __('jurnal') }})</div>
    <div class="card-body">
        @forelse ($vouchers as $entries)
            @php $v = $entries->first(); @endphp
            <div class="border rounded mb-3">
                <div class="d-flex justify-content-between align-items-center bg-light px-3 py-2 border-bottom">
                    <div>
                        <span class="fw-bold">{{ $v->voucher_ref }}</span>
                        <span class="text-muted ms-2">{{ \Illuminate\Support\Carbon::parse($v->tarikh)->format('d/m/Y') }}</span>
                        <span class="badge text-bg-secondary ms-2">{{ $v->source_type }}</span>
                        <div class="small text-muted">{{ $v->deskripsi }}</div>
                    </div>
                    @if ($bolehBatal)
                        <form method="POST" action="{{ route('akaun.jurnal.batal', $v->voucher_id) }}" class="no-print"
                              onsubmit="return confirm('{{ __('Batal jurnal') }} {{ $v->voucher_ref }}? {{ __('Voucher pembalik akan dicipta (jejak audit kekal).') }}')">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-x-circle me-1"></i>{{ __('Batal Jurnal') }}
                            </button>
                        </form>
                    @endif
                </div>
                <table class="table table-sm mb-0">
                    <thead>
                        <tr class="table-light">
                            <th style="width:55%">{{ __('Akaun') }}</th>
                            <th style="width:20%">{{ __('Memo') }}</th>
                            <th class="text-end" style="width:12%">{{ __('Debit (RM)') }}</th>
                            <th class="text-end" style="width:13%">{{ __('Kredit (RM)') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $e)
                            <tr>
                                <td @class(['ps-4' => (float) $e->kredit > 0])>{{ $e->kod }} {{ $e->nama }}</td>
                                <td class="small text-muted">{{ $e->memo }}</td>
                                <td class="text-end">{{ (float) $e->debit > 0 ? number_format((float) $e->debit, 2) : '' }}</td>
                                <td class="text-end">{{ (float) $e->kredit > 0 ? number_format((float) $e->kredit, 2) : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="2" class="text-end">{{ __('JUMLAH') }}</th>
                            <th class="text-end">{{ number_format($entries->sum(fn ($e) => (float) $e->debit), 2) }}</th>
                            <th class="text-end">{{ number_format($entries->sum(fn ($e) => (float) $e->kredit), 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @empty
            <div class="text-center text-muted py-4">{{ __('Tiada jurnal bagi tempoh ini.') }}</div>
        @endforelse
    </div>
</div>
@endsection
