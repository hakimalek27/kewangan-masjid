@extends('layouts.app')

@section('title', $tajuk)

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

<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ $tajuk }} — {{ __('Tempoh') }} {{ $ym }}</div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Kod') }}</th>
                    <th>{{ __('Kategori') }}</th>
                    <th class="text-end">{{ __('Bil') }}</th>
                    <th class="text-end">{{ __('Jumlah (RM)') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($data['baris'] as $r)
                    <tr>
                        <td>{{ $r->kod }}</td>
                        <td>{{ $r->nama }}</td>
                        <td class="text-end">{{ $r->bil }}</td>
                        <td class="text-end">{{ number_format((float) $r->jumlah, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted">{{ __('Tiada data bagi tempoh ini.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="table-light">
                <tr class="fw-bold">
                    <th colspan="2" class="text-end">{{ __('JUMLAH BESAR') }}</th>
                    <th class="text-end">{{ $data['baris']->sum('bil') }}</th>
                    <th class="text-end">{{ number_format((float) $data['jumlah'], 2) }}</th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
