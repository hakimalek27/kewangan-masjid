@extends('layouts.app')

@section('title', $tajuk)

@section('content')
<div class="card shadow-sm"
     x-data="{
        caraBayar: '{{ old('cara_bayar', $modePwr ? 'PWR' : 'CEK') }}',
        autoBaucer: {{ old('auto_baucer', '1') ? 'true' : 'false' }},
        peek: { PV: '{{ $peekPv }}', PWR: '{{ $peekPwr }}' },
        peekSemasa() { return this.caraBayar === 'PWR' ? this.peek.PWR : this.peek.PV; }
     }">
    <div class="card-header fw-bold">{{ $modePwr ? __('BAYARAN PANJAR WANG RUNCIT (PWR)') : __('Borang Bayaran Perbelanjaan') }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('belanja.simpan') }}" enctype="multipart/form-data">
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
                                {{ __('Baucer auto (seterusnya:') }} <strong x-text="peekSemasa()"></strong>)
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
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="contactno">{{ __('No. Telefon') }}</label>
                        <input type="text" name="contactno" id="contactno" class="form-control" value="{{ old('contactno') }}" maxlength="30">
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="alamat">{{ __('Alamat') }}</label>
                <textarea name="alamat" id="alamat" class="form-control" rows="2" maxlength="500">{{ old('alamat') }}</textarea>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <x-coa-select name="coa_id" label="Jenis Pembayaran" :mapping="true" :julat="['300','600','650']" />
                </div>
                <div class="col-md-3">
                    <x-money-input name="jumlah" label="Jumlah Bayaran (RM)" />
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="cara_bayar">{{ __('Cara Pembayaran') }} <span class="text-danger">*</span></label>
                        <select name="cara_bayar" id="cara_bayar" class="form-select" required x-model="caraBayar">
                            <option value="CEK">{{ __('Cek') }}</option>
                            <option value="PWR">{{ __('PWR (Panjar Wang Runcit)') }}</option>
                            <option value="EFT">{{ __('EFT / Pindahan Bank') }}</option>
                            <option value="NON_CASH">{{ __('Bukan Tunai') }}</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Fasa 9 — amaran belanjawan masa nyata (COA + jumlah → /belanjawan/semak) --}}
            <x-budget-warning coa-input="coa_id" jumlah-input="jumlah" />

            {{-- Amaran defisit dana 300-04xxx masa nyata (COA + jumlah → /dana/semak) --}}
            <x-fund-warning coa-input="coa_id" jumlah-input="jumlah" />

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
                        <div class="form-text">{{ __('Tag program supaya bayaran ini muncul dalam nota kaki penyata. Pilih sedia ada atau taip baru.') }}</div>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="deskripsi">{{ __('Deskripsi') }}</label>
                <textarea name="deskripsi" id="deskripsi" class="form-control" rows="2" maxlength="500">{{ old('deskripsi') }}</textarea>
            </div>

            <div class="row" x-show="caraBayar !== 'PWR'">
                <div class="col-md-6">
                    <x-bank-select name="bank_account_id" label="Bank (wajib jika bukan PWR)" />
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

            <div class="row" x-show="caraBayar === 'PWR'">
                <div class="col-md-6">
                    <x-coa-select name="pwr_coa_id" label="Akaun PWR (wajib jika PWR)" :julat="['250-06']" :required="false" />
                </div>
            </div>

            {{-- Fasa 9 UX — zon seret & lepas resit --}}
            <x-drop-zone name="dokumen[]" id="dokumen" />

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="semakan" id="semakan" value="1" required>
                <label class="form-check-label" for="semakan">
                    {{ __('Saya mengesahkan maklumat pembayaran di atas telah disemak dan betul.') }} <span class="text-danger">*</span>
                </label>
            </div>

            @if (auth()->user()?->bolehTulis())
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Simpan Pembayaran') }}</button>
            @else
                <span class="badge bg-secondary"><i class="bi bi-eye me-1"></i>{{ __('Paparan sahaja — hanya bendahari boleh merekod pembayaran.') }}</span>
            @endif
            <a href="{{ route('belanja.senarai') }}" class="btn btn-outline-secondary">{{ __('Batal') }}</a>
        </form>
    </div>
</div>
@endsection
