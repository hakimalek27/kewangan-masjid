@extends('layouts.app')

@section('title', __('Laporan Buku Cek'))

@section('content')
<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" action="{{ route('cek.senarai') }}" class="row g-2 align-items-end">
            <div class="col-auto">
                <select name="y" class="form-select">
                    @for ($t = 2030; $t >= 2020; $t--)
                        <option value="{{ $t }}" @selected($tahun === $t)>{{ $t }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">{{ __('Cari') }}</button>
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>{{ __('Cetak') }}
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Laporan Buku Cek') }} — {{ __('Tahun') }} {{ $tahun }}</div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover table-bordered">
            <thead class="table-light">
                <tr>
                    <th style="width:50px">{{ __('No') }}</th>
                    <th>{{ __('Tarikh Keluar') }}</th>
                    <th>{{ __('Bank') }}</th>
                    <th>{{ __('Penerima') }}</th>
                    <th>{{ __('No. Siri Mula') }}</th>
                    <th>{{ __('No. Siri Akhir') }}</th>
                    <th>{{ __('Catatan') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($senarai as $c)
                    @php $bank = $namaBank->get($c->bank_account_id); @endphp
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td>{{ $c->tarikh_keluar?->format('d/m/Y') }}</td>
                        <td>{{ $bank ? $bank->nama_bank.' ('.$bank->no_akaun.')' : '—' }}</td>
                        <td>{{ $c->penerima }}</td>
                        <td>{{ $c->no_siri_mula }}</td>
                        <td>{{ $c->no_siri_akhir }}</td>
                        <td>{{ $c->catatan ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">{{ __('Tiada buku cek didaftarkan bagi tahun ini.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
