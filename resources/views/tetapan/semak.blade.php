@extends('layouts.app')

@section('title', __('Carian Padanan Kod Akaun'))

@section('content')
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('tetapan.semak') }}" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label" for="search">{{ __('Istilah Tempatan') }}</label>
                <input type="text" name="search" id="search" class="form-control" value="{{ $istilah }}"
                       placeholder="{{ __('cth: elaun, sumbangan, bil api') }}" maxlength="100" autofocus>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>{{ __('Semak Padanan') }}</button>
            </div>
        </form>
        <div class="small text-muted mt-2">
            {{ __('Carian fuzzy terhadap nama kod akaun (COA) dan label tempatan masjid — hanya padanan ≥ 40% dipaparkan.') }}
        </div>
    </div>
</div>

@if ($istilah !== '')
    <div class="card shadow-sm">
        <div class="card-header fw-bold">{{ __('Keputusan Padanan untuk') }} "{{ $istilah }}"</div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-hover table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:120px">{{ __('Kod') }}</th>
                        <th>{{ __('Nama') }}</th>
                        <th class="text-end" style="width:120px">{{ __('% Padanan') }}</th>
                        <th>{{ __('Catatan') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($keputusan as $r)
                        <tr>
                            <td>{{ $r->kod }}</td>
                            <td>{{ $r->nama }}</td>
                            <td class="text-end">
                                <span class="badge {{ $r->skor >= 70 ? 'text-bg-success' : 'text-bg-warning' }}">{{ $r->skor }}%</span>
                            </td>
                            <td>{{ $r->catatan ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">{{ __('Tiada padanan ≥ 40% ditemui.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
