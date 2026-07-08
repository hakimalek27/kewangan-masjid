@extends('layouts.app')

@section('title', __('Semak Penyata (AI)'))

@section('content')
    <div class="row g-3">
        {{-- Muat naik + kuota --}}
        <div class="col-lg-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header fw-bold"><i class="bi bi-cloud-arrow-up me-1"></i>{{ __('Muat Naik Penyata Bank') }}</div>
                <div class="card-body">
                    @php $kuotaOk = $kuotaGlobal && $kuotaHad > 0 && $kuotaBaki > 0; @endphp
                    <div class="alert alert-{{ $kuotaOk ? 'info' : 'warning' }} py-2 small mb-3">
                        @if (!$kuotaGlobal)
                            <i class="bi bi-exclamation-triangle me-1"></i>{{ __('Ciri ini tidak aktif buat masa ini.') }}
                        @elseif ($kuotaHad === 0)
                            <i class="bi bi-exclamation-triangle me-1"></i>{{ __('Ciri ini dimatikan untuk masjid anda.') }}
                        @else
                            <i class="bi bi-info-circle me-1"></i>{{ __('Penggunaan bulan ini') }}:
                            <strong>{{ $kuotaGuna }}/{{ $kuotaHad }}</strong> — {{ __('baki') }} <strong>{{ $kuotaBaki }}</strong>
                        @endif
                    </div>

                    <p class="small text-muted">{{ __('Muat naik penyata bank (PDF atau imej). AI akan senaraikan setiap transaksi dan bandingkan dengan rekod sistem.') }}</p>

                    <form method="POST" action="{{ route('semakpenyata.muatnaik') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small" for="bank_account_id">{{ __('Akaun Bank') }}</label>
                            <select name="bank_account_id" id="bank_account_id" class="form-select form-select-sm" required @disabled(!$kuotaOk)>
                                @foreach ($banks as $b)
                                    <option value="{{ $b->id }}">{{ $b->nama_bank }} — {{ $b->no_akaun }}</option>
                                @endforeach
                            </select>
                            @error('bank_account_id')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label small" for="fail">{{ __('Fail Penyata (PDF/JPG/PNG, maks 100MB)') }}</label>
                            <input type="file" name="fail" id="fail" accept=".pdf,.jpg,.jpeg,.png"
                                   class="form-control form-control-sm" required @disabled(!$kuotaOk)>
                            @error('fail')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary" @disabled(!$kuotaOk)>
                            <i class="bi bi-robot me-1"></i>{{ __('Proses dengan AI') }}
                        </button>
                    </form>
                </div>
            </div>

            @if ($batches->isNotEmpty())
                <div class="card shadow-sm">
                    <div class="card-header fw-bold"><i class="bi bi-collection me-1"></i>{{ __('Penyata Terkini') }}</div>
                    <div class="list-group list-group-flush">
                        @foreach ($batches as $b)
                            <a href="{{ route('semakpenyata.index', ['batch' => $b->id]) }}"
                               class="list-group-item list-group-item-action py-2 {{ $batch && $batch->id === $b->id ? 'active' : '' }}">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-truncate">#{{ $b->id }} {{ $b->original_name }}</small>
                                    @php $w = ['SEDIA'=>'success','GAGAL'=>'danger','AI_PROCESSING'=>'info','UPLOADED'=>'secondary'][$b->status] ?? 'secondary'; @endphp
                                    <span class="badge bg-{{ $w }} ms-1">{{ $b->status }}</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Jadual dua lajur --}}
        <div class="col-lg-8">
            @if (!$batch)
                <div class="card shadow-sm"><div class="card-body text-center text-muted py-5">
                    {{ __('Muat naik penyata bank untuk bermula.') }}
                </div></div>
            @elseif (in_array($batch->status, ['UPLOADED', 'AI_PROCESSING']))
                <div class="card shadow-sm" id="kad-proses" data-batch="{{ $batch->id }}"
                     data-url="{{ route('semakpenyata.status', $batch->id) }}">
                    <div class="card-body text-center py-5">
                        <div class="spinner-border text-primary mb-3" role="status"></div>
                        <div class="fw-bold">{{ __('AI sedang memproses penyata…') }}</div>
                        <div class="small text-muted">{{ __('Ini mungkin mengambil masa sehingga satu minit. Halaman akan dikemas kini automatik.') }}</div>
                    </div>
                </div>
            @elseif ($batch->status === 'GAGAL')
                <div class="card shadow-sm border-danger">
                    <div class="card-body">
                        <h6 class="text-danger"><i class="bi bi-x-octagon me-1"></i>{{ __('Pemprosesan AI gagal') }}</h6>
                        <p class="small mb-0">{{ $batch->error_text ?: __('Ralat tidak diketahui.') }}</p>
                    </div>
                </div>
            @else
                {{-- KPI --}}
                @if ($laporan)
                    <div class="row g-2 mb-3">
                        <div class="col"><div class="card text-center shadow-sm"><div class="card-body py-2">
                            <div class="small text-muted">{{ __('Jumlah Baris') }}</div><div class="fw-bold">{{ $belum->count() + $sudah->count() }}</div>
                        </div></div></div>
                        <div class="col"><div class="card text-center shadow-sm"><div class="card-body py-2">
                            <div class="small text-muted">{{ __('Belum Direkod') }}</div><div class="fw-bold text-warning">{{ $belum->count() }}</div>
                        </div></div></div>
                        <div class="col"><div class="card text-center shadow-sm"><div class="card-body py-2">
                            <div class="small text-muted">{{ __('Sudah Padan') }}</div><div class="fw-bold text-success">{{ $sudah->where('status','MATCHED')->count() }}</div>
                        </div></div></div>
                        <div class="col"><div class="card text-center shadow-sm"><div class="card-body py-2">
                            <div class="small text-muted" title="{{ __('Penyata vs buku — meliputi SEMUA baris penyata akaun bank ini (bukan batch ini sahaja)') }}">{{ __('Beza Akaun') }}</div><div class="fw-bold">{{ $laporan['beza'] }}</div>
                        </div></div></div>
                    </div>
                @endif

                <div class="mb-2">
                    <a href="{{ route('semakpenyata.fail', $batch->id) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-file-earmark-text me-1"></i>{{ __('Lihat fail penyata') }}
                    </a>
                </div>

                @error('rekod')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror

                {{-- KIRI: Belum Direkod --}}
                <div class="card shadow-sm mb-3">
                    <div class="card-header fw-bold bg-warning-subtle d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span><i class="bi bi-inbox me-1"></i>{{ __('Belum Direkod') }} ({{ $belum->count() }})</span>
                        <div id="lumpBar" class="d-none align-items-center gap-2">
                            <span class="small"><strong id="lumpCount">0</strong> {{ __('dipilih') }} · RM <strong id="lumpSum">0.00</strong></span>
                            <button type="button" class="btn btn-sm btn-primary" id="btnLumpSum" title="{{ __('Longgok baris dipilih jadi 1 rekod') }}">
                                <i class="bi bi-collection me-1"></i>{{ __('Rekod Lump-Sum') }}
                            </button>
                            <span id="lumpMixWarn" class="small text-danger d-none">{{ __('Pilih satu jenis sahaja (masuk/keluar).') }}</span>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light"><tr>
                                <th style="width:28px"><input type="checkbox" id="lumpAll" class="form-check-input" title="{{ __('Pilih semua') }}"></th>
                                <th>{{ __('Tarikh') }}</th><th>{{ __('Deskripsi') }}</th>
                                <th class="text-end">{{ __('Masuk') }}</th><th class="text-end">{{ __('Keluar') }}</th>
                                <th>{{ __('Cadangan AI') }}</th><th class="text-end">{{ __('Tindakan') }}</th>
                            </tr></thead>
                            <tbody>
                                @forelse ($belum as $l)
                                    @php
                                        $masuk = (float) $l->kredit > 0;
                                        $cadCoa = $l->cadangan_coa_id ? ($masuk ? $coaHasil : $coaBelanja)->firstWhere('id', $l->cadangan_coa_id) : null;
                                        $tarikhStr = $l->tarikh instanceof \DateTimeInterface ? $l->tarikh->format('Y-m-d') : $l->tarikh;
                                    @endphp
                                    <tr>
                                        <td><input type="checkbox" class="form-check-input lump-chk"
                                                   data-line="{{ $l->id }}" data-side="{{ $masuk ? 'masuk' : 'keluar' }}"
                                                   data-amount="{{ $masuk ? $l->kredit : $l->debit }}" data-date="{{ $tarikhStr }}"
                                                   data-coa="{{ $l->cadangan_coa_id }}"></td>
                                        <td><small>{{ $tarikhStr }}</small></td>
                                        <td><small>{{ $l->deskripsi }}</small></td>
                                        <td class="text-end text-success">{{ $masuk ? number_format((float) $l->kredit, 2) : '' }}</td>
                                        <td class="text-end text-danger">{{ !$masuk ? number_format((float) $l->debit, 2) : '' }}</td>
                                        <td>
                                            <small>{{ $cadCoa ? $cadCoa->kod : '—' }}</small>
                                            @if ($l->ai_confidence !== null)
                                                <span class="badge bg-{{ $l->ai_confidence >= 80 ? 'success' : ($l->ai_confidence >= 50 ? 'warning' : 'secondary') }} ms-1">{{ $l->ai_confidence }}%</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-success btn-rekod"
                                                    data-line="{{ $l->id }}"
                                                    data-jenis="{{ $masuk ? 'KUTIPAN' : 'BAYARAN' }}"
                                                    data-tarikh="{{ $l->tarikh instanceof \DateTimeInterface ? $l->tarikh->format('Y-m-d') : $l->tarikh }}"
                                                    data-jumlah="{{ $masuk ? $l->kredit : $l->debit }}"
                                                    data-deskripsi="{{ $l->deskripsi }}"
                                                    data-coa="{{ $l->cadangan_coa_id }}">
                                                <i class="bi bi-check-lg"></i> {{ __('Rekod') }}
                                            </button>
                                            <form method="POST" action="{{ route('semakpenyata.abai', $l->id) }}" class="d-inline"
                                                  onsubmit="return confirm('{{ __('Abaikan baris ini?') }}')">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-secondary" title="{{ __('Abai') }}"><i class="bi bi-slash-circle"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center text-muted py-3">{{ __('Semua baris telah direkod atau diabaikan.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- KANAN: Sudah Padan / Direkod --}}
                <div class="card shadow-sm">
                    <div class="card-header fw-bold bg-success-subtle"><i class="bi bi-check2-all me-1"></i>{{ __('Sudah Padan / Direkod') }} ({{ $sudah->count() }})</div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr>
                                <th>{{ __('Tarikh') }}</th><th>{{ __('Deskripsi') }}</th>
                                <th class="text-end">{{ __('Jumlah') }}</th><th>{{ __('Voucher') }}</th><th>{{ __('Status') }}</th>
                            </tr></thead>
                            <tbody>
                                @forelse ($sudah as $l)
                                    @php $masuk = (float) $l->kredit > 0; @endphp
                                    <tr>
                                        <td><small>{{ $l->tarikh instanceof \DateTimeInterface ? $l->tarikh->format('Y-m-d') : $l->tarikh }}</small></td>
                                        <td><small>{{ $l->deskripsi }}</small></td>
                                        <td class="text-end">{{ number_format($masuk ? (float) $l->kredit : (float) $l->debit, 2) }}</td>
                                        <td><small>{{ $l->voucher_ref ?? '—' }}</small></td>
                                        <td><span class="badge bg-{{ $l->status === 'MATCHED' ? 'success' : 'secondary' }}">{{ $l->status }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-3">{{ __('Tiada baris dipadan lagi.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Modal Rekod --}}
    <div class="modal fade" id="modalRekod" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" id="formRekod" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Rekod Baris Penyata') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small">{{ __('Jenis') }}</label>
                        <div><span class="badge" id="rk-jenis"></span></div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col"><label class="form-label small">{{ __('Tarikh') }}</label><input type="text" id="rk-tarikh" class="form-control form-control-sm" readonly></div>
                        <div class="col"><label class="form-label small">{{ __('Jumlah (RM)') }}</label><input type="text" id="rk-jumlah" class="form-control form-control-sm" readonly></div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small" for="rk-coa">{{ __('Kod Akaun (COA)') }} <span class="text-danger">*</span></label>
                        <select name="coa_id" id="rk-coa" class="form-select form-select-sm" required></select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small" for="rk-penerima">{{ __('Penerima / Pemberi') }}</label>
                        <input type="text" name="penerima" id="rk-penerima" maxlength="200" class="form-control form-control-sm">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small" for="rk-deskripsi">{{ __('Deskripsi') }}</label>
                        <input type="text" name="deskripsi" id="rk-deskripsi" maxlength="500" class="form-control form-control-sm">
                    </div>
                    <div class="alert alert-light border py-1 px-2 small mb-0">
                        <i class="bi bi-info-circle me-1"></i>{{ __('Belian aset atau pemindahan PWR? Guna Abai dan rekod di modul asal (Perbelanjaan Aset / Rekupmen).') }}
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>{{ __('Rekod Transaksi') }}</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Lump-Sum (longgok banyak baris jadi 1 rekod) --}}
    <div class="modal fade" id="modalLump" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('semakpenyata.lumpsum') }}" id="formLump" class="modal-content">
                @csrf
                <input type="hidden" name="batch" value="{{ $batch?->id }}">
                <div id="lumpIds"></div>
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Rekod Lump-Sum (Longgok)') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-primary py-2 small mb-2">
                        <i class="bi bi-collection me-1"></i><strong id="lm-bil">0</strong> {{ __('baris akan dilonggok jadi SATU rekod berjumlah') }} RM <strong id="lm-total">0.00</strong>.
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col"><label class="form-label small">{{ __('Jenis') }}</label><div><span class="badge" id="lm-jenis"></span></div></div>
                        <div class="col"><label class="form-label small" for="lm-tarikh">{{ __('Tarikh') }} <span class="text-danger">*</span></label><input type="date" name="tarikh" id="lm-tarikh" class="form-control form-control-sm" required></div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small" for="lm-coa">{{ __('Kod Akaun (COA)') }} <span class="text-danger">*</span></label>
                        <select name="coa_id" id="lm-coa" class="form-select form-select-sm" required></select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small" for="lm-deskripsi">{{ __('Deskripsi') }}</label>
                        <input type="text" name="deskripsi" id="lm-deskripsi" maxlength="500" class="form-control form-control-sm" placeholder="{{ __('cth Infaq/Sedekah QR') }}">
                    </div>
                    <input type="hidden" name="penerima" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-collection me-1"></i>{{ __('Rekod Lump-Sum') }}</button>
                </div>
            </form>
        </div>
    </div>

    @php
        $coaHasilJson = $coaHasil->map(fn ($c) => ['id' => $c->id, 't' => $c->kod.' — '.$c->nama])->values();
        $coaBelanjaJson = $coaBelanja->map(fn ($c) => ['id' => $c->id, 't' => $c->kod.' — '.$c->nama])->values();
    @endphp
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const COA = { KUTIPAN: @json($coaHasilJson), BAYARAN: @json($coaBelanjaJson) };
            const rekodBase = @json(route('semakpenyata.index')) + '/baris';

            // Poll status semasa pemprosesan — berhenti selepas ~5 minit (75 × 4s)
            // dan tunjuk mesej supaya spinner tidak berputar selamanya jika job hilang.
            const kad = document.getElementById('kad-proses');
            if (kad) {
                const url = kad.dataset.url;
                let cubaan = 0;
                const timer = setInterval(function () {
                    if (++cubaan > 75) {
                        clearInterval(timer);
                        const badan = kad.querySelector('.card-body');
                        if (badan) badan.innerHTML = '<div class="text-warning py-4"><i class="bi bi-hourglass-split fs-3 d-block mb-2"></i>'
                            + @json(__('Pemprosesan mengambil masa luar biasa. Sila muat semula halaman kemudian atau hubungi pentadbir sistem.')) + '</div>';
                        return;
                    }
                    fetch(url, { headers: { 'Accept': 'application/json' } })
                        .then(r => r.json())
                        .then(d => { if (d.status === 'SEDIA' || d.status === 'GAGAL') location.reload(); })
                        .catch(() => {});
                }, 4000);
            }

            // Modal Rekod
            const modalEl = document.getElementById('modalRekod');
            const modal = modalEl ? new window.bootstrap.Modal(modalEl) : null;
            document.querySelectorAll('.btn-rekod').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const d = btn.dataset;
                    const jenis = d.jenis;
                    document.getElementById('formRekod').action = rekodBase + '/' + d.line + '/rekod';
                    const badge = document.getElementById('rk-jenis');
                    badge.textContent = jenis === 'KUTIPAN' ? '{{ __('Kutipan (Wang Masuk)') }}' : '{{ __('Belanja (Wang Keluar)') }}';
                    badge.className = 'badge bg-' + (jenis === 'KUTIPAN' ? 'success' : 'danger');
                    document.getElementById('rk-tarikh').value = d.tarikh;
                    document.getElementById('rk-jumlah').value = d.jumlah;
                    document.getElementById('rk-penerima').value = '';
                    document.getElementById('rk-deskripsi').value = d.deskripsi || '';
                    const sel = document.getElementById('rk-coa');
                    sel.innerHTML = '';
                    (COA[jenis] || []).forEach(function (c) {
                        const o = document.createElement('option');
                        o.value = c.id; o.textContent = c.t;
                        if (String(c.id) === String(d.coa)) o.selected = true;
                        sel.appendChild(o);
                    });
                    modal.show();
                });
            });

            // ==== Lump-Sum: pilih banyak baris → longgok jadi 1 rekod ====
            const lumpBar = document.getElementById('lumpBar');
            const chks = Array.from(document.querySelectorAll('.lump-chk'));
            const lumpAll = document.getElementById('lumpAll');
            const btnLump = document.getElementById('btnLumpSum');
            const lumpMixWarn = document.getElementById('lumpMixWarn');
            const lumpModalEl = document.getElementById('modalLump');
            const lumpModal = lumpModalEl ? new window.bootstrap.Modal(lumpModalEl) : null;

            function terpilih() { return chks.filter(c => c.checked); }
            function kemasBar() {
                const sel = terpilih();
                const mixed = new Set(sel.map(c => c.dataset.side)).size > 1;
                const total = sel.reduce((s, c) => s + parseFloat(c.dataset.amount || 0), 0);
                if (document.getElementById('lumpCount')) document.getElementById('lumpCount').textContent = sel.length;
                if (document.getElementById('lumpSum')) document.getElementById('lumpSum').textContent = total.toFixed(2);
                if (lumpBar) { lumpBar.classList.toggle('d-none', sel.length === 0); lumpBar.classList.toggle('d-flex', sel.length > 0); }
                if (lumpMixWarn) lumpMixWarn.classList.toggle('d-none', !mixed);
                if (btnLump) btnLump.disabled = mixed || sel.length < 2;
            }
            chks.forEach(c => c.addEventListener('change', kemasBar));
            if (lumpAll) lumpAll.addEventListener('change', function () { chks.forEach(c => { c.checked = lumpAll.checked; }); kemasBar(); });

            if (btnLump && lumpModal) {
                btnLump.addEventListener('click', function () {
                    const sel = terpilih();
                    if (sel.length < 2) return;
                    const masuk = sel[0].dataset.side === 'masuk';
                    const jenis = masuk ? 'KUTIPAN' : 'BAYARAN';
                    const total = sel.reduce((s, c) => s + parseFloat(c.dataset.amount || 0), 0);
                    document.getElementById('lm-bil').textContent = sel.length;
                    document.getElementById('lm-total').textContent = total.toFixed(2);
                    const badge = document.getElementById('lm-jenis');
                    badge.textContent = masuk ? '{{ __('Kutipan (Wang Masuk)') }}' : '{{ __('Belanja (Wang Keluar)') }}';
                    badge.className = 'badge bg-' + (masuk ? 'success' : 'danger');
                    const dates = new Set(sel.map(c => c.dataset.date));
                    document.getElementById('lm-tarikh').value = dates.size === 1 ? sel[0].dataset.date : '';
                    const selCoa = document.getElementById('lm-coa');
                    selCoa.innerHTML = '';
                    const cadDefault = sel[0].dataset.coa;
                    (COA[jenis] || []).forEach(function (c) {
                        const o = document.createElement('option'); o.value = c.id; o.textContent = c.t;
                        if (String(c.id) === String(cadDefault)) o.selected = true;
                        selCoa.appendChild(o);
                    });
                    const box = document.getElementById('lumpIds');
                    box.innerHTML = '';
                    sel.forEach(function (c) {
                        const inp = document.createElement('input');
                        inp.type = 'hidden'; inp.name = 'line_ids[]'; inp.value = c.dataset.line;
                        box.appendChild(inp);
                    });
                    lumpModal.show();
                });
            }
        });
    </script>
@endsection
