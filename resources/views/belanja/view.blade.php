@extends('layouts.app')

@section('title', __('Baucer').' '.$pembayaran->baucer_no)

@section('content')
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold">{{ __('BAUCER BAYARAN — No.') }} {{ $pembayaran->baucer_no }} ({{ $pembayaran->jenis }})</span>
        <span class="badge {{ $pembayaran->status === 'ACTIVE' ? 'text-bg-success' : 'text-bg-danger' }}">{{ $pembayaran->status }}</span>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr><th class="w-50">{{ __('Tarikh Mohon') }}</th><td>{{ $pembayaran->tar_mohon?->format('d/m/Y') }}</td></tr>
                    <tr><th>{{ __('Tarikh Lulus') }}</th><td>{{ $pembayaran->tar_lulus?->format('d/m/Y') }}</td></tr>
                    <tr><th>{{ __('No. Invois') }}</th><td>{{ $pembayaran->no_baucer ?: '—' }}</td></tr>
                    <tr><th>{{ __('Kategori (COA)') }}</th><td>{{ $pembayaran->coa?->kod }} {{ $pembayaran->coa?->nama }}</td></tr>
                    <tr><th>{{ __('Jumlah (RM)') }}</th><td class="fw-bold">{{ number_format((float) $pembayaran->jumlah, 2) }}</td></tr>
                    <tr><th>{{ __('Cara Pembayaran') }}</th><td>{{ $pembayaran->cara_bayar }}</td></tr>
                    <tr><th>{{ __('Bank') }}</th><td>{{ $pembayaran->bank ? $pembayaran->bank->nama_bank.' ('.$pembayaran->bank->no_akaun.')' : '—' }}</td></tr>
                    <tr><th>{{ __('Akaun PWR') }}</th><td>{{ $pembayaran->pwrCoa ? $pembayaran->pwrCoa->kod.' '.$pembayaran->pwrCoa->nama : '—' }}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr><th class="w-50">{{ __('Pemohon / Penerima') }}</th><td>{{ $pembayaran->pemohon ?: '—' }}</td></tr>
                    <tr><th>{{ __('No. KP') }}</th><td>{{ $pembayaran->nokp ?: '—' }}</td></tr>
                    <tr><th>{{ __('No. Telefon') }}</th><td>{{ $pembayaran->contactno ?: '—' }}</td></tr>
                    <tr><th>{{ __('Alamat') }}</th><td>{{ $pembayaran->alamat ?: '—' }}</td></tr>
                    <tr><th>{{ __('No. Cek') }}</th><td>{{ $pembayaran->no_cek ?: '—' }}</td></tr>
                    <tr><th>{{ __('No. Akaun Penerima') }}</th><td>{{ $pembayaran->no_acct ?: '—' }}</td></tr>
                    <tr><th>{{ __('Deskripsi') }}</th><td>{{ $pembayaran->deskripsi ?: '—' }}</td></tr>
                </table>
            </div>
        </div>

        @if ($pembayaran->aset)
            <h2 class="h6 mt-3">{{ __('Aset Didaftarkan') }}</h2>
            <table class="table table-sm table-bordered">
                <thead class="table-light"><tr><th>{{ __('Kod Aset') }}</th><th>{{ __('Nama') }}</th><th>{{ __('Lokasi') }}</th><th class="text-end">{{ __('Kos (RM)') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    <tr>
                        <td>{{ $pembayaran->aset->kod_aset }}</td>
                        <td>{{ $pembayaran->aset->nama }}</td>
                        <td>{{ $pembayaran->aset->lokasi ?: '—' }}</td>
                        <td class="text-end">{{ number_format((float) $pembayaran->aset->kos, 2) }}</td>
                        <td>{{ $pembayaran->aset->status }}</td>
                    </tr>
                </tbody>
            </table>
        @endif

        @if ($lampiran->isNotEmpty())
            <h2 class="h6 mt-3">{{ __('Dokumen Sokongan') }}</h2>
            <ul>
                @foreach ($lampiran as $l)
                    <li><a href="{{ route('belanja.lampiran', $l->id) }}">{{ $l->file_name }}</a>
                        <span class="text-muted small">({{ number_format($l->size_bytes / 1024, 1) }} KB)</span></li>
                @endforeach
            </ul>
        @endif

        @if ($pembayaran->voucher)
            <h2 class="h6 mt-3">{{ __('Jurnal Berkaitan — Voucher') }} {{ $pembayaran->voucher->voucher_ref }}</h2>
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr><th>{{ __('Akaun') }}</th><th class="text-end">{{ __('Debit (RM)') }}</th><th class="text-end">{{ __('Kredit (RM)') }}</th><th>{{ __('Memo') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($pembayaran->voucher->entries as $e)
                        <tr>
                            <td>{{ $e->coa?->kod }} {{ $e->coa?->nama }}</td>
                            <td class="text-end">{{ number_format((float) $e->debit, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $e->kredit, 2) }}</td>
                            <td>{{ $e->memo }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="mt-3 no-print d-flex flex-wrap gap-2 align-items-center">
            {{-- Pilih mod cetak: dengan tandatangan ATAU cetakan berkomputer (lalai ikut Tetapan Penyata) --}}
            <div class="btn-group" role="group" aria-label="{{ __('Mod cetak') }}">
                <input type="radio" class="btn-check" name="modCetak" id="modSig" value="SIGNATURE" {{ $modCetak === 'SIGNATURE' ? 'checked' : '' }}>
                <label class="btn btn-outline-secondary btn-sm" for="modSig">{{ __('Dengan Tandatangan') }}</label>
                <input type="radio" class="btn-check" name="modCetak" id="modDis" value="DISCLAIMER" {{ $modCetak === 'DISCLAIMER' ? 'checked' : '' }}>
                <label class="btn btn-outline-secondary btn-sm" for="modDis">{{ __('Cetakan Berkomputer') }}</label>
            </div>
            <button type="button" class="btn btn-primary btn-sm" onclick="cetakBaucer()">
                <i class="bi bi-printer me-1"></i>{{ __('Cetak Baucer') }}
            </button>
            @if ($pembayaran->status === 'ACTIVE' && in_array(auth()->user()?->role?->value, ['admin', 'bendahari'], true))
                <form method="POST" action="{{ route('belanja.padam', $pembayaran) }}" class="d-inline"
                      onsubmit="return confirm('{{ __('Padam pembayaran ini? Jurnal berkaitan akan turut dibatalkan.') }}')">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>{{ __('Padam') }}</button>
                </form>
            @endif
            <a href="{{ route('belanja.senarai') }}" class="btn btn-outline-secondary btn-sm">{{ __('Kembali ke Senarai') }}</a>
        </div>
        @push('scripts')
        <script>
            function cetakBaucer() {
                var mod = document.querySelector('input[name=modCetak]:checked').value;
                window.open('{{ route('belanja.cetak', $pembayaran) }}?mod=' + mod + '&auto=1', '_blank');
            }
        </script>
        @endpush
    </div>
</div>
@endsection
