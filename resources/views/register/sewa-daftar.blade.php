@extends('layouts.app')

@section('title', __('Daftar Sewa'))

@section('content')
<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Borang Daftar Sewaan (register — tiada jurnal)') }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('sewa.simpan') }}">
            @csrf
            @include('register._sewa-fields')

            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Simpan') }}</button>
            <a href="{{ route('sewa.senarai') }}" class="btn btn-outline-secondary">{{ __('Batal') }}</a>
        </form>
    </div>
</div>
@endsection
