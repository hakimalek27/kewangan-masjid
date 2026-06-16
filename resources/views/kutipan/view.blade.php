@extends('layouts.app')

@section('title', __('Resit Kutipan').' '.$kutipan->no_resit)

@section('content')
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold">{{ __('RESIT RASMI — No.') }} {{ $kutipan->no_resit }}</span>
        <span class="badge {{ $kutipan->status === 'ACTIVE' ? 'text-bg-success' : 'text-bg-danger' }}">{{ $kutipan->status }}</span>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr><th class="w-50">{{ __('Jenis') }}</th><td>{{ $kutipan->jenis }}@if($kutipan->jenis_tabung) ({{ $kutipan->jenis_tabung }})@endif</td></tr>
                    <tr><th>{{ __('Tarikh Kutipan') }}</th><td>{{ $kutipan->tarikh?->format('d/m/Y') }}</td></tr>
                    <tr><th>{{ __('Kategori (COA)') }}</th><td>{{ $kutipan->coa?->kod }} {{ $kutipan->coa?->nama }}</td></tr>
                    <tr><th>{{ __('Kaedah') }}</th><td>{{ $kutipan->kaedah }}</td></tr>
                    <tr><th>{{ __('Jumlah (RM)') }}</th><td class="fw-bold">{{ number_format((float) $kutipan->jumlah, 2) }}</td></tr>
                    <tr><th>{{ __('No. Resit') }}</th><td>{{ $kutipan->no_resit }} ({{ $kutipan->auto_resit ? 'auto' : 'manual' }})</td></tr>
                    <tr><th>{{ __('Nama Pemberi') }}</th><td>{{ $kutipan->nama_pemberi ?: '—' }}</td></tr>
                    @if($kutipan->fd)
                        <tr><th>{{ __('Pelaburan (FD)') }}</th><td>{{ $kutipan->fd->institusi }} — {{ $kutipan->fd->no_sijil }}</td></tr>
                    @endif
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr><th class="w-50">{{ __('Saksi') }} 1</th><td>{{ $kutipan->saksi1 ?: '—' }}</td></tr>
                    <tr><th>{{ __('Saksi') }} 2</th><td>{{ $kutipan->saksi2 ?: '—' }}</td></tr>
                    <tr><th>{{ __('Saksi') }} 3</th><td>{{ $kutipan->saksi3 ?: '—' }}</td></tr>
                    <tr><th>{{ __('Bank') }}</th><td>{{ $kutipan->bank ? $kutipan->bank->nama_bank.' ('.$kutipan->bank->no_akaun.')' : '—' }}</td></tr>
                    <tr><th>{{ __('No. Slip Bank') }}</th><td>{{ $kutipan->no_slip ?: '—' }}</td></tr>
                    <tr><th>{{ __('Tarikh Bank Masuk') }}</th><td>{{ $kutipan->tar_bankin?->format('d/m/Y') ?: '—' }}</td></tr>
                    @if($kutipan->jenis === 'TABUNG')
                        <tr><th>{{ __('Tarikh Kiraan') }}</th><td>{{ $kutipan->tar_kira?->format('d/m/Y') ?: '—' }}</td></tr>
                        <tr><th>{{ __('Dibank Oleh') }}</th><td>{{ $kutipan->dibank_oleh ?: '—' }}</td></tr>
                    @endif
                    <tr><th>{{ __('Deskripsi') }}</th><td>{{ $kutipan->deskripsi ?: '—' }}</td></tr>
                </table>
            </div>
        </div>

        @if ($kutipan->denominasi->isNotEmpty())
            <h2 class="h6 mt-3">{{ __('Pecahan Denominasi') }}</h2>
            <table class="table table-sm table-bordered w-auto">
                <thead><tr><th>{{ __('Denominasi (RM)') }}</th><th>{{ __('Bilangan') }}</th><th>{{ __('Jumlah (RM)') }}</th></tr></thead>
                <tbody>
                    @foreach ($kutipan->denominasi as $d)
                        <tr>
                            <td class="text-end">{{ number_format((float) $d->denominasi, 2) }}</td>
                            <td class="text-end">{{ $d->bilangan }}</td>
                            <td class="text-end">{{ number_format((float) $d->denominasi * $d->bilangan, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($kutipan->voucher)
            <h2 class="h6 mt-3">{{ __('Jurnal Berkaitan — Voucher') }} {{ $kutipan->voucher->voucher_ref }}</h2>
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr><th>{{ __('Akaun') }}</th><th class="text-end">{{ __('Debit (RM)') }}</th><th class="text-end">{{ __('Kredit (RM)') }}</th><th>{{ __('Memo') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($kutipan->voucher->entries as $e)
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
            <button type="button" class="btn btn-primary btn-sm" onclick="cetakResit(1)">
                <i class="bi bi-printer me-1"></i>{{ __('Cetak Resit (1/A4)') }}
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="cetakResit(2)">
                <i class="bi bi-scissors me-1"></i>{{ __('Cetak 2 Salinan (1 A4, koyak tengah)') }}
            </button>
            @if ($kutipan->status === 'ACTIVE' && in_array(auth()->user()?->role?->value, ['admin', 'bendahari'], true))
                <form method="POST" action="{{ route('kutipan.padam', $kutipan) }}" class="d-inline"
                      onsubmit="return confirm('{{ __('Padam kutipan ini? Jurnal berkaitan akan turut dibatalkan.') }}')">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>{{ __('Padam') }}</button>
                </form>
            @endif
            <a href="{{ route('kutipan.senarai') }}" class="btn btn-outline-secondary btn-sm">{{ __('Kembali ke Senarai') }}</a>
        </div>
        @push('scripts')
        <script>
            function cetakResit(salinan) {
                var mod = document.querySelector('input[name=modCetak]:checked').value;
                window.open('{{ route('kutipan.cetak', $kutipan) }}?salinan=' + salinan + '&mod=' + mod + '&auto=1', '_blank');
            }
        </script>
        @endpush
    </div>
</div>
@endsection
