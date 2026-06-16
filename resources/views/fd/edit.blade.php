@extends('layouts.app')

@section('title', __('Edit Pelaburan FD'))

@section('content')
<div class="card shadow-sm">
    <div class="card-header fw-bold">
        {{ __('Edit Pelaburan FD') }}
        <span class="badge text-bg-warning ms-2">{{ __('Jumlah & akaun dikunci (jurnal POSTED)') }}</span>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            {{ __('Hanya maklumat') }} <strong>{{ __('bukan-kewangan') }}</strong> {{ __('boleh diubah. Jumlah pelaburan, Akaun FD dan Akaun Bank dikekalkan kerana ia menjejaskan jurnal yang telah diposkan.') }}
        </div>

        <form method="POST" action="{{ route('fd.kemaskini', $fd) }}">
            @csrf
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Tarikh Pelaburan') }}</label>
                        <input type="date" class="form-control" value="{{ $fd->tarikh?->format('Y-m-d') }}" disabled>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Jumlah Pelaburan (RM)') }}</label>
                        <input type="text" class="form-control" value="{{ number_format((float) $fd->jumlah, 2) }}" disabled>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Status') }}</label>
                        <input type="text" class="form-control" value="{{ $fd->status }}" disabled>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label" for="institusi">{{ __('Institusi') }} <span class="text-danger">*</span></label>
                        <input type="text" name="institusi" id="institusi" class="form-control" required
                               value="{{ old('institusi', $fd->institusi) }}" maxlength="150">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="no_sijil">{{ __('No. Sijil') }}</label>
                        <input type="text" name="no_sijil" id="no_sijil" class="form-control"
                               value="{{ old('no_sijil', $fd->no_sijil) }}" maxlength="60">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="kadar_pct">{{ __('Kadar (%)') }}</label>
                        <input type="number" name="kadar_pct" id="kadar_pct" class="form-control"
                               step="0.01" min="0" max="100" value="{{ old('kadar_pct', $fd->kadar_pct) }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="tempoh_bulan">{{ __('Tempoh (bulan)') }}</label>
                        <input type="number" name="tempoh_bulan" id="tempoh_bulan" class="form-control"
                               min="1" max="600" value="{{ old('tempoh_bulan', $fd->tempoh_bulan) }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="maturity_date">{{ __('Tarikh Matang') }}</label>
                        <input type="date" name="maturity_date" id="maturity_date" class="form-control"
                               value="{{ old('maturity_date', $fd->maturity_date?->format('Y-m-d')) }}">
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="keterangan">{{ __('Keterangan') }}</label>
                <textarea name="keterangan" id="keterangan" class="form-control" rows="2" maxlength="300">{{ old('keterangan', $fd->keterangan) }}</textarea>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i>{{ __('Kemaskini') }}
            </button>
            <a href="{{ $fd->is_opening ? route('fd.senarailama') : route('fd.senarai') }}" class="btn btn-outline-secondary">{{ __('Batal') }}</a>
        </form>
    </div>
</div>
@endsection
