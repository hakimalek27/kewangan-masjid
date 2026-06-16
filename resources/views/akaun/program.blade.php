@extends('layouts.app')

@section('title', __('Laporan Mengikut Program'))

@php
    $wang = fn ($v) => (float) $v < 0
        ? '<span class="text-danger">('.number_format(abs((float) $v), 2).')</span>'
        : number_format((float) $v, 2);
@endphp

@section('content')
<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-0 small">{{ __('Dari (Bulan/Tahun) — opsional') }}</label>
                <div class="row g-1">
                    <div class="col-auto">
                        <select name="dari_bln" class="form-select">
                            @for ($i = 1; $i <= 12; $i++)
                                <option value="{{ $i }}" @selected((int) request('dari_bln', 1) === $i)>{{ sprintf('%02d', $i) }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-auto">
                        <select name="dari_year" class="form-select">
                            <option value="">{{ __('-- Semua --') }}</option>
                            @for ($y = 2030; $y >= 2020; $y--)
                                <option value="{{ $y }}" @selected((int) request('dari_year') === $y)>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                </div>
            </div>
            <div class="col-auto">
                <label class="form-label mb-0 small">{{ __('Hingga (Bulan/Tahun) — opsional') }}</label>
                <div class="row g-1">
                    <div class="col-auto">
                        <select name="bln" class="form-select">
                            @for ($i = 1; $i <= 12; $i++)
                                <option value="{{ $i }}" @selected((int) request('bln', 12) === $i)>{{ sprintf('%02d', $i) }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-auto">
                        <select name="year" class="form-select">
                            <option value="">{{ __('-- Semua --') }}</option>
                            @for ($y = 2030; $y >= 2020; $y--)
                                <option value="{{ $y }}" @selected((int) request('year') === $y)>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                </div>
            </div>
            <div class="col-auto">
                <x-export-buttons />
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-bold">
        {{ __('Laporan Mengikut Program') }}
        @if ($dari || $hingga) — {{ $dari ?? __('awal') }} {{ __('hingga') }} {{ $hingga ?? __('kini') }} @else — {{ __('Keseluruhan') }} @endif
        ({{ $program->count() }} {{ __('program') }})
    </div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Program') }}</th>
                    <th class="text-end">{{ __('Terima (RM)') }}</th>
                    <th class="text-end">{{ __('Belanja (RM)') }}</th>
                    <th class="text-end">{{ __('Net (RM)') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($program as $p)
                    <tr>
                        <td>{{ $p->program }}</td>
                        <td class="text-end">{{ number_format((float) $p->terima, 2) }}</td>
                        <td class="text-end">{{ number_format((float) $p->belanja, 2) }}</td>
                        <td class="text-end">{!! $wang($p->net) !!}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted">{{ __('Tiada program bagi tempoh ini.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="table-light">
                <tr class="fw-bold">
                    <th class="text-end">{{ __('JUMLAH') }}</th>
                    <th class="text-end">{{ number_format((float) $jumlah['terima'], 2) }}</th>
                    <th class="text-end">{{ number_format((float) $jumlah['belanja'], 2) }}</th>
                    <th class="text-end">{!! $wang($jumlah['net']) !!}</th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
