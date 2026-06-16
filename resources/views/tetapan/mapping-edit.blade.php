@extends('layouts.app')

@section('title', __('Edit Pemetaan Kod'))

@section('content')
<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Kemaskini Pemetaan') }} — {{ $mapping->local_label }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('tetapan.mapping.kemaskini', $mapping) }}">
            @csrf
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="local_label">{{ __('Label Tempatan') }} <span class="text-danger">*</span></label>
                        <input type="text" name="local_label" id="local_label" class="form-control" required
                               value="{{ old('local_label', $mapping->local_label) }}" maxlength="150">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="jenis_guna">{{ __('Jenis Guna') }} <span class="text-danger">*</span></label>
                        <select name="jenis_guna" id="jenis_guna" class="form-select" required>
                            @foreach (['penerimaan' => 'Penerimaan', 'perbelanjaan' => 'Perbelanjaan', 'kedua' => 'Kedua-dua'] as $nilai => $label)
                                <option value="{{ $nilai }}" @selected(old('jenis_guna', $mapping->jenis_guna) === $nilai)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-5">
                    <x-coa-select name="coa_id" label="Kod Akaun (COA)" :selected="$mapping->coa_id" />
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Kemaskini') }}</button>
            <a href="{{ route('tetapan.mapping') }}" class="btn btn-outline-secondary">{{ __('Batal') }}</a>
        </form>
    </div>
</div>
@endsection
