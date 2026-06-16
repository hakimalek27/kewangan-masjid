@extends('layouts.app')

@section('title', __('Kotak Draf AI'))

@section('content')
<div class="alert alert-info small">
    <i class="bi bi-robot me-1"></i>
    {{ __('Draf di bawah dicipta oleh AI daripada resit yang dihantar ke group Telegram.') }}
    <strong>{{ __('Tiada transaksi direkodkan') }}</strong> {{ __('sehingga bendahari menyemak & mengesahkan setiap draf.') }}
</div>

@php $bolehTulis = auth()->user()?->bolehTulis(); @endphp

{{-- Fasa 9 UX — bulk sahkan draf dipilih --}}
<form method="POST" action="{{ route('draf.bulk') }}"
      onsubmit="return confirm('{{ __('Sahkan SEMUA draf yang dipilih? Setiap draf akan direkodkan sebagai transaksi sebenar.') }}')">
    @csrf
    <div class="card shadow-sm">
        <div class="card-header fw-bold d-flex align-items-center">
            <span>{{ __('Draf Menunggu Pengesahan') }}
                <span class="badge text-bg-danger ms-1">{{ $senarai->total() }}</span></span>
            @if ($bolehTulis && $senarai->isNotEmpty())
                <button type="submit" class="btn btn-sm btn-success ms-auto no-print">
                    <i class="bi bi-check2-all me-1"></i>{{ __('Sahkan Semua Dipilih') }}
                </button>
            @endif
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-hover table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        @if ($bolehTulis)
                            <th class="no-print" style="width:36px">
                                <input type="checkbox" class="form-check-input"
                                       onclick="document.querySelectorAll('input[name=\'draf_ids[]\']').forEach(c => c.checked = this.checked)">
                            </th>
                        @endif
                        <th style="width:70px">{{ __('Draf') }} #</th>
                        <th style="width:90px">{{ __('Jenis') }}</th>
                        <th style="width:110px">{{ __('Tarikh') }}</th>
                        <th class="text-end" style="width:120px">{{ __('Jumlah (RM)') }}</th>
                        <th>{{ __('Penerima / Pemberi') }}</th>
                        <th>{{ __('Deskripsi') }}</th>
                        <th style="width:110px">{{ __('Keyakinan AI') }}</th>
                        <th class="no-print" style="width:100px">{{ __('Tindakan') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($senarai as $d)
                        @php
                            $ex = $extractions->get($d->extraction_id);
                            $conf = (int) ($ex->confidence ?? 0);
                            $warna = $conf >= 80 ? 'success' : ($conf >= 50 ? 'warning' : 'danger');
                        @endphp
                        <tr>
                            @if ($bolehTulis)
                                <td class="no-print text-center">
                                    <input type="checkbox" class="form-check-input" name="draf_ids[]" value="{{ $d->id }}">
                                </td>
                            @endif
                            <td>#{{ $d->id }}</td>
                            <td>
                                <span class="badge text-bg-{{ $d->jenis === 'BAYARAN' ? 'secondary' : 'primary' }}">{{ $d->jenis }}</span>
                            </td>
                            <td>{{ $d->tarikh?->format('d/m/Y') }}</td>
                            <td class="text-end">{{ number_format((float) ($d->jumlah ?? 0), 2) }}</td>
                            <td>{{ $d->penerima ?: '—' }}</td>
                            <td class="small">{{ \Illuminate\Support\Str::limit($d->deskripsi, 60) ?: '—' }}</td>
                            <td>
                                <span class="badge text-bg-{{ $warna }}">{{ $conf }}%</span>
                            </td>
                            <td class="no-print">
                                <a href="{{ route('draf.lihat', $d->id) }}" class="btn btn-sm btn-primary">{{ __('Semak') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $bolehTulis ? 9 : 8 }}" class="text-center text-muted">{{ __('Tiada draf menunggu pengesahan.') }} 🎉</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $senarai->links() }}
        </div>
    </div>
</form>
@endsection
