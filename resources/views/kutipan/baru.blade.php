@extends('layouts.app')

@section('title', __('Kutipan Baru'))

@section('content')
<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Borang Kutipan Baru') }}</div>
    <div class="card-body" x-data="{ kaedah: '{{ old('kaedah', 'TUNAI') }}', autoResit: {{ old('auto_resit', '1') ? 'true' : 'false' }} }">
        <form method="POST" action="{{ route('kutipan.simpan') }}">
            @csrf
            <div class="row">
                <div class="col-md-6">
                    <x-coa-select name="coa_id" label="Jenis Kutipan" :mapping="true" :julat="['100','300','400','450']" />
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="kaedah">{{ __('Kaedah Kutipan') }} <span class="text-danger">*</span></label>
                        <select name="kaedah" id="kaedah" class="form-select" required x-model="kaedah">
                            <option value="TUNAI">{{ __('Tunai') }}</option>
                            <option value="CEK">{{ __('Cek') }}</option>
                            <option value="BANK_TRANSFER_QR">{{ __('Bank Transfer / QR') }}</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="tarikh">{{ __('Tarikh Kutipan') }} <span class="text-danger">*</span></label>
                        <input type="date" name="tarikh" id="tarikh" class="form-control" required
                               value="{{ old('tarikh', now()->format('Y-m-d')) }}">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <x-money-input name="jumlah" label="Jumlah Kutipan (RM)" />
                </div>
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
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="nama_pemberi">{{ __('Nama Pemberi') }}</label>
                        <input type="text" name="nama_pemberi" id="nama_pemberi" class="form-control"
                               value="{{ old('nama_pemberi') }}" maxlength="200">
                    </div>
                </div>
                @foreach ([1, 2, 3] as $i)
                    <div class="col-md-2">
                        <div class="mb-3">
                            <label class="form-label" for="saksi{{ $i }}">{{ __('Saksi') }} {{ $i }}</label>
                            <input type="text" name="saksi{{ $i }}" id="saksi{{ $i }}" class="form-control"
                                   value="{{ old('saksi'.$i) }}" maxlength="200">
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row" x-show="kaedah !== 'TUNAI'">
                <div class="col-md-6">
                    <x-bank-select name="bank_account_id" label="Bank (wajib jika bukan tunai)" />
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="no_slip">{{ __('No. Slip Bank') }}</label>
                        <input type="text" name="no_slip" id="no_slip" class="form-control"
                               value="{{ old('no_slip') }}" maxlength="60">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="tar_bankin">{{ __('Tarikh Bank Masuk') }}</label>
                        <input type="date" name="tar_bankin" id="tar_bankin" class="form-control"
                               value="{{ old('tar_bankin') }}">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="program">{{ __('Program') }}
                            <span class="text-muted small">{{ __('(pilihan — untuk Nota Penyata)') }}</span></label>
                        <input type="text" name="program" id="program" class="form-control" list="senarai-program"
                               value="{{ old('program') }}" maxlength="120"
                               placeholder="{{ __('cth: IHYA RAMADAN, QURBAN, BOWLING') }}">
                        <datalist id="senarai-program">
                            @foreach ($programSenarai ?? [] as $pg)
                                <option value="{{ $pg }}"></option>
                            @endforeach
                        </datalist>
                        <div class="form-text">{{ __('Tag program supaya kutipan ini muncul dalam nota kaki penyata. Pilih sedia ada atau taip baru.') }}</div>
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
                    {{ __('Saya mengesahkan maklumat kutipan di atas telah disemak dan betul.') }} <span class="text-danger">*</span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Simpan Kutipan') }}</button>
            <a href="{{ route('kutipan.senarai') }}" class="btn btn-outline-secondary">{{ __('Batal') }}</a>
        </form>
    </div>
</div>
@endsection
