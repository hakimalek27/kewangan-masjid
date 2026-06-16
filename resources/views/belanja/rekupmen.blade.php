@extends('layouts.app')

@section('title', __('Rekupmen PWR'))

@section('content')
<div class="card shadow-sm" x-data="{ autoBaucer: {{ old('auto_baucer', '1') ? 'true' : 'false' }} }">
    <div class="card-header fw-bold">{{ __('Borang Rekupmen PWR — pemindahan Bank ke Panjar Wang Runcit (tiada kesan P&L)') }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('belanja.rekupmen.simpan') }}">
            @csrf
            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="tar_mohon">{{ __('Tarikh Mohon') }} <span class="text-danger">*</span></label>
                        <input type="date" name="tar_mohon" id="tar_mohon" class="form-control" required
                               value="{{ old('tar_mohon', now()->format('Y-m-d')) }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="tar_lulus">{{ __('Tarikh Lulus') }} <span class="text-danger">*</span></label>
                        <input type="date" name="tar_lulus" id="tar_lulus" class="form-control" required
                               value="{{ old('tar_lulus', now()->format('Y-m-d')) }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label d-block">{{ __('No. Baucer (siri PWR)') }}</label>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="auto_baucer" id="auto_baucer" value="1"
                                   x-model="autoBaucer" @checked(old('auto_baucer', '1'))>
                            <label class="form-check-label" for="auto_baucer">
                                {{ __('Baucer auto (seterusnya:') }} <strong>{{ $peekPwr }}</strong>)
                            </label>
                        </div>
                        <input type="text" name="baucer_no" class="form-control mt-1" placeholder="{{ __('No. baucer manual') }}"
                               value="{{ old('baucer_no') }}" x-show="!autoBaucer" :required="!autoBaucer" maxlength="30">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <x-coa-select name="pwr_coa_id" label="Akaun PWR (250-06)" :julat="['250-06']" />
                </div>
                <div class="col-md-6">
                    <x-bank-select name="bank_account_id" label="Bank Sumber" :required="true" />
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <x-money-input name="jumlah" label="Jumlah Rekupmen (RM)" />
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="pemohon">{{ __('Pemohon') }}</label>
                        <input type="text" name="pemohon" id="pemohon" class="form-control"
                               value="{{ old('pemohon') }}" maxlength="200">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="cara_bayar">{{ __('Cara Pembayaran') }} <span class="text-danger">*</span></label>
                        <select name="cara_bayar" id="cara_bayar" class="form-select" required>
                            <option value="EFT" @selected(old('cara_bayar', 'EFT') === 'EFT')>{{ __('EFT / Pindahan Bank') }}</option>
                            <option value="CEK" @selected(old('cara_bayar') === 'CEK')>{{ __('Cek') }}</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="no_cek">{{ __('No. Cek') }}</label>
                        <input type="text" name="no_cek" id="no_cek" class="form-control" value="{{ old('no_cek') }}" maxlength="60">
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="deskripsi">{{ __('Deskripsi') }}</label>
                <textarea name="deskripsi" id="deskripsi" class="form-control" rows="2" maxlength="500">{{ old('deskripsi') }}</textarea>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="semakan" id="semakan" value="1" required>
                <label class="form-check-label" for="semakan">
                    {{ __('Saya mengesahkan maklumat rekupmen di atas telah disemak dan betul.') }} <span class="text-danger">*</span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Simpan Data') }}</button>
            <a href="{{ route('belanja.senarai') }}" class="btn btn-outline-secondary">{{ __('Batal') }}</a>
        </form>
    </div>
</div>
@endsection
