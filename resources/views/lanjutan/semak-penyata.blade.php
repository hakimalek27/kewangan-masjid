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
                        <div class="mb-2">
                            <label class="form-label small" for="fail">{{ __('Fail Penyata (PDF/JPG/PNG, maks 100MB)') }}</label>
                            <input type="file" name="fail" id="fail" accept=".pdf,.jpg,.jpeg,.png"
                                   class="form-control form-control-sm" required @disabled(!$kuotaOk)>
                            @error('fail')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>

                        {{-- Notis & persetujuan PDPA (WAJIB sebelum muat naik) --}}
                        <div class="border border-warning rounded bg-warning-subtle p-2 mb-2 small">
                            <div class="fw-bold mb-1"><i class="bi bi-shield-lock me-1"></i>{{ __('Notis Perlindungan Data Peribadi (PDPA 2010)') }}</div>
                            <p class="mb-1">{{ __('Penyata bank mengandungi maklumat peribadi & kewangan yang sensitif — termasuk nama penderma dan pembayar. Sebelum memuat naik, sila pastikan:') }}</p>
                            <ul class="mb-0 ps-3">
                                <li>{{ __('Anda pegawai masjid yang DIBENARKAN mengendalikan penyata ini.') }}</li>
                                <li>{{ __('Maklumat digunakan SEMATA-MATA untuk rekod kewangan masjid (padanan penyata & resit).') }}</li>
                                <li>{{ __('Fail diproses oleh sistem dan disimpan dengan selamat; anda boleh memadamnya pada bila-bila masa.') }}</li>
                            </ul>
                        </div>
                        <div class="form-check small mb-3">
                            <input type="checkbox" class="form-check-input" id="pdpa_setuju" name="pdpa_setuju" value="1" required @disabled(!$kuotaOk)>
                            <label class="form-check-label" for="pdpa_setuju">{{ __('Saya faham & mengesahkan perkara di atas, serta bersetuju memuat naik penyata bank ini.') }}</label>
                            @error('pdpa_setuju')<div class="text-danger">{{ $message }}</div>@enderror
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
                                    @php $w = ['SEDIA'=>'success','GAGAL'=>'danger','AI_PROCESSING'=>'info','UPLOADED'=>'secondary','DIBATAL'=>'dark','DIPADAM'=>'secondary'][$b->status] ?? 'secondary'; @endphp
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
                        <div class="fw-bold" id="proses-tajuk">{{ __('AI sedang memproses penyata…') }}</div>
                        <div class="small text-muted mt-1" id="proses-nota">
                            <i class="bi bi-info-circle me-1"></i>{{ __('Anda boleh tutup halaman ini — pemprosesan berterusan di latar belakang. Hasil akan tersedia di sini apabila siap.') }}
                        </div>
                        <div class="progress mt-3 mx-auto d-none" id="proses-bar-wrap" style="height:8px; max-width:320px">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" id="proses-bar" role="progressbar" style="width:0%"></div>
                        </div>
                        <div class="small text-muted mt-2 d-none" id="proses-muka"></div>
                        <div class="small text-muted mt-2">
                            <i class="bi bi-lightning-charge me-1"></i>{{ __('Penyata DIGITAL (muat turun dari bank) diproses dalam beberapa saat. Penyata IMBASAN (scan) berbilang muka mengambil masa lebih lama.') }}
                        </div>
                        <form method="POST" action="{{ route('semakpenyata.batal', $batch->id) }}" class="mt-3"
                              onsubmit="return confirm('{{ __('Batalkan pemprosesan AI ini? Hasil separa tidak akan disimpan.') }}')">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-x-circle me-1"></i>{{ __('Batalkan Pemprosesan') }}
                            </button>
                        </form>
                    </div>
                </div>
            @elseif ($batch->status === 'DIBATAL')
                <div class="card shadow-sm border-secondary">
                    <div class="card-body">
                        <h6 class="text-secondary"><i class="bi bi-x-octagon me-1"></i>{{ __('Pemprosesan dibatalkan') }}</h6>
                        <p class="small mb-0">{{ $batch->error_text ?: __('Penyata ini dibatalkan. Muat naik semula untuk scan sekali lagi.') }}</p>
                    </div>
                </div>
            @elseif ($batch->status === 'DIPADAM')
                <div class="card shadow-sm border-secondary">
                    <div class="card-body">
                        <h6 class="text-secondary"><i class="bi bi-trash me-1"></i>{{ __('Penyata telah dipadam') }}</h6>
                        <p class="small mb-1">{{ $batch->error_text ?: __('Fail & data penyata ini telah dipadam.') }}</p>
                        <p class="small text-muted mb-0"><i class="bi bi-info-circle me-1"></i>{{ __('Kuota bagi scan ini KEKAL digunakan (scan sudah dijalankan). Untuk semak semula, muat naik penyata sekali lagi jika ada baki kuota.') }}</p>
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

                {{-- Peringatan SEMAK SILANG manual (wajib) --}}
                <div class="alert alert-warning py-2 small">
                    <i class="bi bi-exclamation-triangle me-1"></i><strong>{{ __('Semak silang secara manual.') }}</strong>
                    @if ($batch->kaedah === 'PARSE')
                        {{ __('Data dibaca terus dari penyata (tepat), tetapi sila SAHKAN kod akaun (COA) setiap baris dan bandingkan dengan penyata bank asal sebelum merekod.') }}
                    @else
                        {{ __('Hasil scan AI/imbasan TIDAK 100% tepat — boleh ada silap baca atau baris tertinggal. Sila bandingkan dengan penyata bank asal dan sahkan setiap baris sebelum merekod.') }}
                    @endif
                </div>

                {{-- Amaran had kos (proses separa) — status SEDIA tetapi ada error_text --}}
                @if ($batch->error_text)
                    <div class="alert alert-warning py-2 small">
                        <i class="bi bi-exclamation-triangle me-1"></i>{{ $batch->error_text }}
                    </div>
                @endif

                <div class="mb-2 d-flex gap-2 flex-wrap">
                    <a href="{{ route('semakpenyata.fail', $batch->id) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-file-earmark-text me-1"></i>{{ __('Lihat fail penyata') }}
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalPadam">
                        <i class="bi bi-trash me-1"></i>{{ __('Padam Penyata') }}
                    </button>
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
                                @php
                                    $belumGroup = $belum->groupBy(fn ($l) => $l->tarikh instanceof \DateTimeInterface ? $l->tarikh->format('Y-m-d') : (string) ($l->tarikh ?? ''));
                                @endphp
                                @forelse ($belumGroup as $hari => $rows)
                                    @php
                                        $tarikhLabel = $hari !== '' ? \Illuminate\Support\Carbon::parse($hari)->format('d M Y') : __('Tiada tarikh');
                                        $masukHari = $rows->sum(fn ($r) => (float) $r->kredit);
                                        $keluarHari = $rows->sum(fn ($r) => (float) $r->debit);
                                    @endphp
                                    {{-- Pemisah hari --}}
                                    <tr class="table-secondary">
                                        <td class="text-center"><input type="checkbox" class="form-check-input lump-hari" data-hari="{{ $hari }}" title="{{ __('Pilih semua hari ini') }}"></td>
                                        <td colspan="6" class="py-1 small fw-bold">
                                            <i class="bi bi-calendar3 me-1"></i>{{ $tarikhLabel }}
                                            <span class="text-muted fw-normal">· {{ $rows->count() }} {{ __('baris') }}</span>
                                            @if ($masukHari > 0)<span class="text-success ms-2">+{{ number_format($masukHari, 2) }}</span>@endif
                                            @if ($keluarHari > 0)<span class="text-danger ms-2">−{{ number_format($keluarHari, 2) }}</span>@endif
                                        </td>
                                    </tr>
                                    @foreach ($rows as $l)
                                        @php
                                            $masuk = (float) $l->kredit > 0;
                                            $cadCoa = $l->cadangan_coa_id ? ($masuk ? $coaHasil : $coaBelanja)->firstWhere('id', $l->cadangan_coa_id) : null;
                                            $tarikhStr = $l->tarikh instanceof \DateTimeInterface ? $l->tarikh->format('Y-m-d') : $l->tarikh;
                                        @endphp
                                        <tr class="baris-pilih" data-hari="{{ $hari }}" style="cursor:pointer">
                                            <td><input type="checkbox" class="form-check-input lump-chk"
                                                       data-line="{{ $l->id }}" data-side="{{ $masuk ? 'masuk' : 'keluar' }}"
                                                       data-amount="{{ $masuk ? $l->kredit : $l->debit }}" data-date="{{ $tarikhStr }}"
                                                       data-hari="{{ $hari }}" data-coa="{{ $l->cadangan_coa_id }}"></td>
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
                                                        data-tarikh="{{ $tarikhStr }}"
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
                                    @endforeach
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
                                @php
                                    $sudahGroup = $sudah->groupBy(fn ($l) => $l->tarikh instanceof \DateTimeInterface ? $l->tarikh->format('Y-m-d') : (string) ($l->tarikh ?? ''));
                                @endphp
                                @forelse ($sudahGroup as $hari => $rows)
                                    @php $tarikhLabel = $hari !== '' ? \Illuminate\Support\Carbon::parse($hari)->format('d M Y') : __('Tiada tarikh'); @endphp
                                    <tr class="table-secondary">
                                        <td colspan="5" class="py-1 small fw-bold">
                                            <i class="bi bi-calendar3 me-1"></i>{{ $tarikhLabel }}
                                            <span class="text-muted fw-normal">· {{ $rows->count() }} {{ __('baris') }}</span>
                                        </td>
                                    </tr>
                                    @foreach ($rows as $l)
                                        @php $masuk = (float) $l->kredit > 0; @endphp
                                        <tr>
                                            <td><small>{{ $l->tarikh instanceof \DateTimeInterface ? $l->tarikh->format('Y-m-d') : $l->tarikh }}</small></td>
                                            <td><small>{{ $l->deskripsi }}</small></td>
                                            <td class="text-end">{{ number_format($masuk ? (float) $l->kredit : (float) $l->debit, 2) }}</td>
                                            <td>
                                                @if ($l->voucher_ref && $l->matched_voucher_id)
                                                    <button type="button" class="btn btn-link btn-sm p-0 lihat-voucher text-decoration-none"
                                                            data-voucher="{{ $l->matched_voucher_id }}">
                                                        <small>{{ $l->voucher_ref }} <i class="bi bi-box-arrow-up-right small"></i></small>
                                                    </button>
                                                @else
                                                    <small>—</small>
                                                @endif
                                            </td>
                                            <td><span class="badge bg-{{ $l->status === 'MATCHED' ? 'success' : 'secondary' }}">{{ $l->status }}</span></td>
                                        </tr>
                                    @endforeach
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-3">{{ __('Tiada baris dipadan lagi.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Jumlah IKUT REKOD YANG DIEKSTRAK + semakan tally vs jumlah tercetak --}}
                @php
                    $adaTally = $batch->penyata_jum_kredit !== null || $batch->penyata_jum_debit !== null;
                    $tallyKredit = $adaTally && abs((float) $batch->penyata_jum_kredit - $jumMasuk) < 0.01;
                    $tallyDebit = $adaTally && abs((float) $batch->penyata_jum_debit - $jumKeluar) < 0.01;
                @endphp
                <div class="card shadow-sm mt-3 border-info">
                    <div class="card-body py-2">
                        <div class="small text-muted mb-1">
                            <i class="bi bi-calculator me-1"></i>{{ __('Jumlah mengikut transaksi yang diekstrak') }}
                        </div>
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <span class="text-success fw-bold"><i class="bi bi-arrow-down-circle me-1"></i>{{ __('Duit Masuk') }}: RM {{ number_format($jumMasuk, 2) }}</span>
                            <span class="text-danger fw-bold"><i class="bi bi-arrow-up-circle me-1"></i>{{ __('Duit Keluar') }}: RM {{ number_format($jumKeluar, 2) }}</span>
                            <span class="fw-bold"><i class="bi bi-scales me-1"></i>{{ __('Bersih') }}: RM {{ number_format($jumMasuk - $jumKeluar, 2) }}</span>
                        </div>

                        @if ($adaTally)
                            <hr class="my-2">
                            <div class="small fw-bold mb-1"><i class="bi bi-check2-square me-1"></i>{{ __('Semakan tally dengan jumlah TERCETAK dalam penyata') }}:</div>
                            <div class="d-flex justify-content-between flex-wrap gap-2 small">
                                <span>{{ __('Penyata: Masuk') }} RM {{ number_format((float) $batch->penyata_jum_kredit, 2) }}
                                    <span class="badge bg-{{ $tallyKredit ? 'success' : 'danger' }}">{{ $tallyKredit ? __('TALLY') : __('BEZA') }}</span></span>
                                <span>{{ __('Penyata: Keluar') }} RM {{ number_format((float) $batch->penyata_jum_debit, 2) }}
                                    <span class="badge bg-{{ $tallyDebit ? 'success' : 'danger' }}">{{ $tallyDebit ? __('TALLY') : __('BEZA') }}</span></span>
                            </div>
                            @if (!$tallyKredit || !$tallyDebit)
                                <div class="text-danger small mt-1"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Jumlah TIDAK sepadan — fail penyata mungkin tidak lengkap (contoh: hanya sebahagian muka), atau ada baris tidak dibaca. Sila semak fail penuh.') }}</div>
                            @else
                                <div class="text-success small mt-1"><i class="bi bi-check-circle me-1"></i>{{ __('Semua transaksi tepat & lengkap — jumlah diekstrak sepadan dengan penyata.') }}</div>
                            @endif
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Modal Padam Penyata (amaran keras) --}}
    <div class="modal fade" id="modalPadam" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ $batch ? route('semakpenyata.padam', $batch->id) : '#' }}" class="modal-content">
                @csrf
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-octagon me-1"></i>{{ __('Padam Penyata Ini?') }}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 small mb-2">
                        <strong>{{ __('AMARAN — tindakan ini KEKAL & TIDAK BOLEH DIBATALKAN.') }}</strong>
                    </div>
                    <ul class="small mb-2">
                        <li>{{ __('Fail penyata bank asal & semua baris yang BELUM direkod akan dipadam terus.') }}</li>
                        <li>{{ __('Untuk semak semula, anda perlu MUAT NAIK & SCAN sekali lagi (guna kuota).') }}</li>
                        <li>{{ __('Rekod kewangan yang SUDAH direkod (kutipan/bayaran) TIDAK dipadam — kekal dalam sistem.') }}</li>
                    </ul>
                    <div class="form-check small">
                        <input type="checkbox" class="form-check-input" id="padamFaham" required>
                        <label class="form-check-label" for="padamFaham">{{ __('Saya faham fail ini akan hilang kekal dan perlu discan semula.') }}</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash me-1"></i>{{ __('Padam Kekal') }}</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Panel gelangsar: butiran voucher (lihat rekod dalam sistem) --}}
    <div class="offcanvas offcanvas-end" tabindex="-1" id="ocVoucher" style="width:420px">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title"><i class="bi bi-receipt me-1"></i>{{ __('Rekod Dalam Sistem') }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body" id="ocVoucherBody">
            <div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm"></div></div>
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

            // Poll status semasa pemprosesan. Penyata IMBASAN 70+ muka boleh ambil
            // beberapa minit → JANGAN henti selagi progres (muka_siap) bergerak;
            // hanya menyerah jika TIADA kemajuan selama ~6 minit (job mungkin mati).
            const kad = document.getElementById('kad-proses');
            if (kad) {
                const url = kad.dataset.url;
                const kaedahLabel = { PARSE: @json(__('Baca terus (tepat)')), DIGITAL: @json(__('Digital AI (teks)')),
                    SCAN: @json(__('Imbasan (OCR)')), IMEJ: @json(__('Imej')), PDF: @json(__('PDF')) };
                let lastSiap = -1, stale = 0;
                const timer = setInterval(function () {
                    fetch(url, { headers: { 'Accept': 'application/json' } })
                        .then(r => r.json())
                        .then(d => {
                            if (d.status === 'SEDIA' || d.status === 'GAGAL' || d.status === 'DIBATAL') { clearInterval(timer); location.reload(); return; }
                            // Kemas kini bar progres muka.
                            if (d.muka_jumlah > 0) {
                                const pct = Math.min(100, Math.round(d.muka_siap / d.muka_jumlah * 100));
                                const wrap = document.getElementById('proses-bar-wrap');
                                const bar = document.getElementById('proses-bar');
                                const mukaTxt = document.getElementById('proses-muka');
                                if (wrap) wrap.classList.remove('d-none');
                                if (bar) bar.style.width = pct + '%';
                                if (mukaTxt) {
                                    mukaTxt.classList.remove('d-none');
                                    mukaTxt.textContent = (kaedahLabel[d.kaedah] ? kaedahLabel[d.kaedah] + ' — ' : '')
                                        + @json(__('Muka')) + ' ' + d.muka_siap + '/' + d.muka_jumlah + ' (' + pct + '%)';
                                }
                            }
                            // Jejak kemajuan; menyerah hanya jika mati (90 × 4s = 6 min tanpa gerak).
                            if (d.muka_siap > lastSiap) { lastSiap = d.muka_siap; stale = 0; } else { stale++; }
                            if (stale > 90) {
                                clearInterval(timer);
                                const badan = kad.querySelector('.card-body');
                                if (badan) badan.innerHTML = '<div class="text-warning py-4"><i class="bi bi-hourglass-split fs-3 d-block mb-2"></i>'
                                    + @json(__('Pemprosesan nampak terhenti. Sila muat semula halaman kemudian atau hubungi pentadbir sistem.')) + '</div>';
                            }
                        })
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
                // Serlahkan baris yang dipilih.
                chks.forEach(c => { const tr = c.closest('tr'); if (tr) tr.classList.toggle('table-active', c.checked); });
            }
            chks.forEach(c => c.addEventListener('change', kemasBar));
            if (lumpAll) lumpAll.addEventListener('change', function () { chks.forEach(c => { c.checked = lumpAll.checked; }); kemasBar(); });

            // Klik mana-mana pada baris (bukan butang/pautan/input) → toggle pilihan.
            document.querySelectorAll('tr.baris-pilih').forEach(function (row) {
                row.addEventListener('click', function (e) {
                    if (e.target.closest('button, a, input, form, label')) return;
                    const chk = row.querySelector('.lump-chk');
                    if (chk) { chk.checked = !chk.checked; kemasBar(); }
                });
            });

            // Checkbox pemisah hari → pilih/nyahpilih semua baris hari itu.
            document.querySelectorAll('.lump-hari').forEach(function (hchk) {
                hchk.addEventListener('change', function () {
                    chks.filter(c => c.dataset.hari === hchk.dataset.hari).forEach(c => { c.checked = hchk.checked; });
                    kemasBar();
                });
            });

            // ==== Panel gelangsar voucher: lihat rekod dalam sistem tanpa tinggal halaman ====
            const ocEl = document.getElementById('ocVoucher');
            const oc = ocEl ? new window.bootstrap.Offcanvas(ocEl) : null;
            const ocBody = document.getElementById('ocVoucherBody');
            const voucherBase = @json(url('/semak-penyata/voucher'));
            const T = { akaun: @json(__('Akaun')), debit: @json(__('Debit')), kredit: @json(__('Kredit')),
                jumlah: @json(__('Jumlah')), buka: @json(__('Buka Rekod Penuh')), gagal: @json(__('Gagal memuatkan rekod.')) };
            document.querySelectorAll('.lihat-voucher').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (!oc) return;
                    ocBody.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm"></div></div>';
                    oc.show();
                    fetch(voucherBase + '/' + btn.dataset.voucher, { headers: { 'Accept': 'application/json' } })
                        .then(r => r.ok ? r.json() : Promise.reject())
                        .then(d => {
                            const rows = (d.entries || []).map(e =>
                                '<tr><td><small>' + e.kod + '</small><br><small class="text-muted">' + (e.nama || '') + '</small></td>'
                                + '<td class="text-end text-success"><small>' + (e.debit || '') + '</small></td>'
                                + '<td class="text-end text-danger"><small>' + (e.kredit || '') + '</small></td></tr>').join('');
                            ocBody.innerHTML =
                                '<div class="mb-2"><span class="badge bg-primary">' + (d.ref || '') + '</span> '
                                + '<span class="badge bg-light text-dark border">' + (d.tarikh || '') + '</span></div>'
                                + (d.deskripsi ? '<div class="small mb-2">' + d.deskripsi + '</div>' : '')
                                + '<table class="table table-sm"><thead class="table-light"><tr>'
                                + '<th><small>' + T.akaun + '</small></th><th class="text-end"><small>' + T.debit + '</small></th>'
                                + '<th class="text-end"><small>' + T.kredit + '</small></th></tr></thead><tbody>' + rows + '</tbody>'
                                + '<tfoot><tr class="fw-bold"><td class="text-end"><small>' + T.jumlah + '</small></td>'
                                + '<td class="text-end" colspan="2"><small>RM ' + (d.jumlah || '') + '</small></td></tr></tfoot></table>'
                                + (d.pautan ? '<a href="' + d.pautan + '" class="btn btn-sm btn-primary w-100"><i class="bi bi-box-arrow-up-right me-1"></i>' + T.buka + '</a>' : '');
                        })
                        .catch(() => { ocBody.innerHTML = '<div class="text-danger small py-3">' + T.gagal + '</div>'; });
                });
            });

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
