@extends('layouts.app')

@section('title', __('Imbangan Duga'))

@section('content')
<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <x-period-filter />
            <div class="col-auto">
                <x-export-buttons />
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-bold d-flex justify-content-between align-items-center">
        <span>{{ __('Imbangan Duga sehingga') }} {{ $ym }}</span>
        @if ($tb['jumlah_debit'] === $tb['jumlah_kredit'])
            <span class="badge text-bg-success">{{ __('SEIMBANG') }} &#10004;</span>
        @else
            <span class="badge text-bg-danger">{{ __('TIDAK SEIMBANG') }}</span>
        @endif
    </div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Kod') }}</th>
                    <th>{{ __('Nama Akaun') }}</th>
                    <th class="text-end">{{ __('Debit (RM)') }}</th>
                    <th class="text-end">{{ __('Kredit (RM)') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tb['baris'] as $r)
                    <tr>
                        <td>{{ $r->kod }}</td>
                        <td>{{ $r->nama }}</td>
                        <td class="text-end">{{ $r->debit !== '0.00' ? number_format((float) $r->debit, 2) : '' }}</td>
                        <td class="text-end">{{ $r->kredit !== '0.00' ? number_format((float) $r->kredit, 2) : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted">{{ __('Tiada baki bagi cutoff ini.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="2" class="text-end">{{ __('JUMLAH') }}</th>
                    <th class="text-end">{{ number_format((float) $tb['jumlah_debit'], 2) }}</th>
                    <th class="text-end">{{ number_format((float) $tb['jumlah_kredit'], 2) }}</th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
