@extends('layouts.app')

@section('title', __('Pembelian Aset'))

@section('content')
<div class="card shadow-sm" x-data="{ autoBaucer: {{ old('auto_baucer', '1') ? 'true' : 'false' }} }">
    <div class="card-header fw-bold">{{ __('Borang Pembelian Aset — bayar & daftar aset serentak') }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('belanja.aset.simpan') }}" enctype="multipart/form-data">
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
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="no_baucer">{{ __('No. Invois') }}</label>
                        <input type="text" name="no_baucer" id="no_baucer" class="form-control"
                               value="{{ old('no_baucer') }}" maxlength="60">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label d-block">{{ __('No. Baucer') }}</label>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="auto_baucer" id="auto_baucer" value="1"
                                   x-model="autoBaucer" @checked(old('auto_baucer', '1'))>
                            <label class="form-check-label" for="auto_baucer">
                                {{ __('Baucer auto (seterusnya:') }} <strong>{{ $peekPv }}</strong>)
                            </label>
                        </div>
                        <input type="text" name="baucer_no" class="form-control mt-1" placeholder="{{ __('No. baucer manual') }}"
                               value="{{ old('baucer_no') }}" x-show="!autoBaucer" :required="!autoBaucer" maxlength="30">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="pemohon">{{ __('Nama Pemohon / Penerima') }} <span class="text-danger">*</span></label>
                        <input type="text" name="pemohon" id="pemohon" class="form-control" required
                               value="{{ old('pemohon') }}" maxlength="200">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="nokp">{{ __('No. KP') }}</label>
                        <input type="text" name="nokp" id="nokp" class="form-control" value="{{ old('nokp') }}" maxlength="30">
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="mb-3">
                        <label class="form-label" for="alamat">{{ __('Alamat') }}</label>
                        <input type="text" name="alamat" id="alamat" class="form-control" value="{{ old('alamat') }}" maxlength="500">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="coa_id">{{ __('Akaun Aset (COA 200-01)') }} <span class="text-danger">*</span></label>
                        <select name="coa_id" id="coa_id" class="form-select" required data-searchable
                                data-placeholder="{{ __('-- Pilih Akaun Aset --') }}">
                            <option value="">{{ __('-- Pilih Akaun Aset --') }}</option>
                            @foreach ($coaAset as $coa)
                                <option value="{{ $coa->id }}" @selected((int) old('coa_id') === $coa->id)>{{ $coa->kod }} {{ $coa->nama }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">{{ __('Akaun SNT (kontra susut nilai) ditentukan automatik oleh sistem.') }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="asset_name">{{ __('Nama Aset') }} <span class="text-danger">*</span></label>
                        <input type="text" name="asset_name" id="asset_name" class="form-control" required
                               value="{{ old('asset_name') }}" maxlength="200">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="asset_location">{{ __('Lokasi Aset') }}</label>
                        <input type="text" name="asset_location" id="asset_location" class="form-control"
                               value="{{ old('asset_location') }}" maxlength="200">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <x-money-input name="jumlah" label="Jumlah Bayaran / Kos (RM)" />
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="useful_life">{{ __('Usia Guna (tahun)') }}</label>
                        <input type="number" name="useful_life" id="useful_life" class="form-control" min="1" max="99"
                               value="{{ old('useful_life') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="depn_rate">{{ __('Kadar Susut Nilai (%)') }}</label>
                        <input type="number" name="depn_rate" id="depn_rate" class="form-control" step="0.01" min="0" max="100"
                               value="{{ old('depn_rate') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="cara_bayar">{{ __('Cara Pembayaran') }} <span class="text-danger">*</span></label>
                        <select name="cara_bayar" id="cara_bayar" class="form-select" required>
                            <option value="CEK" @selected(old('cara_bayar') === 'CEK')>{{ __('Cek') }}</option>
                            <option value="EFT" @selected(old('cara_bayar') === 'EFT')>{{ __('EFT / Pindahan Bank') }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <x-bank-select name="bank_account_id" label="Bank" :required="true" />
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="no_cek">{{ __('No. Cek') }}</label>
                        <input type="text" name="no_cek" id="no_cek" class="form-control" value="{{ old('no_cek') }}" maxlength="60">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="no_acct">{{ __('No. Akaun Penerima') }}</label>
                        <input type="text" name="no_acct" id="no_acct" class="form-control" value="{{ old('no_acct') }}" maxlength="60">
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="deskripsi">{{ __('Deskripsi') }}</label>
                <textarea name="deskripsi" id="deskripsi" class="form-control" rows="2" maxlength="500">{{ old('deskripsi') }}</textarea>
            </div>

            {{-- Fasa 9 UX — zon seret & lepas resit --}}
            <x-drop-zone name="dokumen[]" id="dokumen" />

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="semakan" id="semakan" value="1" required>
                <label class="form-check-label" for="semakan">
                    {{ __('Saya mengesahkan maklumat pembelian aset di atas telah disemak dan betul.') }} <span class="text-danger">*</span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Simpan & Daftar Aset') }}</button>
            <a href="{{ route('belanja.senarai') }}" class="btn btn-outline-secondary">{{ __('Batal') }}</a>
        </form>
    </div>
</div>
@endsection
