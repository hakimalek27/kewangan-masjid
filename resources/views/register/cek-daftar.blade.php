@extends('layouts.app')

@section('title', __('Daftar Buku Cek'))

@section('content')
<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Borang Daftar Buku Cek (register — tiada jurnal)') }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('cek.simpan') }}">
            @csrf
            <div class="row">
                <div class="col-md-6">
                    <x-bank-select name="bank_account_id" label="Bank" :required="true" />
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="tarikh_keluar">{{ __('Tarikh Keluar') }} <span class="text-danger">*</span></label>
                        <input type="date" name="tarikh_keluar" id="tarikh_keluar" class="form-control" required
                               value="{{ old('tarikh_keluar', now()->format('Y-m-d')) }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="penerima">{{ __('Penerima') }} <span class="text-danger">*</span></label>
                        <input type="text" name="penerima" id="penerima" class="form-control" required
                               value="{{ old('penerima') }}" maxlength="200">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="no_siri_mula">{{ __('No. Siri Mula') }} <span class="text-danger">*</span></label>
                        <input type="text" name="no_siri_mula" id="no_siri_mula" class="form-control" required
                               value="{{ old('no_siri_mula') }}" maxlength="30">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="no_siri_akhir">{{ __('No. Siri Akhir') }} <span class="text-danger">*</span></label>
                        <input type="text" name="no_siri_akhir" id="no_siri_akhir" class="form-control" required
                               value="{{ old('no_siri_akhir') }}" maxlength="30">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="catatan">{{ __('Catatan') }}</label>
                        <input type="text" name="catatan" id="catatan" class="form-control"
                               value="{{ old('catatan') }}" maxlength="500">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Hantar') }}</button>
            <a href="{{ route('cek.senarai') }}" class="btn btn-outline-secondary">{{ __('Batal') }}</a>
        </form>
    </div>
</div>
@endsection
