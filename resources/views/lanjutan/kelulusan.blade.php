@extends('layouts.app')

@section('title', __('Kelulusan Maker-Checker'))

@section('content')
<div class="alert alert-info small">
    <i class="bi bi-shield-check me-1"></i>
    {{ __('Had kelulusan semasa:') }} <strong>{{ $had > 0 ? 'RM '.number_format($had, 2) : __('DIMATIKAN (0)') }}</strong> —
    {{ __('bayaran bendahari melebihi had memerlukan kelulusan admin/pengerusi sebelum direkodkan ke jurnal.') }}
    {{ __('Ubah had di halaman') }} <a href="{{ route('kawalan.index') }}">{{ __('Kawalan') }}</a>.
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header fw-bold">{{ __('Permohonan Menunggu') }} <span class="badge text-bg-danger ms-1">{{ $pending->count() }}</span></div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width:60px">#</th>
                    <th>{{ __('Butiran Permohonan') }}</th>
                    <th class="text-end" style="width:130px">{{ __('Jumlah (RM)') }}</th>
                    <th style="width:150px">{{ __('Pemohon (Maker)') }}</th>
                    <th style="width:140px">{{ __('Tarikh Mohon') }}</th>
                    <th class="no-print" style="width:210px">{{ __('Tindakan') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pending as $p)
                    @php $payload = json_decode((string) $p->payload, true) ?: []; @endphp
                    <tr>
                        <td>#{{ $p->id }}</td>
                        <td class="small">
                            <span class="badge text-bg-secondary">{{ $payload['_jenis'] ?? 'BAYARAN' }}</span>
                            {{ $payload['deskripsi'] ?? '' }}
                            @if (!empty($payload['pemohon'])) — {{ __('penerima:') }} {{ $payload['pemohon'] }} @endif
                            @if (!empty($payload['cara_bayar'])) <span class="badge text-bg-light text-dark">{{ $payload['cara_bayar'] }}</span> @endif
                        </td>
                        <td class="text-rm fw-bold">{{ number_format((float) $p->amaun, 2) }}</td>
                        <td class="small">{{ $pengguna->get($p->maker_id)?->nama_penuh ?? $pengguna->get($p->maker_id)?->login ?? '—' }}</td>
                        <td class="small">{{ $p->made_at?->format('d/m/Y H:i') }}</td>
                        <td class="no-print">
                            <form method="POST" action="{{ route('kelulusan.lulus', $p->id) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-success" onclick="return confirm('{{ __('Lulus & rekodkan pembayaran') }} #{{ $p->id }} (RM {{ number_format((float) $p->amaun, 2) }})?')">
                                    <i class="bi bi-check-lg"></i> {{ __('Lulus') }}
                                </button>
                            </form>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#tolakModal-{{ $p->id }}">
                                <i class="bi bi-x-lg"></i> {{ __('Tolak') }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">{{ __('Tiada permohonan menunggu kelulusan.') }} 🎉</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Sejarah Keputusan (30 terkini)') }}</div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-bordered align-middle">
            <thead class="table-light">
                <tr><th>#</th><th>{{ __('Catatan') }}</th><th class="text-end">{{ __('Jumlah (RM)') }}</th><th>{{ __('Status') }}</th><th>{{ __('Checker') }}</th><th>{{ __('Diputuskan') }}</th></tr>
            </thead>
            <tbody>
                @forelse ($sejarah as $s)
                    <tr>
                        <td>#{{ $s->id }}</td>
                        <td class="small">{{ \Illuminate\Support\Str::limit($s->remark, 70) }}</td>
                        <td class="text-rm">{{ number_format((float) $s->amaun, 2) }}</td>
                        <td><span class="badge {{ $s->status === 'APPROVED' ? 'text-bg-success' : 'text-bg-danger' }}">{{ $s->status }}</span></td>
                        <td class="small">{{ $pengguna->get($s->checker_id)?->nama_penuh ?? '—' }}</td>
                        <td class="small">{{ $s->decided_at?->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">{{ __('Tiada sejarah.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Modal tolak --}}
@foreach ($pending as $p)
    <div class="modal fade" id="tolakModal-{{ $p->id }}" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('kelulusan.tolak', $p->id) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Tolak Permohonan') }} #{{ $p->id }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="sebab-{{ $p->id }}">{{ __('Sebab Penolakan') }}</label>
                    <textarea name="sebab" id="sebab-{{ $p->id }}" class="form-control" rows="2" maxlength="200"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-danger">{{ __('Sahkan Tolak') }}</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
@endsection
