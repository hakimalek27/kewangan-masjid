@extends('layouts.app')

@section('title', __('Rekonsiliasi Bank'))

@section('content')
<div class="row no-print">
    <div class="col-lg-6">
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">{{ __('Pilih Bank') }}</div>
            <div class="card-body">
                <form method="GET" action="{{ route('rekonsiliasi.index') }}" class="row g-2 align-items-end">
                    <div class="col-8">
                        <select name="bank_account_id" class="form-select">
                            @foreach ($banks as $b)
                                <option value="{{ $b->id }}" @selected($bank && $bank->id === $b->id)>
                                    {{ __('Slot') }} {{ $b->slot }} : {{ $b->nama_bank }} ({{ $b->no_akaun }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto"><button type="submit" class="btn btn-primary">{{ __('Papar') }}</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">{{ __('Muat Naik Penyata Bank (CSV)') }}</div>
            <div class="card-body">
                <form method="POST" action="{{ route('rekonsiliasi.import') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                    @csrf
                    <input type="hidden" name="bank_account_id" value="{{ $bank?->id }}">
                    <div class="col-8">
                        <input type="file" name="fail" class="form-control" accept=".csv,.txt" required>
                        <div class="form-text">{{ __('Lajur: tarikh (Y-m-d atau d/m/Y), deskripsi, debit, kredit, baki — baris pertama header.') }}</div>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-success" @disabled(!$bank)>
                            <i class="bi bi-upload me-1"></i>{{ __('Import & Padan Auto') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@if ($bank && $laporan)
    <div class="row mb-3">
        <div class="col-md-3"><div class="card border-start border-4 border-primary shadow-sm"><div class="card-body py-2">
            <div class="small text-muted">{{ __('Baris Penyata') }} ({{ $laporan['dari'] }} → {{ $laporan['hingga'] }})</div>
            <div class="fs-5 fw-bold">{{ $laporan['bil'] }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card border-start border-4 border-success shadow-sm"><div class="card-body py-2">
            <div class="small text-muted">{{ __('Dipadan / Belum') }}</div>
            <div class="fs-5 fw-bold">{{ $laporan['bil_matched'] }} / {{ $laporan['bil_unmatched'] }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card border-start border-4 border-info shadow-sm"><div class="card-body py-2">
            <div class="small text-muted">{{ __('Bersih Penyata lwn Buku (RM)') }}</div>
            <div class="fs-6 fw-bold">{{ number_format((float) $laporan['bersih_penyata'], 2) }} {{ __('lwn') }} {{ number_format((float) $laporan['bersih_buku'], 2) }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card border-start border-4 {{ abs((float) $laporan['beza']) < 0.005 ? 'border-success' : 'border-danger' }} shadow-sm"><div class="card-body py-2">
            <div class="small text-muted">{{ __('Beza (RM)') }}</div>
            <div class="fs-5 fw-bold {{ abs((float) $laporan['beza']) < 0.005 ? 'text-success' : 'text-danger' }}">{{ number_format((float) $laporan['beza'], 2) }}</div>
        </div></div></div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header fw-bold">{{ __('Baris Penyata BELUM DIPADAN') }} <span class="badge text-bg-warning ms-1">{{ $unmatched->count() }}</span></div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-hover table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Tarikh') }}</th><th>{{ __('Deskripsi Penyata') }}</th>
                        <th class="text-end">{{ __('Keluar/Dr (RM)') }}</th><th class="text-end">{{ __('Masuk/Cr (RM)') }}</th>
                        <th class="no-print" style="width:230px">{{ __('Padanan Manual') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($unmatched as $u)
                        <tr @class(['table-info' => $lineCari === $u->id])>
                            <td>{{ $u->tarikh?->format('d/m/Y') }}</td>
                            <td class="small">{{ $u->deskripsi }}</td>
                            <td class="text-rm">{{ (float) $u->debit > 0 ? number_format((float) $u->debit, 2) : '' }}</td>
                            <td class="text-rm">{{ (float) $u->kredit > 0 ? number_format((float) $u->kredit, 2) : '' }}</td>
                            <td class="no-print">
                                <a href="{{ route('rekonsiliasi.index', ['bank_account_id' => $bank->id, 'line' => $u->id]) }}"
                                   class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i> {{ __('Cari Voucher') }}</a>
                                <form method="POST" action="{{ route('rekonsiliasi.abaikan', $u->id) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary" onclick="return confirm('{{ __('Abaikan baris penyata ini?') }}')">{{ __('Abai') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">{{ __('Tiada baris belum dipadan.') }} 🎉</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($lineCari)
        <div class="card shadow-sm mb-3 no-print">
            <div class="card-header fw-bold">{{ __('Padanan Manual — Baris Penyata') }} #{{ $lineCari }}</div>
            <div class="card-body">
                <form method="GET" action="{{ route('rekonsiliasi.index') }}" class="row g-2 mb-3">
                    <input type="hidden" name="bank_account_id" value="{{ $bank->id }}">
                    <input type="hidden" name="line" value="{{ $lineCari }}">
                    <div class="col-md-5">
                        <input type="text" name="cari" class="form-control" placeholder="{{ __('Cari ref voucher / deskripsi / amaun...') }}" value="{{ request('cari') }}">
                    </div>
                    <div class="col-auto"><button type="submit" class="btn btn-outline-primary">{{ __('Cari') }}</button></div>
                </form>
                <table class="table table-sm table-bordered align-middle">
                    <thead class="table-light">
                        <tr><th>{{ __('Voucher') }}</th><th>{{ __('Tarikh') }}</th><th>{{ __('Deskripsi') }}</th><th class="text-end">{{ __('Dr Bank') }}</th><th class="text-end">{{ __('Cr Bank') }}</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($calon as $c)
                            <tr>
                                <td>{{ $c->voucher_ref }}</td>
                                <td>{{ $c->tarikh }}</td>
                                <td class="small">{{ \Illuminate\Support\Str::limit($c->deskripsi, 50) }}</td>
                                <td class="text-rm">{{ (float) $c->debit > 0 ? number_format((float) $c->debit, 2) : '' }}</td>
                                <td class="text-rm">{{ (float) $c->kredit > 0 ? number_format((float) $c->kredit, 2) : '' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('rekonsiliasi.padan', $lineCari) }}">
                                        @csrf
                                        <input type="hidden" name="voucher_id" value="{{ $c->id }}">
                                        <button class="btn btn-sm btn-success"><i class="bi bi-link-45deg"></i> {{ __('Padan') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">{{ __('Tiada voucher calon — cuba carian lain.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header fw-bold">{{ __('Baris DIPADAN terkini') }} <span class="badge text-bg-success ms-1">{{ $matched->count() }}</span></div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-bordered align-middle">
                <thead class="table-light">
                    <tr><th>{{ __('Tarikh') }}</th><th>{{ __('Deskripsi') }}</th><th class="text-end">Dr</th><th class="text-end">Cr</th><th>{{ __('Voucher') }} #</th></tr>
                </thead>
                <tbody>
                    @forelse ($matched as $m)
                        <tr>
                            <td>{{ $m->tarikh?->format('d/m/Y') }}</td>
                            <td class="small">{{ $m->deskripsi }}</td>
                            <td class="text-rm">{{ (float) $m->debit > 0 ? number_format((float) $m->debit, 2) : '' }}</td>
                            <td class="text-rm">{{ (float) $m->kredit > 0 ? number_format((float) $m->kredit, 2) : '' }}</td>
                            <td><span class="badge text-bg-success">#{{ $m->matched_voucher_id }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">{{ __('Belum ada padanan.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
