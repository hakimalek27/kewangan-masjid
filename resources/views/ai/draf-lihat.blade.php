@extends('layouts.app')

@section('title', __('Semak Draf AI').' #'.$draf->id)

@section('content')
@php
    $bolehTulis = in_array(auth()->user()?->role?->value, ['admin', 'bendahari'], true);
    $conf = (int) ($extraction->confidence ?? 0);
    $warnaConf = $conf >= 80 ? 'success' : ($conf >= 50 ? 'warning' : 'danger');
@endphp

@if ($conf < 80)
    <div class="alert alert-warning">
        ⚠️ {{ __('Keyakinan AI hanya') }} {{ $conf }}% — {{ __('sila semak setiap medan dengan teliti sebelum mengesahkan.') }}
    </div>
@endif

<div class="row g-4">
    {{-- ===== Pratonton imej resit ===== --}}
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-bold"><i class="bi bi-image me-1"></i>{{ __('Dokumen Asal (Telegram)') }}</div>
            <div class="card-body text-center">
                @if ($inbox && $inbox->file_path)
                    @if ($inbox->file_type === 'PDF')
                        <iframe src="{{ route('draf.imej', $draf->id) }}" style="width:100%;height:520px;border:0"></iframe>
                    @else
                        <a href="{{ route('draf.imej', $draf->id) }}" target="_blank">
                            <img src="{{ route('draf.imej', $draf->id) }}" alt="Resit"
                                 class="img-fluid rounded border" style="max-height:520px">
                        </a>
                    @endif
                    <div class="small text-muted mt-2">
                        {{ __('Dihantar oleh') }} {{ $inbox->tg_sender_name ?: '—' }}
                        {{ __('pada') }} {{ $inbox->received_at?->format('d/m/Y H:i') }}
                        @if ($inbox->caption)<br>{{ __('Kapsyen:') }} "{{ $inbox->caption }}"@endif
                    </div>
                @else
                    <p class="text-muted my-5">{{ __('Tiada fail dilampirkan.') }}</p>
                @endif
            </div>
        </div>
    </div>

    {{-- ===== Borang boleh-edit + maklumat AI ===== --}}
    <div class="col-lg-7">
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-bold"><i class="bi bi-pencil-square me-1"></i>{{ __('Butiran Draf (boleh diubah sebelum pengesahan)') }}</div>
            <div class="card-body">
                <form method="POST" action="{{ route('draf.sahkan', $draf->id) }}" id="borangSahkan">
                    @csrf
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label" for="jenis">{{ __('Jenis') }} <span class="text-danger">*</span></label>
                                <select name="jenis" id="jenis" class="form-select" required>
                                    <option value="KUTIPAN" @selected(old('jenis', $draf->jenis) === 'KUTIPAN')>{{ __('KUTIPAN (Terimaan)') }}</option>
                                    <option value="BAYARAN" @selected(old('jenis', $draf->jenis) === 'BAYARAN')>{{ __('BAYARAN (Belanja)') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label" for="tarikh">{{ __('Tarikh') }} <span class="text-danger">*</span></label>
                                <input type="date" name="tarikh" id="tarikh" class="form-control" required
                                       value="{{ old('tarikh', $draf->tarikh?->format('Y-m-d')) }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label" for="jumlah">{{ __('Jumlah (RM)') }} <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01" name="jumlah" id="jumlah" class="form-control text-end" required
                                       value="{{ old('jumlah', $draf->jumlah) }}">
                            </div>
                        </div>
                    </div>

                    <x-coa-select name="coa_id" label="Kod Akaun (COA)" :mapping="true" :selected="$draf->coa_id" />

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="penerima">{{ __('Penerima / Pemberi') }}</label>
                                <input type="text" name="penerima" id="penerima" class="form-control" maxlength="200"
                                       value="{{ old('penerima', $draf->penerima) }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="no_rujukan">{{ __('No Rujukan / Resit') }}</label>
                                <input type="text" name="no_rujukan" id="no_rujukan" class="form-control" maxlength="80"
                                       value="{{ old('no_rujukan', $draf->no_rujukan) }}">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="kaedah">{{ __('Kaedah') }}</label>
                                <select name="kaedah" id="kaedah" class="form-select">
                                    @foreach (['EFT' => 'EFT / Pindahan Bank', 'QR' => 'QR', 'CEK' => 'Cek', 'TUNAI' => 'Tunai'] as $k => $label)
                                        <option value="{{ $k }}" @selected(strtoupper(old('kaedah', $draf->kaedah ?? 'EFT')) === $k)>{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <x-bank-select name="bank_account_id" label="Akaun Bank" :selected="$draf->bank_account_id" />
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="deskripsi">{{ __('Deskripsi') }}</label>
                        <textarea name="deskripsi" id="deskripsi" class="form-control" rows="2" maxlength="500">{{ old('deskripsi', $draf->deskripsi) }}</textarea>
                    </div>

                    @if ($bolehTulis)
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success"
                                    onclick="return confirm('{{ __('Sahkan draf ini? Transaksi & jurnal sebenar akan direkodkan.') }}')">
                                ✅ {{ __('Sahkan & Rekod') }}
                            </button>
                            <button type="submit" form="borangTolak" class="btn btn-outline-danger"
                                    onclick="return confirm('{{ __('Tolak draf ini? Tiada transaksi akan direkodkan.') }}')">
                                ❌ {{ __('Tolak') }}
                            </button>
                            <a href="{{ route('draf.index') }}" class="btn btn-outline-secondary ms-auto">{{ __('Kembali') }}</a>
                        </div>
                    @else
                        <div class="alert alert-secondary small mb-0">{{ __('Akses baca sahaja — pengesahan oleh admin/bendahari.') }}</div>
                    @endif
                </form>

                @if ($bolehTulis)
                    <form method="POST" action="{{ route('draf.tolak', $draf->id) }}" id="borangTolak" class="mt-3">
                        @csrf
                        <label class="form-label small" for="sebab">{{ __('Sebab penolakan (pilihan)') }}</label>
                        <input type="text" name="sebab" id="sebab" class="form-control form-control-sm" maxlength="300"
                               placeholder="{{ __('cth: bukan resit masjid / pendua / tidak jelas') }}">
                    </form>
                @endif
            </div>
        </div>

        {{-- ===== Maklumat AI ===== --}}
        <div class="card shadow-sm">
            <div class="card-header fw-bold"><i class="bi bi-robot me-1"></i>{{ __('Maklumat Ekstrakan AI') }}</div>
            <div class="card-body">
                @if ($extraction)
                    <div class="row small mb-2">
                        <div class="col-md-6">{{ __('Provider:') }} <strong>{{ $extraction->provider }}</strong> ({{ $extraction->model }})</div>
                        <div class="col-md-6">{{ __('Jenis dokumen:') }} <strong>{{ $extraction->doc_type ?? '—' }}</strong></div>
                    </div>
                    <label class="form-label small mb-1">{{ __('Keyakinan AI:') }} <strong>{{ $conf }}%</strong></label>
                    <div class="progress mb-3" style="height:18px">
                        <div class="progress-bar bg-{{ $warnaConf }}" role="progressbar" style="width: {{ max($conf, 5) }}%">{{ $conf }}%</div>
                    </div>
                    <details>
                        <summary class="small text-muted">{{ __('Lihat JSON mentah AI') }}</summary>
                        <pre class="small bg-light p-2 rounded mt-2 mb-0" style="max-height:200px;overflow:auto">{{ json_encode($extraction->raw_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </details>
                @else
                    <p class="text-muted mb-0">{{ __('Tiada rekod ekstrakan AI untuk draf ini.') }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
