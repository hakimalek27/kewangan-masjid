@extends('layouts.app')

@section('title', $tajuk)

@section('content')
@php
    $bolehTulis = in_array(auth()->user()?->role?->value, ['admin', 'bendahari'], true);
@endphp

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold">{{ $tajuk }}</span>
        <span class="no-print">
            <a href="{{ $opening ? route('fd.daftarlama') : route('fd.baru') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg me-1"></i>{{ $opening ? __('Daftar Pelaburan Lama') : __('Daftar Pelaburan Baru') }}
            </a>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>{{ __('Cetak') }}
            </button>
        </span>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Tarikh') }}</th>
                    <th>{{ __('Institusi') }}</th>
                    <th>{{ __('No. Sijil') }}</th>
                    <th>{{ __('Akaun FD') }}</th>
                    <th>{{ __('Akaun Bank') }}</th>
                    <th class="text-end">{{ __('Jumlah (RM)') }}</th>
                    <th class="text-end">{{ __('Kadar (%)') }}</th>
                    <th class="text-end">{{ __('Tempoh (Bln)') }}</th>
                    <th>{{ __('Matang') }}</th>
                    <th class="text-end">{{ __('Baki Hari') }}</th>
                    <th>{{ __('Status') }}</th>
                    @if (!$opening)<th class="no-print" style="width:260px">{{ __('Tindakan') }}</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($senarai as $fd)
                    @php
                        $coaFd = $namaCoa->get($fd->coa_fd_id);
                        $coaBank = $namaCoa->get($fd->coa_bank_id);
                    @endphp
                    <tr>
                        <td>{{ $fd->tarikh?->format('d/m/Y') }}</td>
                        <td>{{ $fd->institusi }}</td>
                        <td>{{ $fd->no_sijil ?: '—' }}</td>
                        <td>{{ $coaFd?->kod ?: '—' }}</td>
                        <td>{{ $coaBank?->kod ?: '—' }}</td>
                        <td class="text-end">{{ number_format((float) $fd->jumlah, 2) }}</td>
                        <td class="text-end">{{ $fd->kadar_pct !== null ? number_format((float) $fd->kadar_pct, 2) : '—' }}</td>
                        <td class="text-end">{{ $fd->tempoh_bulan ?: '—' }}</td>
                        <td>{{ $fd->maturity_date?->format('d/m/Y') ?: '—' }}</td>
                        <td class="text-end">
                            @php $bakiHari = ($fd->maturity_date && $fd->status === 'AKTIF') ? (int) round(now()->startOfDay()->diffInDays($fd->maturity_date, false)) : null; @endphp
                            @if ($bakiHari === null)—
                            @elseif ($bakiHari < 0)<span class="text-danger">{{ __('Tamat') }}</span>
                            @else <span class="{{ $bakiHari <= 30 ? 'text-warning fw-bold' : '' }}">{{ $bakiHari }}</span>
                            @endif
                        </td>
                        <td><span class="badge {{ $fd->status === 'AKTIF' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $fd->status }}</span></td>
                        @if (!$opening)
                            <td class="no-print">
                                <a href="{{ route('kutipan.dividen', ['fd_id' => $fd->id]) }}" class="btn btn-sm btn-outline-success">{{ __('Dividen') }}</a>
                                @if ($bolehTulis && $fd->status === 'AKTIF')
                                    <form method="POST" action="{{ route('fd.matang', $fd) }}" class="d-inline"
                                          onsubmit="return confirm('{{ __('Tandakan FD ini sebagai MATANG? Wang akan dijurnal kembali ke bank.') }}')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('Matang') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('fd.renew', $fd) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Renew') }}</button>
                                    </form>
                                @endif
                                @if ($bolehTulis)
                                    <a href="{{ route('fd.edit', $fd) }}" class="btn btn-sm btn-outline-secondary">{{ __('Edit') }}</a>
                                    <form method="POST" action="{{ route('fd.padam', $fd) }}" class="d-inline"
                                          onsubmit="return confirm('{{ __('Padam pelaburan ini? Jurnal berkaitan akan turut dibatalkan.') }}')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Padam') }}</button>
                                    </form>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $opening ? 11 : 12 }}" class="text-center text-muted">{{ __('Tiada pelaburan direkodkan.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
