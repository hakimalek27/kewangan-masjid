@extends('layouts.app')

@section('title', __('Setting Penyata'))

@section('content')
@php
    $bolehTulis = (bool) auth()->user()?->bolehUrusMasjid();
    $mode = old('mode', $setting?->mode ?? 'SIGNATURE');
@endphp

<div class="card shadow-sm mb-4">
    <div class="card-header fw-bold">{{ __('Tandatangan Penyata') }}</div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width:90px">{{ __('Susunan') }}</th>
                    <th>{{ __('Nama') }}</th>
                    <th>{{ __('Jawatan') }}</th>
                    <th>{{ __('Status') }}</th>
                    @if ($bolehTulis)<th class="no-print" style="width:140px">{{ __('Tindakan') }}</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($tandatangan as $t)
                    <tr>
                        <td class="text-center">{{ $t->susunan }}</td>
                        <td>{{ $t->nama }}</td>
                        <td>{{ $t->jawatan }}</td>
                        <td><span class="badge {{ $t->aktif ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $t->aktif ? __('AKTIF') : __('TIDAK AKTIF') }}</span></td>
                        @if ($bolehTulis)
                            <td class="no-print">
                                <a href="{{ route('penyata.setting.sig.edit', $t) }}" class="btn btn-sm btn-outline-primary">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('penyata.setting.sig.padam', $t) }}" class="d-inline"
                                      onsubmit="return confirm('{{ __('Padam tandatangan') }} \'{{ $t->nama }}\'?')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Padam') }}</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $bolehTulis ? 5 : 4 }}" class="text-center text-muted">{{ __('Tiada tandatangan didaftarkan.') }}</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($bolehTulis)
            <hr>
            <h6 class="fw-bold">{{ __('Tambah Tandatangan') }}</h6>
            <form method="POST" action="{{ route('penyata.setting.sig.simpan') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-4">
                    <label class="form-label" for="nama">{{ __('Nama') }} <span class="text-danger">*</span></label>
                    <input type="text" name="nama" id="nama" class="form-control" required value="{{ old('nama') }}" maxlength="200">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="jawatan">{{ __('Jawatan') }} <span class="text-danger">*</span></label>
                    <input type="text" name="jawatan" id="jawatan" class="form-control" required value="{{ old('jawatan') }}" maxlength="120">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="susunan">{{ __('Susunan') }} <span class="text-danger">*</span></label>
                    <input type="number" name="susunan" id="susunan" class="form-control" required min="1" max="99"
                           value="{{ old('susunan', $tandatangan->max('susunan') + 1 ?: 1) }}">
                </div>
                <div class="col-md-1">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="aktif" id="aktif" value="1" @checked(old('aktif', '1'))>
                        <label class="form-check-label" for="aktif">{{ __('Aktif') }}</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">{{ __('Simpan Tandatangan') }}</button>
                </div>
            </form>
        @endif
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Pilihan Paparan Penyata') }}</div>
    <div class="card-body">
        @if ($bolehTulis)
            <form method="POST" action="{{ route('penyata.setting.mod') }}" x-data="{ mode: '{{ $mode }}' }">
                @csrf
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="mode" id="mode_signature" value="SIGNATURE"
                           x-model="mode" @checked($mode === 'SIGNATURE')>
                    <label class="form-check-label" for="mode_signature">
                        <strong>{{ __('Tandatangan') }}</strong> — {{ __('papar senarai tandatangan di bahagian bawah penyata') }}
                    </label>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="radio" name="mode" id="mode_disclaimer" value="DISCLAIMER"
                           x-model="mode" @checked($mode === 'DISCLAIMER')>
                    <label class="form-check-label" for="mode_disclaimer">
                        <strong>{{ __('Disclaimer') }}</strong> — {{ __('papar teks penafian di bahagian bawah penyata') }}
                    </label>
                </div>
                <div class="mb-3" x-show="mode === 'DISCLAIMER'">
                    <label class="form-label" for="disclaimer_text">{{ __('Teks Disclaimer') }}</label>
                    <textarea name="disclaimer_text" id="disclaimer_text" class="form-control" rows="3"
                              maxlength="2000">{{ old('disclaimer_text', $setting?->disclaimer_text) }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Simpan Pilihan Paparan') }}</button>
            </form>
        @else
            <p class="mb-1">{{ __('Mod semasa:') }} <strong>{{ $mode }}</strong></p>
            @if ($mode === 'DISCLAIMER')
                <p class="text-muted mb-0">{{ $setting?->disclaimer_text }}</p>
            @endif
        @endif
    </div>
</div>
@endsection
