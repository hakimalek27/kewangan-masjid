@extends('layouts.app')

@section('title', __('Wizard Setup Sistem'))

@section('content')
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 mb-0">{{ __('Kemajuan Setup') }}</h2>
                <span class="badge text-bg-{{ $bilSiap === $jumlah ? 'success' : 'secondary' }}">
                    {{ $bilSiap }}/{{ $jumlah }} {{ __('langkah selesai') }}
                </span>
            </div>
            <div class="progress" style="height: 10px;">
                <div class="progress-bar {{ $bilSiap === $jumlah ? 'bg-success' : '' }}"
                     role="progressbar" style="width: {{ $peratus }}%"></div>
            </div>
            @if ($bilSiap === $jumlah)
                <p class="text-success small mt-2 mb-0"><i class="bi bi-check-circle-fill me-1"></i>
                    {{ __('Setup lengkap — sistem sedia digunakan.') }}</p>
            @else
                <p class="text-muted small mt-2 mb-0">{{ __('Lengkapkan langkah berikut mengikut urutan untuk pemasangan baharu.') }}</p>
            @endif
        </div>
    </div>

    <div class="list-group shadow-sm">
        @foreach ($langkah as $l)
            <div class="list-group-item d-flex align-items-center">
                <div class="me-3">
                    @if ($l['siap'])
                        <span class="badge rounded-pill text-bg-success" style="width:2rem;height:2rem;line-height:1.6rem;">
                            <i class="bi bi-check-lg"></i></span>
                    @else
                        <span class="badge rounded-pill text-bg-light border" style="width:2rem;height:2rem;line-height:1.6rem;">
                            {{ $l['no'] }}</span>
                    @endif
                </div>
                <div class="flex-grow-1">
                    <div class="fw-semibold">{{ __($l['tajuk']) }}
                        @if ($l['siap'])<span class="badge text-bg-success ms-1">{{ __('Selesai') }}</span>@endif
                    </div>
                    <div class="small text-muted">{{ __($l['huraian']) }}</div>
                </div>
                <a href="{{ route($l['route']) }}" class="btn btn-sm {{ $l['siap'] ? 'btn-outline-secondary' : 'btn-primary' }}">
                    {{ $l['siap'] ? __('Semak') : __('Tetapkan') }} <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        @endforeach
    </div>
@endsection
