@extends('layouts.app')

@section('title', __('Penutupan Tahun Kewangan'))

@section('content')
<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" action="{{ route('tutuptahun.index') }}" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label" for="tahun">{{ __('Tahun Kewangan') }}</label>
                <select name="tahun" id="tahun" class="form-select">
                    @for ($t = now()->year; $t >= now()->year - 5; $t--)
                        <option value="{{ $t }}" @selected($tahun === $t)>{{ $t }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-auto"><button type="submit" class="btn btn-primary">{{ __('Pratonton') }}</button></div>
            @if ($pratonton['locked_until'])
                <div class="col-auto ms-auto">
                    <span class="badge text-bg-secondary"><i class="bi bi-lock-fill me-1"></i>{{ __('Tempoh dikunci sehingga') }} {{ $pratonton['locked_until'] }}</span>
                </div>
            @endif
        </form>
    </div>
</div>

@if ($pratonton['sudah_tutup'])
    <div class="alert alert-success">
        <i class="bi bi-check-circle-fill me-1"></i>
        {{ __('Tahun') }} <strong>{{ $tahun }}</strong> {{ __('telah pun ditutup (voucher') }} <strong>YE-{{ $tahun }}</strong> {{ __('wujud).') }}
        {{ __('Penutupan kedua tidak dibenarkan.') }}
    </div>
@endif

@if ($pratonton['ada_suspense'])
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-octagon-fill me-1"></i>
        <strong>{{ __('AMARAN KUAT:') }}</strong> {{ __('Akaun Sementara') }} <strong>300-99990</strong> {{ __('masih berbaki') }}
        <strong>RM {{ number_format((float) $pratonton['suspense'], 2) }}</strong> {{ __('pada') }} {{ $tahun }}-12.
        <u>{{ __('Jelaskan (reclass) suspense dahulu') }}</u> {{ __('sebelum menutup tahun — atau tandakan kotak pengesahan') }}
        {{ __('di bawah untuk meneruskan juga (tidak digalakkan).') }}
    </div>
@endif

<div class="row">
    <div class="col-lg-7">
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">{{ __('Pratonton Untung Rugi') }} {{ $tahun }} {{ __('(akan dipindah ke 100-10000 DANA TERKUMPUL)') }}</div>
            <div class="card-body table-responsive" style="max-height:480px; overflow-y:auto">
                <table class="table table-sm table-bordered align-middle">
                    <thead class="table-light"><tr><th>{{ __('Kod') }}</th><th>{{ __('Akaun') }}</th><th class="text-end">{{ __('Amaun (RM)') }}</th></tr></thead>
                    <tbody>
                        <tr class="table-light"><td colspan="3" class="fw-bold">{{ __('HASIL') }}</td></tr>
                        @foreach ($pratonton['pl']['hasil'] as $h)
                            <tr><td>{{ $h->kod }}</td><td class="small">{{ $h->nama }}</td><td class="text-rm">{{ number_format((float) $h->amaun, 2) }}</td></tr>
                        @endforeach
                        <tr class="fw-bold"><td colspan="2">{{ __('JUMLAH HASIL') }}</td><td class="text-rm">{{ number_format((float) $pratonton['pl']['jumlah_hasil'], 2) }}</td></tr>
                        <tr class="table-light"><td colspan="3" class="fw-bold">{{ __('BELANJA') }}</td></tr>
                        @foreach ($pratonton['pl']['belanja'] as $b)
                            <tr><td>{{ $b->kod }}</td><td class="small">{{ $b->nama }}</td><td class="text-rm">{{ number_format((float) $b->amaun, 2) }}</td></tr>
                        @endforeach
                        <tr class="fw-bold"><td colspan="2">{{ __('JUMLAH BELANJA') }}</td><td class="text-rm">{{ number_format((float) $pratonton['pl']['jumlah_belanja'], 2) }}</td></tr>
                        <tr class="fw-bold {{ (float) $pratonton['pl']['lebihan'] >= 0 ? 'table-success' : 'table-danger' }}">
                            <td colspan="2">{{ __('LEBIHAN / (KURANGAN)') }} {{ $tahun }}</td>
                            <td class="text-rm">{{ number_format((float) $pratonton['pl']['lebihan'], 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm no-print">
            <div class="card-header fw-bold">{{ __('Tutup Tahun') }} {{ $tahun }}</div>
            <div class="card-body">
                <ul class="small">
                    <li>{{ __('Voucher penutupan') }} <strong>YE-{{ $tahun }}</strong> {{ __('diposkan pada tempoh maya') }}
                        <strong>{{ $tahun }}-13</strong> — {{ __('laporan P&L bulanan/tahunan sejarah') }} <u>{{ __('tidak berubah') }}</u>.</li>
                    <li>{{ __('Setiap akaun Hasil/Belanja disifarkan; baki bersih dipindah ke') }} <strong>100-10000 DANA TERKUMPUL</strong>.</li>
                    <li>{{ __('Selepas tutup, semua tempoh sehingga') }} <strong>{{ $tahun }}-12</strong> {{ __('DIKUNCI —') }}
                        {{ __('tiada transaksi/pembatalan baharu dibenarkan.') }}</li>
                    <li>{{ __('Akaun Sementara 300-99990: baki') }}
                        <strong>RM {{ number_format((float) $pratonton['suspense'], 2) }}</strong>.</li>
                </ul>

                <form method="POST" action="{{ route('tutuptahun.tutup') }}">
                    @csrf
                    <input type="hidden" name="tahun" value="{{ $tahun }}">

                    @if ($pratonton['ada_suspense'])
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="sahkan_suspense" id="sahkan_suspense" value="1">
                            <label class="form-check-label small" for="sahkan_suspense">
                                {{ __('Saya faham Akaun Sementara masih berbaki dan') }} <strong>{{ __('mengesahkan untuk meneruskan') }}</strong> {{ __('penutupan tahun.') }}
                            </label>
                        </div>
                    @endif

                    <button type="submit" class="btn btn-danger" @disabled($pratonton['sudah_tutup'])
                            onclick="return confirm('{{ __('TUTUP TAHUN') }} {{ $tahun }}? {{ __('Tindakan ini mengunci semua tempoh sehingga') }} {{ $tahun }}-12 {{ __('dan tidak boleh diundur dari halaman ini.') }}')">
                        <i class="bi bi-lock-fill me-1"></i>{{ __('Tutup Tahun') }} {{ $tahun }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
