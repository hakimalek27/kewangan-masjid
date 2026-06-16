@extends('layouts.app')

@section('title', $tajuk)

@section('content')
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-cone-striped display-4 text-warning"></i>
            <h2 class="h5 mt-3">{{ __('Modul') }} "{{ $tajuk }}" {{ __('sedang dibina') }}</h2>
            <p class="text-muted mb-0">{{ __('Halaman ini akan tersedia pada fasa pembinaan berikutnya.') }}</p>
        </div>
    </div>
@endsection
