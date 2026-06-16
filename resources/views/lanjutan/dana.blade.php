@extends('layouts.app')

@section('title', __('Dana & Tabung'))

@section('content')
{{-- Amaran defisit — kad merah --}}
@foreach ($defisit as $d)
    <div class="alert alert-danger d-flex align-items-center">
        <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
        <div>
            <strong>{{ __('AMARAN DEFISIT:') }}</strong> {{ __('Dana') }} <strong>{{ $d->nama ?: $d->coa_nama }} ({{ $d->kod }})</strong>
            {{ __('berbaki') }} <strong>RM {{ number_format((float) $d->baki, 2) }}</strong> — {{ __('tabung ini tidak dibenarkan defisit.') }}
            {{ __('Sila semak transaksi dana atau benarkan defisit jika disengajakan.') }}
        </div>
    </div>
@endforeach

<div class="row">
    <div class="col-lg-7">
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">{{ __('Senarai Dana / Tabung (COA 300-04xxx)') }}</div>
            <div class="card-body table-responsive">
                <table class="table table-sm table-hover table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Kod') }}</th>
                            <th>{{ __('Nama Dana') }}</th>
                            <th class="text-end">{{ __('Baki (RM)') }}</th>
                            <th>{{ __('Defisit?') }}</th>
                            <th class="no-print">{{ __('Tindakan') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($senarai as $f)
                            <tr @class(['table-danger' => $f->defisit])>
                                <td>{{ $f->kod }}</td>
                                <td>{{ $f->nama ?: $f->coa_nama }}</td>
                                <td class="text-rm {{ (float) $f->baki < 0 ? 'text-danger fw-bold' : '' }}">{{ number_format((float) $f->baki, 2) }}</td>
                                <td>
                                    @if ($f->defisit)
                                        <span class="badge text-bg-danger">{{ __('DEFISIT') }}</span>
                                    @elseif ($f->allow_deficit)
                                        <span class="badge text-bg-secondary">{{ __('Dibenarkan') }}</span>
                                    @else
                                        <span class="badge text-bg-success">OK</span>
                                    @endif
                                </td>
                                <td class="no-print">
                                    <a href="{{ route('dana.index', ['coa_id' => $f->coa_id]) }}" class="btn btn-sm btn-outline-primary">{{ __('Transaksi') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">{{ __('Tiada dana didaftarkan.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        @if (auth()->user()?->bolehTulis())
            <div class="card shadow-sm mb-3 no-print">
                <div class="card-header fw-bold">{{ __('Tambah / Kemaskini Dana') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('dana.simpan') }}">
                        @csrf
                        @if ($coaTabung->isNotEmpty())
                            <div class="mb-3">
                                <label class="form-label" for="coa_id">{{ __('Akaun Tabung Baharu (COA 300-04xxx)') }}</label>
                                <select name="coa_id" id="coa_id" class="form-select">
                                    <option value="">{{ __('-- Pilih Akaun --') }}</option>
                                    @foreach ($coaTabung as $coa)
                                        <option value="{{ $coa->id }}">{{ $coa->kod }} {{ $coa->nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <p class="small text-muted mb-2">{{ __('Semua akaun tabung 300-04xxx telah didaftarkan.') }}
                                {{ __('Pilih dana sedia ada untuk kemaskini:') }}</p>
                            <div class="mb-3">
                                <select name="id" class="form-select form-select-sm">
                                    @foreach ($senarai as $f)
                                        <option value="{{ $f->id }}">{{ $f->kod }} — {{ $f->nama ?: $f->coa_nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="mb-3">
                            <label class="form-label" for="nama">{{ __('Nama Dana') }} <span class="text-danger">*</span></label>
                            <input type="text" name="nama" id="nama" class="form-control" required maxlength="150" value="{{ old('nama') }}">
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="allow_deficit" id="allow_deficit" value="1">
                            <label class="form-check-label" for="allow_deficit">{{ __('Benarkan baki defisit (tiada amaran merah)') }}</label>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Simpan') }}</button>
                    </form>
                </div>
            </div>
        @endif

        @if ($pilihan)
            <div class="card shadow-sm">
                <div class="card-header fw-bold">
                    {{ __('Transaksi Dana:') }} {{ $pilihan->kod }} {{ $pilihan->nama ?: $pilihan->coa_nama }}
                    <span class="badge text-bg-secondary ms-1">{{ __('50 terkini') }}</span>
                </div>
                <div class="card-body table-responsive" style="max-height:420px; overflow-y:auto">
                    <table class="table table-sm table-bordered align-middle">
                        <thead class="table-light">
                            <tr><th>{{ __('Tarikh') }}</th><th>{{ __('Ref') }}</th><th>{{ __('Deskripsi') }}</th><th class="text-end">Dr</th><th class="text-end">Cr</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($transaksi as $t)
                                <tr>
                                    <td class="small">{{ $t->tarikh }}</td>
                                    <td class="small">{{ $t->voucher_ref }}</td>
                                    <td class="small">{{ \Illuminate\Support\Str::limit($t->deskripsi, 45) }}</td>
                                    <td class="text-rm small">{{ (float) $t->debit > 0 ? number_format((float) $t->debit, 2) : '' }}</td>
                                    <td class="text-rm small">{{ (float) $t->kredit > 0 ? number_format((float) $t->kredit, 2) : '' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">{{ __('Tiada transaksi.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
