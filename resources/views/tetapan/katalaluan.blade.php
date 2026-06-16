@extends('layouts.app')

@section('title', __('Tukar Kata Laluan'))

@section('content')
<div class="card shadow-sm" style="max-width:520px">
    <div class="card-header fw-bold">{{ __('Tukar Kata Laluan') }} — {{ auth()->user()->login }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('tetapan.katalaluan.kemaskini') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="kata_semasa">{{ __('Kata Laluan Semasa') }} <span class="text-danger">*</span></label>
                <input type="password" name="kata_semasa" id="kata_semasa" class="form-control" required autocomplete="current-password">
            </div>
            <div class="mb-3">
                <label class="form-label" for="kata_baharu">{{ __('Kata Laluan Baharu (min 6 aksara)') }} <span class="text-danger">*</span></label>
                <input type="password" name="kata_baharu" id="kata_baharu" class="form-control" required minlength="6" autocomplete="new-password">
            </div>
            <div class="mb-3">
                <label class="form-label" for="kata_baharu_confirmation">{{ __('Sahkan Kata Laluan Baharu') }} <span class="text-danger">*</span></label>
                <input type="password" name="kata_baharu_confirmation" id="kata_baharu_confirmation" class="form-control" required minlength="6" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-key me-1"></i>{{ __('Kemaskini Kata Laluan') }}</button>
        </form>
        <div class="small text-muted mt-3">
            {{ __('Kata laluan baharu berkuat kuasa pada log masuk seterusnya selepas anda log keluar.') }}
        </div>
    </div>
</div>
@endsection
