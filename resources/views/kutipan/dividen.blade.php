@extends('layouts.app')

@section('title', __('Terimaan Dividen / Hibah FD'))

@section('content')
<div class="card shadow-sm" x-data="{ autoResit: {{ old('auto_resit', '1') ? 'true' : 'false' }} }">
    <div class="card-header fw-bold">{{ __('Borang Terimaan Dividen / Hibah Pelaburan (FD)') }}</div>
    <div class="card-body">
        @if ($senaraiFd->isEmpty())
            <div class="alert alert-warning mb-0">{{ __('Tiada pelaburan FD berstatus AKTIF. Sila daftar pelaburan dahulu.') }}</div>
        @else
        <form method="POST" action="{{ route('kutipan.dividen.simpan') }}">
            @csrf
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="fd_id">{{ __('Pelaburan (FD)') }} <span class="text-danger">*</span></label>
                        <select name="fd_id" id="fd_id" class="form-select" required data-searchable>
                            <option value="">{{ __('-- Pilih FD --') }}</option>
                            @foreach ($senaraiFd as $fd)
                                <option value="{{ $fd->id }}" @selected((int) old('fd_id', $fdDipilih) === $fd->id)>
                                    {{ $fd->institusi }} — {{ $fd->no_sijil ?: 'tiada no. sijil' }} (RM{{ number_format((float) $fd->jumlah, 2) }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <x-coa-select name="coa_id" label="Akaun Dividen (COA 450)" :julat="['450']" />
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <x-money-input name="jumlah" label="Jumlah Dividen (RM)" />
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="tarikh">{{ __('Tarikh Terimaan') }} <span class="text-danger">*</span></label>
                        <input type="date" name="tarikh" id="tarikh" class="form-control" required
                               value="{{ old('tarikh', now()->format('Y-m-d')) }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <x-bank-select name="bank_account_id" label="Bank" :required="true" />
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label d-block">{{ __('No. Resit') }}</label>
                        <div class="form-check form-check-inline mt-1">
                            <input class="form-check-input" type="checkbox" name="auto_resit" id="auto_resit" value="1"
                                   x-model="autoResit" @checked(old('auto_resit', '1'))>
                            <label class="form-check-label" for="auto_resit">
                                {{ __('Resit auto (seterusnya:') }} <strong>{{ $noResitSeterusnya }}</strong>)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="col-md-3" x-show="!autoResit">
                    <div class="mb-3">
                        <label class="form-label" for="no_resit">{{ __('No. Resit Manual') }} <span class="text-danger">*</span></label>
                        <input type="text" name="no_resit" id="no_resit" class="form-control"
                               value="{{ old('no_resit') }}" :required="!autoResit" maxlength="30">
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="mb-3">
                        <label class="form-label" for="deskripsi">{{ __('Deskripsi') }}</label>
                        <input type="text" name="deskripsi" id="deskripsi" class="form-control"
                               value="{{ old('deskripsi') }}" maxlength="500">
                    </div>
                </div>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="semakan" id="semakan" value="1" required>
                <label class="form-check-label" for="semakan">
                    {{ __('Saya mengesahkan maklumat dividen di atas telah disemak dan betul.') }} <span class="text-danger">*</span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Simpan Terimaan Dividen') }}</button>
            <a href="{{ route('fd.senarai') }}" class="btn btn-outline-secondary">{{ __('Batal') }}</a>
        </form>
        @endif
    </div>
</div>
@endsection
