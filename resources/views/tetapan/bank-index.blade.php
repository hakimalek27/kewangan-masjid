@extends('layouts.app')

@section('title', __('Setting Bank'))

@section('content')
@php
    $bolehTulis = (bool) auth()->user()?->bolehTulis();
@endphp

<div class="card shadow-sm mb-4">
    <div class="card-header fw-bold">{{ __('Senarai Akaun Bank (Maksimum 3 Slot)') }}</div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width:60px">{{ __('Slot') }}</th>
                    <th>{{ __('Nama Bank') }}</th>
                    <th>{{ __('No Akaun') }}</th>
                    <th>COA</th>
                    <th>{{ __('Nama Akaun (COA)') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Penggunaan') }}</th>
                    @if ($bolehTulis)<th class="no-print" style="width:140px">{{ __('Tindakan') }}</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($senarai as $b)
                    <tr>
                        <td class="text-center">{{ $b->slot }}</td>
                        <td>{{ $b->nama_bank }}</td>
                        <td>{{ $b->no_akaun }}</td>
                        <td>{{ $b->coa?->kod }}</td>
                        <td>{{ $b->coa?->nama ?: '—' }}</td>
                        <td><span class="badge {{ $b->status === 'AKTIF' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $b->status }}</span></td>
                        <td>{{ $b->digunakan ? __('Digunakan') : __('Tidak digunakan') }}</td>
                        @if ($bolehTulis)
                            <td class="no-print">
                                <a href="{{ route('bank.edit', $b) }}" class="btn btn-sm btn-outline-primary">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('bank.padam', $b) }}" class="d-inline"
                                      onsubmit="return confirm('{{ __('Nyahaktifkan bank slot') }} {{ $b->slot }}? {{ __('Rekod kekal untuk rujukan transaksi.') }}')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Padam') }}</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $bolehTulis ? 8 : 7 }}" class="text-center text-muted">{{ __('Tiada akaun bank didaftarkan.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="small text-muted">
            {{ __('"Padam" hanya menetapkan status TIDAK AKTIF — rekod bank tidak dibuang kerana mungkin dirujuk transaksi lama.') }}
        </div>
    </div>
</div>

@if ($bolehTulis)
<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Tambah Bank Baru') }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('bank.simpan') }}">
            @csrf
            <div class="row">
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" for="slot">{{ __('Slot') }} <span class="text-danger">*</span></label>
                        <select name="slot" id="slot" class="form-select" required>
                            @foreach ([1, 2, 3] as $s)
                                <option value="{{ $s }}" @selected(old('slot') == $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="mb-3">
                        <label class="form-label" for="nama_bank">{{ __('Nama Bank') }} <span class="text-danger">*</span></label>
                        <input type="text" name="nama_bank" id="nama_bank" class="form-control" required
                               value="{{ old('nama_bank') }}" maxlength="120">
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="mb-3">
                        <label class="form-label" for="no_akaun">{{ __('No Akaun') }} <span class="text-danger">*</span></label>
                        <input type="text" name="no_akaun" id="no_akaun" class="form-control" required
                               value="{{ old('no_akaun') }}" maxlength="40">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <x-coa-select name="coa_id" label="Kod Akaun Bank (COA)" :julat="['250-05']" />
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="status">{{ __('Status') }} <span class="text-danger">*</span></label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="AKTIF" @selected(old('status', 'AKTIF') === 'AKTIF')>{{ __('AKTIF') }}</option>
                            <option value="TIDAK AKTIF" @selected(old('status') === 'TIDAK AKTIF')>{{ __('TIDAK AKTIF') }}</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3 form-check mt-4 pt-2">
                        <input class="form-check-input" type="checkbox" name="digunakan" id="digunakan" value="1"
                               @checked(old('digunakan', '1'))>
                        <label class="form-check-label" for="digunakan">{{ __('Digunakan dalam borang') }}</label>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Tambah Bank') }}</button>
        </form>
    </div>
</div>
@endif
@endsection
