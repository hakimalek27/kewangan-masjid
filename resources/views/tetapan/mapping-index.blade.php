@extends('layouts.app')

@section('title', __('Set Kod Penerimaan/Perbelanjaan'))

@section('content')
@php
    $bolehTulis = (bool) auth()->user()?->bolehTulis();
    $jenisLabel = ['penerimaan' => 'Penerimaan', 'perbelanjaan' => 'Perbelanjaan', 'kedua' => 'Kedua-dua'];
@endphp

<div class="card shadow-sm mb-4">
    <div class="card-header fw-bold">{{ __('Pemetaan Label Tempatan → Kod Akaun (COA)') }}</div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Label Tempatan') }}</th>
                    <th>{{ __('Jenis Guna') }}</th>
                    <th>{{ __('COA') }}</th>
                    @if ($bolehTulis)<th class="no-print" style="width:140px">{{ __('Tindakan') }}</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($senarai as $m)
                    <tr>
                        <td>{{ $m->local_label }}</td>
                        <td>{{ __($jenisLabel[$m->jenis_guna] ?? $m->jenis_guna) }}</td>
                        <td>{{ $m->coa_kod }} {{ $m->coa_nama }}</td>
                        @if ($bolehTulis)
                            <td class="no-print">
                                <a href="{{ route('tetapan.mapping.edit', $m->id) }}" class="btn btn-sm btn-outline-primary">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('tetapan.mapping.padam', $m->id) }}" class="d-inline"
                                      onsubmit="return confirm('{{ __('Padam pemetaan') }} \'{{ $m->local_label }}\'?')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Padam') }}</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $bolehTulis ? 4 : 3 }}" class="text-center text-muted">{{ __('Tiada pemetaan kod.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($bolehTulis)
<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Tambah Pemetaan Baru') }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('tetapan.mapping.simpan') }}">
            @csrf
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="local_label">Label Tempatan <span class="text-danger">*</span></label>
                        <input type="text" name="local_label" id="local_label" class="form-control" required
                               value="{{ old('local_label') }}" maxlength="150" placeholder="{{ __('cth: KUTIPAN JUMAAT') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="jenis_guna">Jenis Guna <span class="text-danger">*</span></label>
                        <select name="jenis_guna" id="jenis_guna" class="form-select" required>
                            @foreach ($jenisLabel as $nilai => $label)
                                <option value="{{ $nilai }}" @selected(old('jenis_guna', 'kedua') === $nilai)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-5">
                    <x-coa-select name="coa_id" label="Kod Akaun (COA)" />
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Simpan Pemetaan') }}</button>
        </form>
    </div>
</div>
@endif
@endsection
