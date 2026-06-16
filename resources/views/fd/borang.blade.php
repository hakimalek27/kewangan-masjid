@extends('layouts.app')

@section('title', $tajuk)

@section('content')
<div class="card shadow-sm">
    <div class="card-header fw-bold">
        {{ $tajuk }}
        @if ($opening)
            <span class="badge text-bg-warning ms-2">{{ __('Tiada jurnal — nilai melalui Baki Awal') }}</span>
        @endif
    </div>
    <div class="card-body">
        @if ($opening)
            <div class="alert alert-info">
                {{ __('Pendaftaran pelaburan lama (sebelum sistem digunakan) hanya merekod maklumat FD —') }}
                <strong>{{ __('tiada jurnal') }}</strong> {{ __('dijana; nilainya dimasukkan melalui Baki Awal.') }}
            </div>
        @endif
        <form method="POST" action="{{ $opening ? route('fd.daftarlama.simpan') : route('fd.simpan') }}">
            @csrf
            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="tarikh">{{ __('Tarikh Pelaburan') }} <span class="text-danger">*</span></label>
                        <input type="date" name="tarikh" id="tarikh" class="form-control" required
                               value="{{ old('tarikh', now()->format('Y-m-d')) }}">
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="mb-3">
                        <label class="form-label" for="institusi">{{ __('Institusi') }} <span class="text-danger">*</span></label>
                        <input type="text" name="institusi" id="institusi" class="form-control" required
                               value="{{ old('institusi') }}" maxlength="200">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="no_sijil">{{ __('No. Sijil') }}</label>
                        <input type="text" name="no_sijil" id="no_sijil" class="form-control"
                               value="{{ old('no_sijil') }}" maxlength="60">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <x-coa-select name="coa_fd_id" label="Akaun FD (COA 250-04)" :julat="['250-04']" />
                </div>
                <div class="col-md-6">
                    <x-bank-select name="bank_account_id" label="Bank Sumber (COA bank dipilih automatik)" :required="true" />
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <x-money-input name="jumlah" label="Jumlah Pelaburan (RM)" />
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="kadar_pct">{{ __('Kadar (%)') }}</label>
                        <input type="number" name="kadar_pct" id="kadar_pct" class="form-control"
                               step="0.01" min="0" max="100" value="{{ old('kadar_pct') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="tempoh_bulan">{{ __('Tempoh (bulan)') }}</label>
                        <input type="number" name="tempoh_bulan" id="tempoh_bulan" class="form-control"
                               min="1" max="600" value="{{ old('tempoh_bulan') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="maturity_date">{{ __('Tarikh Matang') }}</label>
                        <input type="date" name="maturity_date" id="maturity_date" class="form-control"
                               value="{{ old('maturity_date') }}">
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="keterangan">{{ __('Keterangan') }}</label>
                <textarea name="keterangan" id="keterangan" class="form-control" rows="2" maxlength="500">{{ old('keterangan') }}</textarea>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i>{{ $opening ? __('Daftar Pelaburan Lama') : __('Simpan FD') }}
            </button>
            <a href="{{ $opening ? route('fd.senarailama') : route('fd.senarai') }}" class="btn btn-outline-secondary">{{ __('Batal') }}</a>
        </form>
    </div>
</div>
@endsection
