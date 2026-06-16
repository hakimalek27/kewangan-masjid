@extends('layouts.app')

@section('title', __('Tetapan AI & Telegram'))

@section('content')

<div class="alert alert-info small">
    <i class="bi bi-shield-lock me-1"></i>
    {{ __('Kunci API & token bot') }} <strong>{{ __('tidak disimpan plaintext') }}</strong> — {{ __('semuanya disulitkan dalam vault;') }}
    {{ __('jadual hanya menyimpan rujukan') }} (<code>*_ref</code>). {{ __('AI hanya mencipta') }} <strong>{{ __('draf') }}</strong>;
    {{ __('jurnal direkod selepas pengesahan bendahari sahaja.') }}
</div>

{{-- ===== Senarai Provider AI ===== --}}
<div class="card shadow-sm mb-4">
    <div class="card-header fw-bold"><i class="bi bi-cpu me-1"></i>{{ __('Provider AI Vision') }}</div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>Provider</th>
                    <th>Dialect</th>
                    <th>Model</th>
                    <th>Base URL</th>
                    <th>{{ __('Kunci API') }}</th>
                    <th class="text-center">{{ __('Aktif') }}</th>
                    <th class="text-center">Default</th>
                    <th class="no-print" style="width:220px">{{ __('Tindakan') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($providers as $p)
                    <tr>
                        <td>{{ $p->provider }}</td>
                        <td><code>{{ $p->dialect }}</code></td>
                        <td>{{ $p->model }}</td>
                        <td class="small">{{ $p->base_url ?: __('(lalai)') }}</td>
                        <td><code>{{ $p->api_key_masked }}</code></td>
                        <td class="text-center">
                            <span class="badge text-bg-{{ $p->is_active ? 'success' : 'secondary' }}">{{ $p->is_active ? __('Ya') : __('Tidak') }}</span>
                        </td>
                        <td class="text-center">
                            @if ($p->is_default)
                                <span class="badge text-bg-primary">Default</span>
                            @else
                                <form method="POST" action="{{ route('tetapan.ai.provider.default', $p->id) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('Jadikan Default') }}</button>
                                </form>
                            @endif
                        </td>
                        <td class="no-print">
                            <form method="POST" action="{{ route('tetapan.ai.provider.uji', $p->id) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-success">{{ __('Uji Sambungan') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted">{{ __('Tiada provider AI. Tambah di bawah.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ===== Borang Tambah Provider ===== --}}
<div class="card shadow-sm mb-4">
    <div class="card-header fw-bold">{{ __('Tambah Provider AI') }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('tetapan.ai.provider') }}">
            @csrf
            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="provider">Provider <span class="text-danger">*</span></label>
                        <select name="provider" id="provider" class="form-select" required>
                            @foreach (['OPENAI', 'ANTHROPIC', 'GEMINI', 'DEEPSEEK', 'QWEN', 'CUSTOM'] as $prov)
                                <option value="{{ $prov }}" @selected(old('provider') === $prov)>{{ $prov }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="dialect">{{ __('Dialect API') }} <span class="text-danger">*</span></label>
                        <select name="dialect" id="dialect" class="form-select" required>
                            <option value="openai" @selected(old('dialect') === 'openai')>{{ __('openai (juga DeepSeek/Qwen)') }}</option>
                            <option value="anthropic" @selected(old('dialect') === 'anthropic')>anthropic</option>
                            <option value="gemini" @selected(old('dialect') === 'gemini')>gemini</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="model">Model <span class="text-danger">*</span></label>
                        <input type="text" name="model" id="model" class="form-control" required maxlength="80"
                               value="{{ old('model') }}" placeholder="{{ __('cth: gpt-4o / claude-opus-4-8') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="base_url">{{ __('Base URL (pilihan)') }}</label>
                        <input type="url" name="base_url" id="base_url" class="form-control" maxlength="200"
                               value="{{ old('base_url') }}" placeholder="{{ __('cth: https://api.deepseek.com') }}">
                    </div>
                </div>
            </div>
            <div class="row align-items-end">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="api_key">{{ __('Kunci API') }} <span class="text-danger">*</span></label>
                        <input type="password" name="api_key" id="api_key" class="form-control" required maxlength="500"
                               autocomplete="off" placeholder="{{ __('Disimpan tersulit dalam vault') }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-check mb-3">
                        <input type="hidden" name="supports_vision" value="0">
                        <input class="form-check-input" type="checkbox" name="supports_vision" id="supports_vision" value="1" checked>
                        <label class="form-check-label" for="supports_vision">{{ __('Sokong Vision') }}</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-check mb-3">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" checked>
                        <label class="form-check-label" for="is_active">{{ __('Aktif') }}</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary w-100">{{ __('Simpan Provider') }}</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ===== Tetapan Telegram ===== --}}
<div class="card shadow-sm">
    <div class="card-header fw-bold"><i class="bi bi-telegram me-1"></i>{{ __('Bot Telegram (Group Resit)') }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('tetapan.ai.telegram') }}">
            @csrf
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="bot_token">{{ __('Token Bot') }} @if(!$bot)<span class="text-danger">*</span>@endif</label>
                        <input type="password" name="bot_token" id="bot_token" class="form-control" maxlength="200" autocomplete="off"
                               placeholder="{{ $bot ? __('Semasa:').' '.$botTokenMasked.' '.__('(kosongkan untuk kekal)') : '123456:ABC-DEF…' }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="chat_id">{{ __('Chat ID Group') }} <span class="text-danger">*</span></label>
                        <input type="number" name="chat_id" id="chat_id" class="form-control" required
                               value="{{ old('chat_id', $bot?->chat_id) }}" placeholder="cth: -1001234567890">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" for="default_jenis">{{ __('Jenis Lalai Draf') }}</label>
                        <select name="default_jenis" id="default_jenis" class="form-select" required>
                            <option value="BAYARAN" @selected(old('default_jenis', $bot?->default_jenis ?? 'BAYARAN') === 'BAYARAN')>BAYARAN</option>
                            <option value="KUTIPAN" @selected(old('default_jenis', $bot?->default_jenis) === 'KUTIPAN')>KUTIPAN</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-1">
                    <div class="form-check mt-4 pt-2">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" id="tg_is_active" value="1"
                               @checked(old('is_active', $bot?->is_active ?? true))>
                        <label class="form-check-label" for="tg_is_active">{{ __('Aktif') }}</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3 mt-4">
                        <button type="submit" class="btn btn-primary w-100">{{ __('Simpan Telegram') }}</button>
                    </div>
                </div>
            </div>
        </form>

        <hr>
        <h6 class="fw-bold">{{ __('Pendaftaran Webhook Telegram') }}</h6>
        <div class="small">
            <div class="mb-1">{{ __('URL Webhook:') }} <code>{{ $webhookUrl }}</code></div>
            <div class="mb-1">Secret (header <code>X-Telegram-Bot-Api-Secret-Token</code>):
                <code>{{ $webhookSecret !== '' ? $webhookSecret : __('(BELUM DISET — sila isi TELEGRAM_WEBHOOK_SECRET dalam .env)') }}</code>
            </div>
            <div class="text-muted mt-2">
                {{ __('Daftar dengan:') }}
                <code>curl "https://api.telegram.org/bot&lt;TOKEN&gt;/setWebhook?url={{ $webhookUrl }}&amp;secret_token=&lt;SECRET&gt;"</code>
            </div>
        </div>
    </div>
</div>
@endsection
