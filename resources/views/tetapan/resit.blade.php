@extends('layouts.app')

@section('title', __('Set Resit/Baucer'))

@section('content')
@php
    $bolehTulis = in_array(auth()->user()?->role?->value, ['admin', 'bendahari'], true);
@endphp

<div class="row">
    @foreach ($siri as $s)
        <div class="col-md-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-bold">{{ __($s['label']) }}</div>
                <div class="card-body">
                    <p class="mb-3">
                        {{ __('Nombor seterusnya:') }} <span class="badge text-bg-primary fs-6">{{ $s['seterusnya'] }}</span>
                    </p>
                    @if ($bolehTulis)
                        <form method="POST" action="{{ route('tetapan.resit.siri') }}" class="row g-2 align-items-end">
                            @csrf
                            <input type="hidden" name="jenis" value="{{ $s['jenis'] }}">
                            <div class="col-5">
                                <label class="form-label" for="digit_{{ $s['jenis'] }}">{{ __('Bilangan Digit (2-8)') }}</label>
                                <input type="number" min="2" max="8" name="digit" id="digit_{{ $s['jenis'] }}"
                                       class="form-control" value="{{ $s['digit'] }}" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label" for="mula_{{ $s['jenis'] }}">{{ __('Nombor Mula') }}</label>
                                <input type="number" min="1" name="mula" id="mula_{{ $s['jenis'] }}"
                                       class="form-control" value="{{ $s['mula'] }}" required>
                            </div>
                            <div class="col-3">
                                <button type="submit" class="btn btn-primary w-100">{{ __('Set') }}</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Logo Masjid (untuk resit & penyata)') }}</div>
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-4">
                @if ($masjid->logo_path)
                    <img src="{{ asset('storage/'.$masjid->logo_path) }}" alt="Logo {{ $masjid->nama }}"
                         class="img-thumbnail" style="max-height:140px">
                @else
                    <p class="text-muted mb-0">{{ __('Tiada logo dimuat naik.') }}</p>
                @endif
            </div>
            @if ($bolehTulis)
                <div class="col-md-8">
                    <form method="POST" action="{{ route('tetapan.resit.logo') }}" enctype="multipart/form-data"
                          class="row g-2 align-items-end">
                        @csrf
                        <div class="col-8">
                            <label class="form-label" for="logo">{{ __('Muat Naik Logo (PNG/JPG, maks 2MB)') }}</label>
                            <input type="file" name="logo" id="logo" class="form-control" accept=".png,.jpg,.jpeg" required>
                        </div>
                        <div class="col-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-upload me-1"></i>{{ __('Muat Naik') }}
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
