@extends('layouts.app')

@section('title', __('Kutipan Tabung'))

@section('content')
<div class="card shadow-sm"
     x-data="{
        denom: {{ json_encode(array_map('floatval', $denominasi)) }},
        bilangan: {{ json_encode(array_map(fn ($i) => (int) old('bilangan.'.$i, 0), array_keys($denominasi))) }},
        autoResit: {{ old('auto_resit', '1') ? 'true' : 'false' }},
        baris(i) { return (this.denom[i] * (parseInt(this.bilangan[i]) || 0)); },
        jumlahBesar() { return this.denom.reduce((t, d, i) => t + this.baris(i), 0); },
        rm(v) { return v.toLocaleString('ms-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
     }">
    <div class="card-header fw-bold">{{ __('Borang Kutipan Tabung (Harian / Jumaat)') }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('kutipan.tabung.simpan') }}">
            @csrf
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="jenis_tabung">{{ __('Jenis Tabung') }} <span class="text-danger">*</span></label>
                        <select name="jenis_tabung" id="jenis_tabung" class="form-select" required>
                            <option value="HARIAN" @selected(old('jenis_tabung') === 'HARIAN')>{{ __('Tabung Harian (400-01010)') }}</option>
                            <option value="JUMAAT" @selected(old('jenis_tabung') === 'JUMAAT')>{{ __('Tabung Jumaat (400-01020)') }}</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="tar_kira">{{ __('Tarikh Kiraan') }} <span class="text-danger">*</span></label>
                        <input type="date" name="tar_kira" id="tar_kira" class="form-control" required
                               value="{{ old('tar_kira', now()->format('Y-m-d')) }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="dibank_oleh">{{ __('Dibank Oleh') }}</label>
                        <input type="text" name="dibank_oleh" id="dibank_oleh" class="form-control"
                               value="{{ old('dibank_oleh') }}" maxlength="200">
                    </div>
                </div>
            </div>

            <h2 class="h6">{{ __('Jadual Denominasi') }} <span class="text-muted small">{{ __('(kaedah: TUNAI)') }}</span></h2>
            <table class="table table-sm table-bordered w-auto">
                <thead class="table-light">
                    <tr><th>{{ __('Denominasi (RM)') }}</th><th style="width:140px">{{ __('Bilangan') }}</th><th class="text-end" style="width:140px">{{ __('Jumlah (RM)') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($denominasi as $i => $denom)
                        <tr>
                            <td class="text-end">{{ $denom }}</td>
                            <td>
                                <input type="number" min="0" step="1" class="form-control form-control-sm text-end"
                                       name="bilangan[{{ $i }}]" x-model="bilangan[{{ $i }}]" placeholder="0">
                            </td>
                            <td class="text-end" x-text="rm(baris({{ $i }}))"></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="2" class="text-end">{{ __('JUMLAH BESAR (RM)') }}</th>
                        <th class="text-end" x-text="rm(jumlahBesar())"></th>
                    </tr>
                </tfoot>
            </table>
            @error('bilangan')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

            <div class="row">
                @foreach ([1, 2, 3] as $i)
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label" for="saksi{{ $i }}">{{ __('Saksi') }} {{ $i }}</label>
                            <input type="text" name="saksi{{ $i }}" id="saksi{{ $i }}" class="form-control"
                                   value="{{ old('saksi'.$i) }}" maxlength="200">
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row">
                <div class="col-md-4">
                    <x-bank-select name="bank_account_id" label="Bank (jika dibankkan)" />
                </div>
                <div class="col-md-2">
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
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="nama_pemberi">{{ __('Nama Pemberi') }}</label>
                        <input type="text" name="nama_pemberi" id="nama_pemberi" class="form-control"
                               value="{{ old('nama_pemberi') }}" maxlength="200">
                    </div>
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
                    {{ __('Saya mengesahkan kiraan tabung di atas telah disemak dan betul.') }} <span class="text-danger">*</span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Simpan Kutipan Tabung') }}</button>
            <a href="{{ route('kutipan.senarai') }}" class="btn btn-outline-secondary">{{ __('Batal') }}</a>
        </form>
    </div>
</div>
@endsection
