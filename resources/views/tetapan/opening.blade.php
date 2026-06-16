@extends('layouts.app')

@section('title', __('Set Baki Awal'))

@section('content')
@php
    $bolehTulis = in_array(auth()->user()?->role?->value, ['admin', 'bendahari'], true);
    $rowsAwal = $baris->map(fn ($b) => [
        'coa_id' => (string) $b->coa_id,
        'amaun'  => (string) $b->amaun,
        'side'   => $b->side,
    ])->values();
@endphp

<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" action="{{ route('bank.opening') }}" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-0" for="tahun">{{ __('Tahun') }}</label>
                <select name="tahun" id="tahun" class="form-select">
                    @for ($t = now()->year + 1; $t >= 2020; $t--)
                        <option value="{{ $t }}" @selected($tahun === $t)>{{ $t }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">{{ __('Papar') }}</button>
            </div>
        </form>
    </div>
</div>

@if ($isLocked)
    <div class="card shadow-sm">
        <div class="card-header fw-bold">{{ __('Baki Awal') }} {{ $tahun }}</div>
        <div class="card-body">
            <div class="alert alert-warning mb-3">
                <i class="bi bi-lock-fill me-1"></i>
                <strong>{{ __('Sistem Terkunci') }}</strong> — {{ __('baki awal tahun') }} {{ $tahun }} {{ __('telah dimuktamadkan.') }}
                {{ __('Jurnal') }} <strong>OB-{{ $tahun }}</strong> {{ __('(Dr Bank/Aset, Cr 100-10000 Dana Terkumpul) telah dijana.') }}
            </div>

            <table class="table table-sm table-bordered align-middle" style="max-width:700px">
                <thead class="table-light">
                    <tr><th>{{ __('Kod Akaun') }}</th><th class="text-end">{{ __('Amaun (RM)') }}</th><th>{{ __('Sisi') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($baris as $b)
                        <tr>
                            <td>{{ $b->coa?->kod }} {{ $b->coa?->nama }}</td>
                            <td class="text-end">{{ number_format((float) $b->amaun, 2) }}</td>
                            <td>{{ $b->side === 'D' ? __('Debit') : __('Kredit') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($bolehTulis)
                <form method="POST" action="{{ route('bank.opening.reset') }}"
                      onsubmit="return confirm('{{ __('Reset & Edit Semula?') }}\n\n{{ __('Jurnal') }} OB-{{ $tahun }} {{ __('akan DIBATALKAN dan kunci dibuka. Anda perlu simpan dan muktamadkan semula.') }}')">
                    @csrf
                    <input type="hidden" name="tahun" value="{{ $tahun }}">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-unlock me-1"></i>{{ __('Reset & Edit Semula') }}
                    </button>
                </form>
            @endif
        </div>
    </div>
@else
    <div class="card shadow-sm">
        <div class="card-header fw-bold">{{ __('Baki Awal') }} {{ $tahun }} — {{ __('Belum Dimuktamadkan') }}</div>
        <div class="card-body">
            @if (!$bolehTulis)
                <p class="text-muted mb-0">{{ __('Anda mempunyai akses baca sahaja.') }}</p>
            @else
                <form method="POST" action="{{ route('bank.opening.simpan') }}"
                      x-data='{ rows: {{ $rowsAwal->isEmpty() ? json_encode([['coa_id' => '', 'amaun' => '', 'side' => 'D']]) : json_encode($rowsAwal) }} }'>
                    @csrf
                    <input type="hidden" name="tahun" value="{{ $tahun }}">

                    <table class="table table-sm table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Kod Akaun (200 Aset Tetap / 250 Aset Semasa / 300 Liabiliti)') }}</th>
                                <th style="width:180px" class="text-end">{{ __('Amaun (RM)') }}</th>
                                <th style="width:130px">{{ __('Sisi') }}</th>
                                <th style="width:60px" class="text-center">—</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, i) in rows" :key="i">
                                <tr>
                                    <td>
                                        <select :name="`rows[${i}][coa_id]`" x-model="row.coa_id" class="form-select form-select-sm" required>
                                            <option value="">{{ __('-- Pilih Akaun --') }}</option>
                                            @foreach ($coaSenarai as $c)
                                                <option value="{{ $c->id }}">{{ $c->kod }} {{ $c->nama }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0.01" :name="`rows[${i}][amaun]`"
                                               x-model="row.amaun" class="form-control form-control-sm text-end" required placeholder="0.00">
                                    </td>
                                    <td>
                                        <select :name="`rows[${i}][side]`" x-model="row.side" class="form-select form-select-sm" required>
                                            <option value="D">{{ __('Debit') }}</option>
                                            <option value="C">{{ __('Kredit') }}</option>
                                        </select>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                @click="rows.splice(i, 1)" :disabled="rows.length === 1">&times;</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <button type="button" class="btn btn-outline-secondary btn-sm mb-3"
                            @click="rows.push({ coa_id: '', amaun: '', side: 'D' })">
                        <i class="bi bi-plus-lg me-1"></i>{{ __('Tambah Baris') }}
                    </button>

                    <div class="small text-muted mb-3">
                        {{ __('Baris pengimbang ke') }} <strong>100-10000 Dana Terkumpul</strong> {{ __('dijana secara automatik') }}
                        {{ __('(jurnal') }} <strong>OB-{{ $tahun }}</strong>).
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Simpan') }}</button>
                </form>

                @if ($baris->isNotEmpty())
                    <form method="POST" action="{{ route('bank.opening.kunci') }}" class="mt-2"
                          onsubmit="return confirm('{{ __('Muktamad & Kunci baki awal') }} {{ $tahun }}?\n\n{{ __('Selepas dikunci, pengeditan memerlukan Reset & Edit Semula.') }}')">
                        @csrf
                        <input type="hidden" name="tahun" value="{{ $tahun }}">
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-lock me-1"></i>{{ __('Muktamad & Kunci') }}
                        </button>
                    </form>
                @endif
            @endif
        </div>
    </div>
@endif
@endsection
