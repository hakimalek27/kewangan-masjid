@extends('layouts.app')

@section('title', __('Kemaskini Sewaan'))

@section('content')
<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Kemaskini Sewaan') }} — {{ $sewaan->nama_penyewa }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('sewa.kemaskini', $sewaan) }}">
            @csrf
            @include('register._sewa-fields', ['sewaan' => $sewaan])

            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Kemaskini') }}</button>
            <a href="{{ route('sewa.senarai') }}" class="btn btn-outline-secondary">{{ __('Batal') }}</a>
        </form>

        <form method="POST" action="{{ route('sewa.padam', $sewaan) }}" class="mt-3"
              onsubmit="return confirm('{{ __('Padam rekod sewaan ini?') }}')">
            @csrf
            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i>{{ __('Padam Rekod') }}</button>
        </form>
    </div>
</div>
@endsection
