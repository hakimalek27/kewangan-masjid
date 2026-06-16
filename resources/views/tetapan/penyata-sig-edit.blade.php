@extends('layouts.app')

@section('title', __('Edit Tandatangan Penyata'))

@section('content')
<div class="card shadow-sm" style="max-width:760px">
    <div class="card-header fw-bold">{{ __('Kemaskini Tandatangan') }} — {{ $signature->nama }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('penyata.setting.sig.kemaskini', $signature) }}">
            @csrf
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="nama">{{ __('Nama') }} <span class="text-danger">*</span></label>
                        <input type="text" name="nama" id="nama" class="form-control" required
                               value="{{ old('nama', $signature->nama) }}" maxlength="200">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="jawatan">{{ __('Jawatan') }} <span class="text-danger">*</span></label>
                        <input type="text" name="jawatan" id="jawatan" class="form-control" required
                               value="{{ old('jawatan', $signature->jawatan) }}" maxlength="120">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" for="susunan">{{ __('Susunan') }} <span class="text-danger">*</span></label>
                        <input type="number" name="susunan" id="susunan" class="form-control" required min="1" max="99"
                               value="{{ old('susunan', $signature->susunan) }}">
                    </div>
                </div>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="aktif" id="aktif" value="1"
                       @checked(old('aktif', $signature->aktif))>
                <label class="form-check-label" for="aktif">{{ __('Aktif (dipaparkan pada penyata)') }}</label>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Kemaskini') }}</button>
            <a href="{{ route('penyata.setting') }}" class="btn btn-outline-secondary">{{ __('Batal') }}</a>
        </form>
    </div>
</div>
@endsection
