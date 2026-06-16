@extends('layouts.app')

@section('title', __('Daftar Cek Batal'))

@section('content')
<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Borang Daftar Cek Batal (register — tiada jurnal)') }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('cekbatal.simpan') }}">
            @csrf
            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="no_cek_batal">{{ __('No. Cek Batal') }} <span class="text-danger">*</span></label>
                        <input type="text" name="no_cek_batal" id="no_cek_batal" class="form-control" required
                               value="{{ old('no_cek_batal') }}" maxlength="30">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="tarikh_batal">{{ __('Tarikh Batal') }} <span class="text-danger">*</span></label>
                        <input type="date" name="tarikh_batal" id="tarikh_batal" class="form-control" required
                               value="{{ old('tarikh_batal', now()->format('Y-m-d')) }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="penerima">{{ __('Penerima') }}</label>
                        <input type="text" name="penerima" id="penerima" class="form-control"
                               value="{{ old('penerima') }}" maxlength="200">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="amaun">{{ __('Amaun (RM)') }} <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="amaun" id="amaun" class="form-control text-rm"
                               required value="{{ old('amaun') }}" placeholder="0.00">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="sebab_batal">{{ __('Sebab Batal') }}</label>
                        <input type="text" name="sebab_batal" id="sebab_batal" class="form-control"
                               value="{{ old('sebab_batal') }}" maxlength="500">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="pengesahan_oleh">{{ __('Pengesahan Oleh') }}</label>
                        <input type="text" name="pengesahan_oleh" id="pengesahan_oleh" class="form-control"
                               value="{{ old('pengesahan_oleh') }}" maxlength="200">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="no_cek_ganti">{{ __('No. Cek Ganti') }}</label>
                        <input type="text" name="no_cek_ganti" id="no_cek_ganti" class="form-control"
                               value="{{ old('no_cek_ganti') }}" maxlength="30">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="tarikh_ganti">{{ __('Tarikh Ganti') }}</label>
                        <input type="date" name="tarikh_ganti" id="tarikh_ganti" class="form-control"
                               value="{{ old('tarikh_ganti') }}">
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
            <a href="{{ route('cekbatal.senarai') }}" class="btn btn-outline-secondary">{{ __('Batal') }}</a>
        </form>
    </div>
</div>
@endsection
