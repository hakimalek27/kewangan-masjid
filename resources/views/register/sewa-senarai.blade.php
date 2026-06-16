@extends('layouts.app')

@section('title', __('Senarai Sewaan'))

@section('content')
@php
    $bolehTulis = in_array(auth()->user()?->role?->value, ['admin', 'bendahari'], true);
@endphp

<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" action="{{ route('sewa.senarai') }}" class="row g-2 align-items-end">
            <div class="col-auto">
                <select name="y" class="form-select">
                    @for ($t = 2030; $t >= 2020; $t--)
                        <option value="{{ $t }}" @selected($tahun === $t)>{{ $t }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">{{ __('Cari') }}</button>
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>{{ __('Cetak') }}
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Senarai Sewaan') }} — {{ __('Tahun') }} {{ $tahun }}</div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Nama Penyewa') }}</th>
                    <th>{{ __('No. KP') }}</th>
                    <th>{{ __('No. Akaun') }}</th>
                    <th>{{ __('Jenis Sewa') }}</th>
                    <th>{{ __('Tempoh Sewa') }}</th>
                    <th class="text-end">{{ __('Kadar Sewa (RM)') }}</th>
                    <th class="text-end">{{ __('Deposit (RM)') }}</th>
                    <th>{{ __('Catatan') }}</th>
                    <th>{{ __('Status') }}</th>
                    @if ($bolehTulis)<th class="no-print" style="width:90px">{{ __('Tindakan') }}</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($senarai as $s)
                    <tr>
                        <td>{{ $s->nama_penyewa }}</td>
                        <td>{{ $s->no_kp ?: '—' }}</td>
                        <td>{{ $s->no_akaun ?: '—' }}</td>
                        <td>{{ $s->jenis_sewa ?: '—' }}</td>
                        <td>{{ $s->tempoh_sewa ?: '—' }}</td>
                        <td class="text-end">{{ $s->kadar_sewa !== null ? number_format((float) $s->kadar_sewa, 2) : '—' }}</td>
                        <td class="text-end">{{ $s->deposit_amaun !== null ? number_format((float) $s->deposit_amaun, 2) : '—' }}</td>
                        <td>{{ $s->catatan ?: '—' }}</td>
                        <td><span class="badge {{ $s->status === 'AKTIF' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $s->status }}</span></td>
                        @if ($bolehTulis)
                            <td class="no-print">
                                <a href="{{ route('sewa.edit', $s) }}" class="btn btn-sm btn-outline-primary">{{ __('Edit') }}</a>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $bolehTulis ? 10 : 9 }}" class="text-center text-muted">{{ __('Tiada sewaan bagi tahun ini.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
