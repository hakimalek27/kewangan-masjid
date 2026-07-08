@extends('layouts.app')

@section('title', __('Semak Penyata (AI) — Kawalan'))

@section('content')
    {{-- Profil provider AI berbilang — banding kualiti OCR (OpenAI/DeepSeek/Ollama/OpenRouter) --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-bold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-robot me-1"></i>{{ __('Profil Provider AI (Semak Penyata)') }}</span>
            <span class="small text-muted">{{ __('Provider bertanda ⭐ Default digunakan oleh SEMUA tenant') }}</span>
        </div>
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
            @endif
            <p class="small text-muted mb-2">
                <i class="bi bi-info-circle me-1"></i>{{ __('Simpan profil untuk setiap provider (OpenAI/DeepSeek/Ollama/OpenRouter). Tandakan SATU sebagai Default — itulah provider + kunci yang SEMUA tenant guna untuk scan penyata. Tukar Default untuk banding.') }}
            </p>
            @unless ($providers->firstWhere('is_default', true))
                <div class="alert alert-warning py-2 small mb-2"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Belum ada provider Default — tenant tidak boleh scan penyata sehingga satu profil ditanda Default.') }}</div>
            @endunless
            <div class="table-responsive mb-3">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Nama') }}</th><th>{{ __('Model') }}</th><th>{{ __('Base URL') }}</th>
                            <th>{{ __('Kunci') }}</th><th class="text-center">{{ __('Aktif') }}</th>
                            <th class="text-center">{{ __('Default') }}</th><th class="text-end">{{ __('Tindakan') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($providers as $p)
                            <tr>
                                <td class="fw-semibold">{{ $p->nama }}</td>
                                <td><code>{{ $p->model }}</code></td>
                                <td class="small text-muted">{{ $p->base_url ?: 'https://api.openai.com' }}</td>
                                <td class="small">{{ $p->keyMasked ?? '—' }}</td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $p->is_active ? 'success' : 'secondary' }}">{{ $p->is_active ? __('Ya') : __('Tidak') }}</span>
                                </td>
                                <td class="text-center">@if ($p->is_default)<i class="bi bi-star-fill text-warning"></i>@endif</td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-prov"
                                            data-id="{{ $p->id }}" data-nama="{{ $p->nama }}" data-model="{{ $p->model }}"
                                            data-base="{{ $p->base_url }}" data-catatan="{{ $p->catatan }}"
                                            data-active="{{ $p->is_active ? 1 : 0 }}" data-default="{{ $p->is_default ? 1 : 0 }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="{{ route('admin.semakpenyata.provider.padam', $p->id) }}" class="d-inline"
                                          onsubmit="return confirm('{{ __('Padam profil') }} {{ $p->nama }}?')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted small">{{ __('Belum ada profil provider. Tambah di bawah.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <form method="POST" action="{{ route('admin.semakpenyata.provider') }}" class="row g-2 align-items-end">
                @csrf
                <input type="hidden" name="id" id="prov_id" value="">
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="prov_preset">{{ __('Provider AI') }}</label>
                    <select id="prov_preset" class="form-select form-select-sm">
                        @foreach ($aiKatalog as $prov)
                            <option value="{{ $prov['key'] }}" @selected($prov['key'] === 'openai')>{{ $prov['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="prov_nama">{{ __('Nama profil') }}</label>
                    <input type="text" name="nama" id="prov_nama" maxlength="80" required class="form-control form-control-sm" placeholder="cth OpenAI GPT-4o">
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-0" for="prov_model_pilih">{{ __('Model') }}</label>
                    <select id="prov_model_pilih" class="form-select form-select-sm mb-1"></select>
                    <input type="text" name="model" id="prov_model" maxlength="80" required class="form-control form-control-sm" placeholder="{{ __('ID model sebenar — cth openai/gpt-4o') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="prov_api_key">{{ __('Kunci API') }}</label>
                    <input type="password" name="api_key" id="prov_api_key" maxlength="200" autocomplete="off" class="form-control form-control-sm" placeholder="{{ __('(kosong = kekal)') }}">
                </div>
                <div class="col-md-5">
                    <label class="form-label small mb-0" for="prov_base">{{ __('Base URL') }}</label>
                    <input type="text" name="base_url" id="prov_base" maxlength="200" class="form-control form-control-sm" placeholder="{{ __('(kosong = OpenAI)') }}">
                    <div id="prov_pdf_warn" class="form-text small text-danger d-none"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Provider ini mungkin tidak sokong PDF — muat naik IMEJ (JPG/PNG).') }}</div>
                </div>
                <div class="col-md-3">
                    <div class="form-check form-check-inline">
                        <input type="checkbox" name="is_active" id="prov_active" value="1" checked class="form-check-input">
                        <label class="form-check-label small" for="prov_active">{{ __('Aktif') }}</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input type="checkbox" name="is_default" id="prov_default" value="1" class="form-check-input">
                        <label class="form-check-label small" for="prov_default">{{ __('Default (semua tenant)') }}</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <input type="text" name="catatan" id="prov_catatan" maxlength="200" class="form-control form-control-sm" placeholder="{{ __('Catatan (pilihan)') }}">
                </div>
                <div class="col-12 text-end">
                    <button type="button" id="prov_reset" class="btn btn-sm btn-outline-secondary">{{ __('Kosongkan') }}</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>{{ __('Simpan Profil') }}</button>
                </div>
            </form>
            <p class="form-text small mb-0 mt-2">
                <i class="bi bi-info-circle me-1"></i>{{ __('Pilih Provider → URL & senarai model auto-isi. Model mesti sokong VISION untuk OCR. PDF hanya OpenAI sokong penuh — provider lain guna IMEJ (JPG/PNG).') }}
            </p>
        </div>
    </div>

    <div class="row g-3">
        {{-- Kiri: toggle global + kunci pusat --}}
        <div class="col-xl-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-bold"><i class="bi bi-toggles me-1"></i>{{ __('Tetapan Global') }}</div>
                <div class="card-body">
                    <p class="small text-muted">
                        {{ __('Apabila DIHIDUPKAN, tenant boleh muat naik penyata bank untuk dianalisis AI. Menggunakan SATU kunci OpenAI pusat untuk semua tenant.') }}
                    </p>
                    <form method="POST" action="{{ route('admin.semakpenyata.toggle') }}">
                        @csrf
                        <input type="hidden" name="aktif" value="{{ $aktif ? 0 : 1 }}">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-{{ $aktif ? 'success' : 'secondary' }}">
                                {{ $aktif ? __('HIDUP') : __('MATI') }}
                            </span>
                            <button type="submit" class="btn btn-sm btn-{{ $aktif ? 'outline-danger' : 'success' }}"
                                    onclick="return confirm('{{ $aktif ? __('Matikan') : __('Hidupkan') }} {{ __('ciri Semak Penyata (AI)?') }}')">
                                <i class="bi bi-power me-1"></i>{{ $aktif ? __('Matikan') : __('Hidupkan') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header fw-bold"><i class="bi bi-key me-1"></i>{{ __('Kunci OpenAI Pusat') }}</div>
                <div class="card-body">
                    @if ($keyMasked)
                        <p class="small mb-2">{{ __('Kunci semasa') }}: <code>{{ $keyMasked }}</code></p>
                    @else
                        <p class="small text-danger mb-2"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Belum dikonfigurasi — ciri tidak akan berfungsi.') }}</p>
                    @endif
                    <form method="POST" action="{{ route('admin.semakpenyata.kunci') }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small" for="api_key">{{ __('Kunci API OpenAI') }}</label>
                            <input type="password" name="api_key" id="api_key" maxlength="200"
                                   class="form-control form-control-sm" autocomplete="off"
                                   placeholder="{{ $keyMasked ? __('(biar kosong untuk kekal)') : 'sk-...' }}">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small" for="model">{{ __('Model') }}</label>
                            <input type="text" name="model" id="model" maxlength="80" required
                                   class="form-control form-control-sm" value="{{ old('model', $model) }}" placeholder="gpt-4o">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small" for="base_url">{{ __('Base URL (pilihan)') }}</label>
                            <input type="url" name="base_url" id="base_url" maxlength="200"
                                   class="form-control form-control-sm" value="{{ old('base_url', $baseUrl) }}"
                                   placeholder="https://api.openai.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small" for="kos_per_1k">{{ __('Kos USD per 1000 token (anggaran)') }}</label>
                            <input type="number" step="0.0001" min="0" max="10" name="kos_per_1k" id="kos_per_1k"
                                   class="form-control form-control-sm" value="{{ old('kos_per_1k', $kosPer1k) }}" placeholder="0.005">
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>{{ __('Simpan') }}</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Kanan: kuota per-tenant --}}
        <div class="col-xl-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-bold"><i class="bi bi-people me-1"></i>{{ __('Kuota Per-Tenant (bulan semasa)') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Masjid') }}</th>
                                <th class="text-center">{{ __('Guna') }}</th>
                                <th class="text-center">{{ __('Had') }}</th>
                                <th class="text-center">{{ __('Top-up') }}</th>
                                <th class="text-center">{{ __('Baki') }}</th>
                                <th class="text-end">{{ __('Tindakan') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tenants as $t)
                                <tr>
                                    <td>{{ $t['nama'] }}</td>
                                    <td class="text-center">{{ $t['guna'] }}</td>
                                    <td class="text-center">
                                        @if ($t['had'] === 0)
                                            <span class="badge bg-secondary">{{ __('Mati') }}</span>
                                        @else
                                            {{ $t['had'] }}
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $t['topup'] > 0 ? '+'.$t['topup'] : '—' }}</td>
                                    <td class="text-center"><span class="badge bg-{{ $t['baki'] > 0 ? 'success' : 'danger' }}">{{ $t['baki'] }}</span></td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <form method="POST" action="{{ route('admin.semakpenyata.kuota', $t['id']) }}" class="d-inline-flex gap-1">
                                                @csrf
                                                <input type="number" name="kuota" min="0" max="1000" value="{{ $t['had'] }}"
                                                       class="form-control form-control-sm" style="width:5rem" title="{{ __('Had bulanan (0 = mati)') }}">
                                                <button class="btn btn-sm btn-outline-primary" title="{{ __('Simpan had') }}"><i class="bi bi-check-lg"></i></button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.semakpenyata.topup', $t['id']) }}" class="d-inline-flex gap-1">
                                                @csrf
                                                <input type="number" name="tambah" min="1" max="100" value="1"
                                                       class="form-control form-control-sm" style="width:4rem" title="{{ __('Top-up bulan ini') }}">
                                                <button class="btn btn-sm btn-outline-success" title="{{ __('Top-up') }}"><i class="bi bi-plus-lg"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Log penggunaan --}}
            <div class="card shadow-sm">
                <div class="card-header fw-bold"><i class="bi bi-clock-history me-1"></i>{{ __('Log Penggunaan') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>{{ __('Masjid') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th class="text-center">{{ __('Baris') }}</th>
                                <th class="text-center">{{ __('Auto padan') }}</th>
                                <th class="text-end">{{ __('Token') }}</th>
                                <th class="text-end">{{ __('Kos (USD)') }}</th>
                                <th>{{ __('Tarikh') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($log as $l)
                                <tr>
                                    <td>{{ $l->id }}</td>
                                    <td><small>{{ $namaMasjid[$l->masjid_id] ?? $l->masjid_id }}</small></td>
                                    <td>
                                        @php $warna = ['SEDIA'=>'success','GAGAL'=>'danger','AI_PROCESSING'=>'info','UPLOADED'=>'secondary'][$l->status] ?? 'secondary'; @endphp
                                        <span class="badge bg-{{ $warna }}">{{ $l->status }}</span>
                                    </td>
                                    <td class="text-center">{{ $l->bil_baris }}</td>
                                    <td class="text-center">{{ $l->bil_auto_padan }}</td>
                                    <td class="text-end">{{ $l->tokens_used ? number_format($l->tokens_used) : '—' }}</td>
                                    <td class="text-end">{{ $l->cost_usd !== null ? number_format((float) $l->cost_usd, 4) : '—' }}</td>
                                    <td><small>{{ $l->created_at?->format('Y-m-d H:i') }}</small></td>
                                    <td class="text-end">
                                        @if (in_array($l->status, ['UPLOADED', 'AI_PROCESSING'], true))
                                            <form method="POST" action="{{ route('admin.semakpenyata.gagalkan', $l->id) }}" class="d-inline"
                                                  onsubmit="return confirm('{{ __('Tanda batch ini GAGAL? Kuota tenant akan dibebaskan.') }}')">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-danger" title="{{ __('Tanda GAGAL (batch tersekat)') }}">
                                                    <i class="bi bi-x-octagon"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="text-center text-muted py-3">{{ __('Tiada rekod penggunaan lagi.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($log->hasPages())
                    <div class="card-footer">{{ $log->links() }}</div>
                @endif
            </div>
        </div>
    </div>

    <script>
        (function () {
            var CATALOG = @json($aiKatalog);
            var $ = function (id) { return document.getElementById(id); };
            var preset = $('prov_preset'), modelPilih = $('prov_model_pilih'),
                modelText = $('prov_model'), baseUrl = $('prov_base'), pdfWarn = $('prov_pdf_warn');
            var CUSTOM = '__custom__';

            function catalogByKey(key) { return CATALOG.filter(function (c) { return c.key === key; })[0]; }
            function catalogByBase(base) {
                base = (base || '').replace(/\/+$/, '');
                return CATALOG.filter(function (c) { return c.key !== 'custom' && (c.base_url || '').replace(/\/+$/, '') === base; })[0];
            }

            // Isi senarai model + base_url ikut provider dipilih. keepModel=true kekalkan teks model semasa (mod edit).
            function isiPreset(key, keepModel) {
                var c = catalogByKey(key) || catalogByKey('custom');
                if (c.key !== 'custom') { baseUrl.value = c.base_url || ''; }
                else if (!keepModel) { baseUrl.value = ''; }
                pdfWarn.classList.toggle('d-none', !!c.pdf); // amaran PDF kecuali provider yg sokong (OpenAI)

                modelPilih.innerHTML = '';
                (c.models || []).forEach(function (m) {
                    var o = document.createElement('option'); o.value = m; o.textContent = m; modelPilih.appendChild(o);
                });
                var oc = document.createElement('option'); oc.value = CUSTOM; oc.textContent = '— taip model sendiri —'; modelPilih.appendChild(oc);

                if (!keepModel) {
                    if ((c.models || []).length) { modelText.value = c.models[0]; modelPilih.value = c.models[0]; }
                    else { modelText.value = ''; modelPilih.value = CUSTOM; }
                } else {
                    // Edit: padankan model semasa dgn senarai; jika tiada → custom.
                    modelPilih.value = (c.models || []).indexOf(modelText.value) >= 0 ? modelText.value : CUSTOM;
                }
            }

            if (preset) {
                preset.addEventListener('change', function () { isiPreset(preset.value, false); });
                modelPilih.addEventListener('change', function () {
                    if (modelPilih.value === CUSTOM) { modelText.value = ''; modelText.focus(); }
                    else { modelText.value = modelPilih.value; }
                });
                isiPreset(preset.value, false); // muatan awal
            }

            document.querySelectorAll('.btn-edit-prov').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var d = btn.dataset;
                    $('prov_id').value = d.id || '';
                    $('prov_nama').value = d.nama || '';
                    modelText.value = d.model || '';
                    baseUrl.value = d.base || '';
                    $('prov_catatan').value = d.catatan || '';
                    $('prov_api_key').value = '';
                    $('prov_active').checked = d.active === '1';
                    $('prov_default').checked = d.default === '1';
                    // Padankan provider ikut base_url; kekalkan model sedia ada.
                    var match = (d.base ? catalogByBase(d.base) : catalogByKey('openai'));
                    preset.value = match ? match.key : 'custom';
                    isiPreset(preset.value, true);
                    $('prov_nama').scrollIntoView({ behavior: 'smooth', block: 'center' });
                    $('prov_nama').focus();
                });
            });

            var reset = $('prov_reset');
            if (reset) {
                reset.addEventListener('click', function () {
                    ['prov_id', 'prov_nama', 'prov_catatan', 'prov_api_key'].forEach(function (id) { $(id).value = ''; });
                    $('prov_active').checked = true;
                    $('prov_default').checked = false;
                    preset.value = 'openai';
                    isiPreset('openai', false);
                });
            }
        })();
    </script>
@endsection
