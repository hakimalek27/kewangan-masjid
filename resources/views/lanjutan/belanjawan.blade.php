@extends('layouts.app')

@section('title', __('Belanjawan'))

@section('content')
<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" action="{{ route('belanjawan.index') }}" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label" for="tahun">{{ __('Tahun Belanjawan') }}</label>
                <select name="tahun" id="tahun" class="form-select">
                    @for ($t = now()->year + 1; $t >= now()->year - 3; $t--)
                        <option value="{{ $t }}" @selected($tahun === $t)>{{ $t }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">{{ __('Papar') }}</button>
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>{{ __('Cetak') }}
                </button>
            </div>
        </form>
    </div>
</div>

<form method="POST" action="{{ route('belanjawan.simpan') }}">
    @csrf
    <input type="hidden" name="tahun" value="{{ $tahun }}">

    <div class="card shadow-sm">
        <div class="card-header fw-bold">
            {{ __('Belanjawan') }} {{ $tahun }} — {{ __('Peruntukan lwn Perbelanjaan Sebenar') }}
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-hover table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Kod') }}</th>
                        <th>{{ __('Akaun Belanja') }}</th>
                        <th class="text-end" style="width:160px">{{ __('Peruntukan (RM)') }}</th>
                        <th class="text-end" style="width:140px">{{ __('Sebenar (RM)') }}</th>
                        <th class="text-end" style="width:140px">{{ __('Baki (RM)') }}</th>
                        <th style="width:220px">{{ __('Penggunaan') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($varians as $v)
                        <tr @class(['table-danger' => $v->melebihi])>
                            <td>{{ $v->kod }}</td>
                            <td>{{ $v->nama }}</td>
                            <td>
                                <input type="number" step="0.01" min="0" name="peruntukan[{{ $v->coa_id }}]"
                                       class="form-control form-control-sm text-end"
                                       value="{{ old('peruntukan.'.$v->coa_id, (float) $v->peruntukan > 0 ? $v->peruntukan : '') }}"
                                       placeholder="0.00">
                            </td>
                            <td class="text-rm">{{ number_format((float) $v->sebenar, 2) }}</td>
                            <td class="text-rm {{ (float) $v->baki < 0 && (float) $v->peruntukan > 0 ? 'text-danger fw-bold' : '' }}">
                                {{ (float) $v->peruntukan > 0 ? number_format((float) $v->baki, 2) : '—' }}
                            </td>
                            <td>
                                @if ($v->pct !== null)
                                    <div class="progress" style="height:18px" title="{{ $v->pct }}%">
                                        <div class="progress-bar {{ $v->melebihi ? 'bg-danger' : ($v->pct >= 80 ? 'bg-warning' : 'bg-success') }}"
                                             style="width: {{ min($v->pct, 100) }}%">{{ $v->pct }}%</div>
                                    </div>
                                    @if ($v->melebihi)
                                        <span class="badge text-bg-danger mt-1">{{ __('MELEBIHI PERUNTUKAN') }}</span>
                                    @endif
                                @else
                                    <span class="text-muted small">{{ __('Tiada peruntukan') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if (auth()->user()?->bolehTulis())
            <div class="card-footer no-print">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Simpan') }} {{ __('Peruntukan') }} {{ $tahun }}</button>
            </div>
        @endif
    </div>
</form>
@endsection
