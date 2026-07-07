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
                            <label class="form-label small" for="fail">{{ __('Fail Penyata (PDF/JPG/PNG, maks 10MB)') }}</label>
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
                            <div class="small text-muted">{{ __('Beza Bersih') }}</div><div class="fw-bold">{{ $laporan['beza'] }}</div>
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
                    <div class="card-header fw-bold bg-warning-subtle"><i class="bi bi-inbox me-1"></i>{{ __('Belum Direkod') }} ({{ $belum->count() }})</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light"><tr>
                                <th>{{ __('Tarikh') }}</th><th>{{ __('Deskripsi') }}</th>
                                <th class="text-end">{{ __('Masuk') }}</th><th class="text-end">{{ __('Keluar') }}</th>
                                <th>{{ __('Cadangan AI') }}</th><th class="text-end">{{ __('Tindakan') }}</th>
                            </tr></thead>
                            <tbody>
                                @forelse ($belum as $l)
                                    @php
                                        $masuk = (float) $l->kredit > 0;
                                        $cadCoa = $l->cadangan_coa_id ? ($masuk ? $coaHasil : $coaBelanja)->firstWhere('id', $l->cadangan_coa_id) : null;
                                    @endphp
                                    <tr>
                                        <td><small>{{ $l->tarikh instanceof \DateTimeInterface ? $l->tarikh->format('Y-m-d') : $l->tarikh }}</small></td>
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
                                    <tr><td colspan="6" class="text-center text-muted py-3">{{ __('Semua baris telah direkod atau diabaikan.') }}</td></tr>
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>{{ __('Rekod Transaksi') }}</button>
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
            const rekodBase = "{{ url('/semak-penyata/baris') }}";

            // Poll status semasa pemprosesan
            const kad = document.getElementById('kad-proses');
            if (kad) {
                const url = kad.dataset.url;
                setInterval(function () {
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
        });
    </script>
@endsection
