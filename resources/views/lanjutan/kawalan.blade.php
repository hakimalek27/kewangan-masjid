@extends('layouts.app')

@section('title', __('Kawalan Dalaman'))

@section('content')
<div class="row">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header fw-bold">{{ __('Tetapan Kawalan Dalaman') }}</div>
            <div class="card-body">
                <form method="POST" action="{{ route('kawalan.simpan') }}">
                    @csrf
                    <div class="form-check form-switch mb-3 border-bottom pb-3">
                        <input type="hidden" name="approval_enabled" value="0">
                        <input class="form-check-input" type="checkbox" name="approval_enabled" id="approval_enabled"
                               value="1" @checked((bool) old('approval_enabled', $approvalEnabled !== 'off'))>
                        <label class="form-check-label fw-semibold" for="approval_enabled">
                            {{ __('Hidupkan Kelulusan Pembayaran (Maker-Checker)') }}
                        </label>
                        <div class="form-text">
                            <strong>{{ __('Suis induk.') }}</strong>
                            {{ __('Jika dimatikan, semua bayaran terus direkod tanpa kelulusan — had di bawah diabaikan.') }}
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="approval_threshold">
                            {{ __('Had Kelulusan Maker-Checker (RM)') }} <span class="text-danger">*</span>
                        </label>
                        <input type="number" step="0.01" min="0" name="approval_threshold" id="approval_threshold"
                               class="form-control" required value="{{ old('approval_threshold', $threshold) }}">
                        <div class="form-text">
                            {{ __('Bayaran/rekupmen oleh') }} <strong>{{ __('bendahari') }}</strong> {{ __('melebihi had ini perlu kelulusan') }}
                            {{ __('admin/pengerusi di halaman') }} <a href="{{ route('kelulusan.index') }}">{{ __('Kelulusan') }}</a> {{ __('sebelum') }}
                            {{ __('direkodkan ke jurnal.') }} <strong>{{ __('0 = maker-checker dimatikan.') }}</strong> {{ __('Admin sentiasa lepas terus.') }}
                        </div>
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="fund_deficit_alert" value="0">
                        <input class="form-check-input" type="checkbox" name="fund_deficit_alert" id="fund_deficit_alert"
                               value="1" @checked((bool) old('fund_deficit_alert', $fundDeficitAlert !== 'off'))>
                        <label class="form-check-label" for="fund_deficit_alert">
                            {{ __('Amaran defisit dana/tabung harian (SecurityEvent HIGH + Telegram, sekali sehari)') }}
                        </label>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="budget_warning" value="0">
                        <input class="form-check-input" type="checkbox" name="budget_warning" id="budget_warning"
                               value="1" @checked((bool) old('budget_warning', $budgetWarning !== 'off'))>
                        <label class="form-check-label" for="budget_warning">
                            {{ __('Amaran belanjawan pada borang Perbelanjaan (semakan peruntukan masa nyata)') }}
                        </label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="baki_rendah_ambang">
                            {{ __('Ambang Notifikasi Baki Bank Rendah (RM)') }} <span class="text-danger">*</span>
                        </label>
                        <input type="number" step="0.01" min="0" name="baki_rendah_ambang" id="baki_rendah_ambang"
                               class="form-control" required value="{{ old('baki_rendah_ambang', $bakiRendahAmbang) }}">
                        <div class="form-text">
                            {{ __('Jika mana-mana akaun bank jatuh bawah ambang ini: notifikasi loceng + amaran') }}
                            {{ __('Telegram harian.') }} <strong>{{ __('0 = notifikasi dimatikan.') }}</strong>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Simpan') }}</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="alert alert-info small">
            <i class="bi bi-info-circle me-1"></i>
            {{ __('Kawalan ini menyokong pengasingan tugas (segregation of duties): pembuat (maker) dan') }}
            {{ __('pelulus (checker) adalah orang berbeza bagi amaun besar. Semua keputusan kelulusan') }}
            {{ __('direkod dalam jejak audit hash-chain.') }}
        </div>
    </div>
</div>
@endsection
