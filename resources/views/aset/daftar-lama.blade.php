@extends('layouts.app')

@section('title', __('Daftar Aset Lama'))

@section('content')
<div class="card shadow-sm"
     x-data="{
        snt: {{ json_encode($coaAset->pluck('snt_info', 'id')) }},
        coaId: '{{ old('coa_id') }}',
        sntInfo() { return this.snt[this.coaId] || '—'; }
     }">
    <div class="card-header fw-bold">{{ __('Borang Daftar Aset Lama — tiada jurnal (nilai melalui Baki Awal)') }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('aset.daftarlama.simpan') }}">
            @csrf
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="coa_id">{{ __('Akaun Aset (COA 200-01)') }} <span class="text-danger">*</span></label>
                        <select name="coa_id" id="coa_id" class="form-select" required x-model="coaId">
                            <option value="">{{ __('-- Pilih Akaun Aset --') }}</option>
                            @foreach ($coaAset as $coa)
                                <option value="{{ $coa->id }}" @selected((int) old('coa_id') === $coa->id)>{{ $coa->kod }} {{ $coa->nama }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">{{ __('Akaun SNT (auto):') }} <strong x-text="sntInfo()"></strong></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="kod_aset">{{ __('Kod Aset (kosongkan untuk auto)') }}</label>
                        <input type="text" name="kod_aset" id="kod_aset" class="form-control"
                               value="{{ old('kod_aset') }}" maxlength="40">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="tarikh_perolehan">{{ __('Tarikh Perolehan') }} <span class="text-danger">*</span></label>
                        <input type="date" name="tarikh_perolehan" id="tarikh_perolehan" class="form-control" required
                               value="{{ old('tarikh_perolehan') }}">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="nama">{{ __('Nama Aset') }} <span class="text-danger">*</span></label>
                        <input type="text" name="nama" id="nama" class="form-control" required
                               value="{{ old('nama') }}" maxlength="200">
                    </div>
                </div>
                <div class="col-md-3">
                    <x-money-input name="kos" label="Kos (RM)" />
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="accumulated_depn">{{ __('Susut Nilai Terkumpul (RM)') }}</label>
                        <input type="number" step="0.01" min="0" name="accumulated_depn" id="accumulated_depn"
                               class="form-control text-rm" value="{{ old('accumulated_depn', '0.00') }}" placeholder="0.00">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="useful_life_years">{{ __('Usia Guna (tahun)') }}</label>
                        <input type="number" name="useful_life_years" id="useful_life_years" class="form-control"
                               min="1" max="99" value="{{ old('useful_life_years') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="depn_rate_pct">{{ __('Kadar Susut Nilai (%)') }}</label>
                        <input type="number" name="depn_rate_pct" id="depn_rate_pct" class="form-control"
                               step="0.01" min="0" max="100" value="{{ old('depn_rate_pct') }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="lokasi">{{ __('Lokasi') }}</label>
                        <input type="text" name="lokasi" id="lokasi" class="form-control"
                               value="{{ old('lokasi') }}" maxlength="200">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="acquisition_type">{{ __('Jenis Perolehan') }} <span class="text-danger">*</span></label>
                        <select name="acquisition_type" id="acquisition_type" class="form-select" required>
                            <option value="BELIAN_LAMA" @selected(old('acquisition_type', 'BELIAN_LAMA') === 'BELIAN_LAMA')>{{ __('Belian Lama') }}</option>
                            <option value="SUMBANGAN" @selected(old('acquisition_type') === 'SUMBANGAN')>{{ __('Sumbangan') }}</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="donation_value">{{ __('Nilai Sumbangan (RM)') }}</label>
                        <input type="number" step="0.01" min="0" name="donation_value" id="donation_value"
                               class="form-control text-rm" value="{{ old('donation_value') }}" placeholder="0.00">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="jenis_aset">{{ __('Jenis Aset') }} <span class="text-danger">*</span></label>
                        <select name="jenis_aset" id="jenis_aset" class="form-select" required>
                            <option value="ALIH" @selected(old('jenis_aset', 'ALIH') === 'ALIH')>{{ __('Aset Alih') }}</option>
                            <option value="TIDAK_ALIH" @selected(old('jenis_aset') === 'TIDAK_ALIH')>{{ __('Aset Tidak Alih') }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Daftar Aset') }}</button>
            <a href="{{ route('aset.senarai') }}" class="btn btn-outline-secondary">{{ __('Batal') }}</a>
        </form>
    </div>
</div>
@endsection
