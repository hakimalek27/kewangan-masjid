@extends('layouts.app')

@section('title', __('Carta Akaun'))

@section('content')
<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <button type="button" class="btn btn-outline-primary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>{{ __('Cetak') }}
        </button>
        <a href="{{ request()->fullUrlWithQuery(['format' => 'pdf']) }}" class="btn btn-outline-danger">
            <i class="bi bi-file-earmark-pdf me-1"></i>{{ __('PDF') }}
        </a>
        <a href="{{ request()->fullUrlWithQuery(['format' => 'xls']) }}" class="btn btn-outline-success">
            <i class="bi bi-file-earmark-excel me-1"></i>{{ __('Excel') }}
        </a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Carta Akaun (Chart of Accounts)') }}</div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Kod') }}</th>
                    <th>{{ __('Nama Akaun') }}</th>
                    <th>{{ __('Jenis') }}</th>
                    <th>{{ __('Baki Normal') }}</th>
                    <th>{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($senarai as $c)
                    <tr @class(['table-secondary fw-bold' => $c->is_header])>
                        <td>{{ $c->kod }}</td>
                        <td @class(['ps-4' => ! $c->is_header])>{{ $c->nama }}</td>
                        <td>{{ $c->jenis }}</td>
                        <td>{{ $c->is_header ? '—' : $c->normal_balance }}</td>
                        <td>
                            @if ($c->is_header)
                                <span class="badge text-bg-secondary">{{ __('Header') }}</span>
                            @elseif ($c->is_active)
                                <span class="badge text-bg-success">{{ __('Aktif') }}</span>
                            @else
                                <span class="badge text-bg-danger">{{ __('Tidak Aktif') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">{{ __('Tiada akaun.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
