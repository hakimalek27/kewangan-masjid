@extends('layouts.app')

@section('title', __('Edit Pengguna').' — '.$pengguna->login)

@section('content')
<div class="card shadow-sm" style="max-width:860px">
    <div class="card-header fw-bold">{{ __('Kemaskini Pengguna') }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('tetapan.pengguna.kemaskini', $pengguna) }}">
            @csrf
            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="login">{{ __('Nama Log Masuk') }} <span class="text-danger">*</span></label>
                        <input type="text" name="login" id="login" class="form-control" required
                               value="{{ old('login', $pengguna->login) }}" maxlength="60">
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="mb-3">
                        <label class="form-label" for="nama_penuh">{{ __('Nama Penuh') }} <span class="text-danger">*</span></label>
                        <input type="text" name="nama_penuh" id="nama_penuh" class="form-control" required
                               value="{{ old('nama_penuh', $pengguna->nama_penuh) }}" maxlength="200">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="role">{{ __('Peranan') }} <span class="text-danger">*</span></label>
                        <select name="role" id="role" class="form-select" required>
                            @foreach ($roles as $r)
                                <option value="{{ $r->value }}" @selected(old('role', $pengguna->role->value) === $r->value)>{{ $r->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="kata_laluan">{{ __('Reset Kata Laluan (kosongkan jika tidak ditukar)') }}</label>
                <input type="password" name="kata_laluan" id="kata_laluan" class="form-control"
                       minlength="6" autocomplete="new-password" placeholder="{{ __('Kata laluan baharu (min 6 aksara)') }}">
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                       @checked(old('is_active', $pengguna->is_active))>
                <label class="form-check-label" for="is_active">{{ __('Aktif (boleh log masuk)') }}</label>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Kemaskini') }}</button>
            <a href="{{ route('tetapan.pengguna') }}" class="btn btn-outline-secondary">{{ __('Batal') }}</a>
        </form>
    </div>
</div>
@endsection
