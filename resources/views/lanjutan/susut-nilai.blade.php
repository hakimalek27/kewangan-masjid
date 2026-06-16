@extends('layouts.app')

@section('title', __('Susut Nilai Aset'))

@section('content')
@if (auth()->user()?->bolehTulis())
    <div class="card shadow-sm mb-3 no-print">
        <div class="card-body d-flex flex-wrap gap-2 align-items-end">
            <form method="POST" action="{{ route('susutnilai.jana') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-auto">
                    <label class="form-label" for="bulan">{{ __('Bulan') }}</label>
                    <select name="bulan" id="bulan" class="form-select">
                        @for ($b = 1; $b <= 12; $b++)
                            <option value="{{ $b }}" @selected($b === (int) now()->month)>{{ sprintf('%02d', $b) }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label" for="tahun">{{ __('Tahun') }}</label>
                    <select name="tahun" id="tahun" class="form-select">
                        @for ($t = now()->year; $t >= now()->year - 3; $t--)
                            <option value="{{ $t }}">{{ $t }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary"
                            onclick="return confirm('{{ __('Jana & pos jurnal susut nilai bulan ini untuk semua aset aktif? Aset yang sudah diposkan bulan ini akan dilangkau (idempoten).') }}')">
                        <i class="bi bi-calculator me-1"></i>{{ __('Jana & Pos Bulan Ini') }}
                    </button>
                </div>
            </form>
            <div class="small text-muted ms-auto" style="max-width:420px">
                {{ __('Garis lurus:') }} <code>kos × kadar% / 12</code> {{ __('sebulan (atau') }} <code>kos / usia guna / 12</code> {{ __('jika kadar tiada).') }}
                {{ __('Jurnal: Dr 650-10000 / Cr akaun SNT aset. Berhenti automatik apabila susut terkumpul = kos.') }}
            </div>
        </div>
    </div>
@endif

<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Senarai Aset & Jadual Susut Nilai') }}</div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Kod / Nama Aset') }}</th>
                    <th>{{ __('Akaun / SNT') }}</th>
                    <th class="text-end">{{ __('Kos (RM)') }}</th>
                    <th class="text-end">{{ __('Kadar') }}</th>
                    <th class="text-end">{{ __('Susut/Bulan (RM)') }}</th>
                    <th class="text-end">{{ __('SNT Terkumpul (RM)') }}</th>
                    <th class="text-end">{{ __('Nilai Buku (RM)') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="no-print" style="width:120px">{{ __('Tindakan') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($aset as $a)
                    @php
                        $coa = $namaCoa->get($a->coa_id);
                        $snt = $namaCoa->get($a->snt_coa_id);
                        $nilaiBuku = (float) $a->kos - (float) $a->accumulated_depn;
                    @endphp
                    <tr>
                        <td><strong>{{ $a->kod_aset }}</strong><br><span class="small">{{ $a->nama }}</span></td>
                        <td class="small">{{ $coa?->kod }}<br>SNT: {{ $snt?->kod ?? '—' }}</td>
                        <td class="text-rm">{{ number_format((float) $a->kos, 2) }}</td>
                        <td class="text-end small">
                            @if ((float) $a->depn_rate_pct > 0) {{ number_format((float) $a->depn_rate_pct, 2) }}%
                            @elseif ((int) $a->useful_life_years > 0) {{ $a->useful_life_years }} {{ __('thn') }}
                            @else — @endif
                        </td>
                        <td class="text-rm">{{ $bulanan[$a->id] > 0 ? number_format($bulanan[$a->id], 2) : '—' }}</td>
                        <td class="text-rm">{{ number_format((float) $a->accumulated_depn, 2) }}</td>
                        <td class="text-rm">{{ number_format($nilaiBuku, 2) }}</td>
                        <td>
                            <span class="badge {{ $a->status === 'AKTIF' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $a->status }}</span>
                            @if ($a->status === 'AKTIF' && (float) $a->accumulated_depn >= (float) $a->kos && (float) $a->kos > 0)
                                <span class="badge text-bg-info">{{ __('Susut penuh') }}</span>
                            @endif
                        </td>
                        <td class="no-print">
                            @if ($a->status === 'AKTIF' && auth()->user()?->bolehTulis())
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal"
                                        data-bs-target="#lupusModal-{{ $a->id }}">
                                    <i class="bi bi-trash3 me-1"></i>{{ __('Lupus') }}
                                </button>
                            @endif
                        </td>
                    </tr>
                    @if ($jadual->has($a->id))
                        <tr class="table-light">
                            <td colspan="9" class="small py-1">
                                <i class="bi bi-clock-history me-1"></i><strong>{{ __('Jadual susut nilai:') }}</strong>
                                @foreach ($jadual[$a->id]->take(12) as $j)
                                    <span class="badge {{ $j->posted ? 'text-bg-success' : 'text-bg-warning' }} me-1">
                                        {{ sprintf('%04d-%02d', $j->tahun, $j->bulan) }}: RM{{ number_format((float) $j->amaun, 2) }}
                                    </span>
                                @endforeach
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="9" class="text-center text-muted">{{ __('Tiada aset didaftarkan.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Modal pelupusan per aset --}}
@foreach ($aset->where('status', 'AKTIF') as $a)
    <div class="modal fade" id="lupusModal-{{ $a->id }}" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('susutnilai.lupus', $a->id) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Pelupusan Aset:') }} {{ $a->kod_aset }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small mb-2">
                        <strong>{{ $a->nama }}</strong> — {{ __('kos') }} RM{{ number_format((float) $a->kos, 2) }},
                        {{ __('SNT terkumpul') }} RM{{ number_format((float) $a->accumulated_depn, 2) }},
                        {{ __('nilai buku') }} RM{{ number_format((float) $a->kos - (float) $a->accumulated_depn, 2) }}.
                    </p>
                    <div class="alert alert-warning small py-2">
                        {{ __('Jurnal pelupusan: Dr SNT (susut terkumpul) + Dr 600-99990 (baki nilai buku) /') }}
                        {{ __('Cr akaun aset (kos penuh). Status aset menjadi DILUPUSKAN.') }}
                    </div>
                    <label class="form-label" for="sebab-{{ $a->id }}">{{ __('Sebab Pelupusan') }} <span class="text-danger">*</span></label>
                    <textarea name="sebab" id="sebab-{{ $a->id }}" class="form-control" rows="2" required maxlength="300"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-danger">{{ __('Sahkan Pelupusan') }}</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
@endsection
