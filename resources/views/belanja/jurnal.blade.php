@extends('layouts.app')

@section('title', __('Perbelanjaan Bukan Tunai (Jurnal)'))

@section('content')
<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Borang Jurnal Manual — susut nilai / akruan / pembetulan (tiada pergerakan tunai)') }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('belanja.jurnal.simpan') }}">
            @csrf
            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="tarikh">{{ __('Tarikh') }} <span class="text-danger">*</span></label>
                        <input type="date" name="tarikh" id="tarikh" class="form-control" required
                               value="{{ old('tarikh', now()->format('Y-m-d')) }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="deskripsi">{{ __('Deskripsi') }} <span class="text-danger">*</span></label>
                        <input type="text" name="deskripsi" id="deskripsi" class="form-control" required
                               value="{{ old('deskripsi') }}" maxlength="500">
                    </div>
                </div>
                <div class="col-md-3">
                    <x-money-input name="jumlah" label="Jumlah (RM)" />
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <x-coa-select name="dr_coa_id" label="Akaun Debit (Belanja 600/650)" :julat="['600','650']" />
                </div>
                <div class="col-md-6">
                    <x-coa-select name="cr_coa_id" label="Akaun Kredit (200/250/300)" :julat="['200','250','300']" />
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Proses Jurnal') }}</button>
            <a href="{{ route('belanja.menu') }}" class="btn btn-outline-secondary">{{ __('Batal') }}</a>
        </form>
    </div>
</div>
@endsection
