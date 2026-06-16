@extends('layouts.app')

@section('title', __('Daftar Peti Besi'))

@section('content')
<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Borang Daftar Peti Besi (register — tiada jurnal)') }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('petibesi.simpan') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="perkara">{{ __('Perkara') }} <span class="text-danger">*</span></label>
                <input type="text" name="perkara" id="perkara" class="form-control" required
                       value="{{ old('perkara') }}" maxlength="300">
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="nama_masuk">{{ __('Nama Pemasuk') }} <span class="text-danger">*</span></label>
                        <input type="text" name="nama_masuk" id="nama_masuk" class="form-control" required
                               value="{{ old('nama_masuk') }}" maxlength="200">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="tarikh_masuk">{{ __('Tarikh Masuk') }} <span class="text-danger">*</span></label>
                        <input type="date" name="tarikh_masuk" id="tarikh_masuk" class="form-control" required
                               value="{{ old('tarikh_masuk', now()->format('Y-m-d')) }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="nama_keluar">{{ __('Nama Pengeluar') }}</label>
                        <input type="text" name="nama_keluar" id="nama_keluar" class="form-control"
                               value="{{ old('nama_keluar') }}" maxlength="200">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="tarikh_keluar">{{ __('Tarikh Keluar') }}</label>
                        <input type="date" name="tarikh_keluar" id="tarikh_keluar" class="form-control"
                               value="{{ old('tarikh_keluar') }}">
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="catatan">{{ __('Catatan') }}</label>
                <input type="text" name="catatan" id="catatan" class="form-control"
                       value="{{ old('catatan') }}" maxlength="500">
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Hantar') }}</button>
            <a href="{{ route('petibesi.senarai') }}" class="btn btn-outline-secondary">{{ __('Batal') }}</a>
        </form>
    </div>
</div>
@endsection
