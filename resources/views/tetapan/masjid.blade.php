@extends('layouts.app')

@section('title', __('Info Masjid'))

@section('content')
@php
    $isAdmin = auth()->user()?->role?->value === 'admin';
@endphp

@if ($isAdmin && ($bilCoa ?? 1) === 0)
    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Masjid ini belum mempunyai Carta Akaun (COA) — transaksi tidak boleh direkod tanpa COA.') }}</span>
        <form method="POST" action="{{ route('tetapan.masjid.sediacoa') }}" class="m-0">@csrf
            <button type="submit" class="btn btn-sm btn-warning"><i class="bi bi-list-columns me-1"></i>{{ __('Sedia COA Standard') }}</button>
        </form>
    </div>
@endif

<div class="card shadow-sm mb-4">
    <div class="card-header fw-bold d-flex justify-content-between align-items-center">
        <span>{{ __('Profil Masjid') }}</span>
        @if ($isAdmin)
            <a href="{{ route('tetapan.masjid.baru') }}" class="btn btn-sm btn-success">
                <i class="bi bi-building-add me-1"></i>{{ __('Masjid Baru') }}
            </a>
        @endif
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-2 text-center">
                @if ($masjid->logo_path)
                    <img src="{{ asset('storage/'.$masjid->logo_path) }}" alt="Logo" class="img-thumbnail mb-2" style="max-height:120px">
                @else
                    <i class="bi bi-moon-stars-fill display-4 text-secondary"></i>
                @endif
            </div>
            <div class="col-md-10">
                <table class="table table-sm">
                    <tr><th style="width:160px">{{ __('Nama') }}</th><td>{{ $masjid->nama }}</td></tr>
                    <tr><th>{{ __('Kategori') }}</th><td>{{ $masjid->kategori ?: '—' }}</td></tr>
                    <tr><th>{{ __('Alamat') }}</th><td>{{ $masjid->alamat ?: '—' }}, {{ $masjid->poskod }} {{ $masjid->bandar }}</td></tr>
                    <tr><th>{{ __('Daerah / Negeri') }}</th><td>{{ $masjid->daerah ?: '—' }} / {{ $masjid->negeri ?: '—' }}</td></tr>
                    <tr><th>{{ __('Telefon / Fax') }}</th><td>{{ $masjid->telefon ?: '—' }} / {{ $masjid->fax ?: '—' }}</td></tr>
                    <tr><th>{{ __('Emel') }}</th><td>{{ $masjid->emel ?: '—' }}</td></tr>
                    <tr><th>{{ __('Laman Web') }}</th><td>{{ $masjid->web ?: '—' }}</td></tr>
                    <tr><th>{{ __('Tahun Semasa') }}</th><td>{{ $masjid->tahun_semasa }}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

@if ($isAdmin)
<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Edit Info Masjid (Admin)') }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('tetapan.masjid.kemaskini') }}">
            @csrf
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label" for="nama">{{ __('Nama Masjid') }} <span class="text-danger">*</span></label>
                        <input type="text" name="nama" id="nama" class="form-control" required
                               value="{{ old('nama', $masjid->nama) }}" maxlength="200">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="kategori">{{ __('Kategori') }}</label>
                        <select name="kategori" id="kategori" class="form-select">
                            <option value="">{{ __('-- Pilih Kategori --') }}</option>
                            @foreach ($kategori as $k)
                                <option value="{{ $k }}" @selected(old('kategori', $masjid->kategori) === $k)>{{ $k }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="alamat">{{ __('Alamat') }}</label>
                <input type="text" name="alamat" id="alamat" class="form-control"
                       value="{{ old('alamat', $masjid->alamat) }}" maxlength="255">
            </div>
            <div class="row">
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" for="poskod">{{ __('Poskod') }}</label>
                        <input type="text" name="poskod" id="poskod" class="form-control"
                               value="{{ old('poskod', $masjid->poskod) }}" maxlength="10">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="bandar">{{ __('Bandar') }}</label>
                        <input type="text" name="bandar" id="bandar" class="form-control"
                               value="{{ old('bandar', $masjid->bandar) }}" maxlength="100">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="daerah">{{ __('Daerah') }}</label>
                        <input type="text" name="daerah" id="daerah" class="form-control"
                               value="{{ old('daerah', $masjid->daerah) }}" maxlength="100">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="negeri">{{ __('Negeri') }}</label>
                        <input type="text" name="negeri" id="negeri" class="form-control"
                               value="{{ old('negeri', $masjid->negeri) }}" maxlength="100">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="telefon">{{ __('Telefon') }}</label>
                        <input type="text" name="telefon" id="telefon" class="form-control"
                               value="{{ old('telefon', $masjid->telefon) }}" maxlength="30">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="fax">{{ __('Fax') }}</label>
                        <input type="text" name="fax" id="fax" class="form-control"
                               value="{{ old('fax', $masjid->fax) }}" maxlength="30">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="emel">{{ __('Emel') }}</label>
                        <input type="email" name="emel" id="emel" class="form-control"
                               value="{{ old('emel', $masjid->emel) }}" maxlength="150">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="web">{{ __('Laman Web') }}</label>
                        <input type="text" name="web" id="web" class="form-control"
                               value="{{ old('web', $masjid->web) }}" maxlength="150">
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Kemaskini Info Masjid') }}</button>
        </form>
    </div>
</div>
@endif
@endsection
