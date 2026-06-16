@extends('layouts.app')

@section('title', __('Edit Bank — Slot').' '.$bank->slot)

@section('content')
<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Kemaskini Maklumat Bank') }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('bank.kemaskini', $bank) }}">
            @csrf
            <div class="row">
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" for="slot">{{ __('Slot') }} <span class="text-danger">*</span></label>
                        <select name="slot" id="slot" class="form-select" required>
                            @foreach ([1, 2, 3] as $s)
                                <option value="{{ $s }}" @selected(old('slot', $bank->slot) == $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="mb-3">
                        <label class="form-label" for="nama_bank">{{ __('Nama Bank') }} <span class="text-danger">*</span></label>
                        <input type="text" name="nama_bank" id="nama_bank" class="form-control" required
                               value="{{ old('nama_bank', $bank->nama_bank) }}" maxlength="120">
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="mb-3">
                        <label class="form-label" for="no_akaun">{{ __('No Akaun') }} <span class="text-danger">*</span></label>
                        <input type="text" name="no_akaun" id="no_akaun" class="form-control" required
                               value="{{ old('no_akaun', $bank->no_akaun) }}" maxlength="40">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <x-coa-select name="coa_id" label="Kod Akaun Bank (COA)" :julat="['250-05']" :selected="$bank->coa_id" />
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="status">{{ __('Status') }} <span class="text-danger">*</span></label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="AKTIF" @selected(old('status', $bank->status) === 'AKTIF')>{{ __('AKTIF') }}</option>
                            <option value="TIDAK AKTIF" @selected(old('status', $bank->status) === 'TIDAK AKTIF')>{{ __('TIDAK AKTIF') }}</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3 form-check mt-4 pt-2">
                        <input class="form-check-input" type="checkbox" name="digunakan" id="digunakan" value="1"
                               @checked(old('digunakan', $bank->digunakan))>
                        <label class="form-check-label" for="digunakan">{{ __('Digunakan dalam borang') }}</label>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Kemaskini') }}</button>
            <a href="{{ route('bank.index') }}" class="btn btn-outline-secondary">{{ __('Batal') }}</a>
        </form>
    </div>
</div>
@endsection
