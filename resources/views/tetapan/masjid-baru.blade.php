@extends('layouts.app')

@section('title', __('Masjid Baru'))

@section('content')
<div class="card shadow-sm" style="max-width:960px">
    <div class="card-header fw-bold">{{ __('Daftar Masjid Baru') }}</div>
    <div class="card-body">
        <p class="text-muted small">
            {{ __('Cipta rekod masjid baharu dan satu login bendahari. Selepas ini, bendahari log masuk dan menyediakan COA, bank, dan baki awal sendiri melalui Wizard Setup.') }}
        </p>
        <form method="POST" action="{{ route('tetapan.masjid.baru.simpan') }}">
            @csrf

            <h6 class="fw-bold border-bottom pb-2 mb-3">{{ __('A. Profil Masjid') }}</h6>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="nama">{{ __('Nama Masjid') }} <span class="text-danger">*</span></label>
                        <input type="text" name="nama" id="nama" class="form-control" required value="{{ old('nama') }}" maxlength="200">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="kategori">{{ __('Kategori') }}</label>
                        <select name="kategori" id="kategori" class="form-select">
                            <option value="">—</option>
                            @foreach ($kategori as $k)
                                <option value="{{ $k }}" @selected(old('kategori') === $k)>{{ $k }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="telefon">{{ __('Telefon') }}</label>
                        <input type="text" name="telefon" id="telefon" class="form-control" value="{{ old('telefon') }}" maxlength="30">
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="alamat">{{ __('Alamat') }}</label>
                <input type="text" name="alamat" id="alamat" class="form-control" value="{{ old('alamat') }}" maxlength="255">
            </div>
            <div class="row">
                <div class="col-md-2"><div class="mb-3"><label class="form-label" for="poskod">{{ __('Poskod') }}</label>
                    <input type="text" name="poskod" id="poskod" class="form-control" value="{{ old('poskod') }}" maxlength="10"></div></div>
                <div class="col-md-3"><div class="mb-3"><label class="form-label" for="bandar">{{ __('Bandar') }}</label>
                    <input type="text" name="bandar" id="bandar" class="form-control" value="{{ old('bandar') }}" maxlength="100"></div></div>
                <div class="col-md-3"><div class="mb-3"><label class="form-label" for="daerah">{{ __('Daerah') }}</label>
                    <input type="text" name="daerah" id="daerah" class="form-control" value="{{ old('daerah') }}" maxlength="100"></div></div>
                <div class="col-md-2"><div class="mb-3"><label class="form-label" for="negeri">{{ __('Negeri') }}</label>
                    <input type="text" name="negeri" id="negeri" class="form-control" value="{{ old('negeri') }}" maxlength="100"></div></div>
                <div class="col-md-2"><div class="mb-3"><label class="form-label" for="emel">{{ __('Emel') }}</label>
                    <input type="email" name="emel" id="emel" class="form-control" value="{{ old('emel') }}" maxlength="150"></div></div>
            </div>

            <h6 class="fw-bold border-bottom pb-2 mb-3 mt-2">{{ __('B. Login Bendahari Pertama') }}</h6>
            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="login">{{ __('Nama Log Masuk') }} <span class="text-danger">*</span></label>
                        <input type="text" name="login" id="login" class="form-control" required value="{{ old('login') }}" maxlength="60" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="mb-3">
                        <label class="form-label" for="nama_penuh">{{ __('Nama Penuh Bendahari') }} <span class="text-danger">*</span></label>
                        <input type="text" name="nama_penuh" id="nama_penuh" class="form-control" required value="{{ old('nama_penuh') }}" maxlength="200">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="kata_laluan">{{ __('Kata Laluan (min 6)') }} <span class="text-danger">*</span></label>
                        <input type="password" name="kata_laluan" id="kata_laluan" class="form-control" required minlength="6" autocomplete="new-password">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-building-add me-1"></i>{{ __('Cipta Masjid + Bendahari') }}</button>
            <a href="{{ route('tetapan.pengguna') }}" class="btn btn-outline-secondary">{{ __('Batal') }}</a>
        </form>
    </div>
</div>
@endsection
