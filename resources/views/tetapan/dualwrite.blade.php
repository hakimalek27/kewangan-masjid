@extends('layouts.app')

@section('title', __('Dual-Write SPPKMS (Sistem Lama)'))

@section('content')
    <div class="row g-3">
        {{-- Tetapan & kredensial --}}
        <div class="col-xl-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-bold"><i class="bi bi-toggles me-1"></i>{{ __('Tetapan Dual-Write') }}</div>
                <div class="card-body">
                    <p class="small text-muted">
                        {{ __('Apabila DIHIDUPKAN, setiap') }} <strong>{{ __('kutipan/pembayaran baharu') }}</strong> {{ __('masjid ini turut dihantar ke borang SPPKMS lama') }}
                        (<code>{{ $legacyUrl }}</code>) {{ __('di bawah akaun SPPKMS masjid ini') }} —
                        {{ __('jambatan sementara semasa tempoh peralihan.') }}
                    </p>

                    <form method="POST" action="{{ route('tetapan.dualwrite.toggle') }}">
                        @csrf
                        <input type="hidden" name="aktif" value="{{ $aktif ? 0 : 1 }}">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-{{ $aktif ? 'success' : 'secondary' }}">
                                {{ $aktif ? __('HIDUP') : __('MATI') }}
                            </span>
                            <button type="submit" class="btn btn-sm btn-{{ $aktif ? 'outline-danger' : 'success' }}"
                                    onclick="return confirm('{{ $aktif ? __('Matikan') : __('Hidupkan') }} {{ __('dual-write SPPKMS?') }}')">
                                <i class="bi bi-power me-1"></i>{{ $aktif ? __('Matikan') : __('Hidupkan') }}
                            </button>
                        </div>
                    </form>

                    <div class="alert alert-warning small mb-0">
                        <i class="bi bi-info-circle me-1"></i>{{ __('No resit/baucer dihantar sebagai') }} <strong>{{ __('manual') }}</strong>
                        {{ __('(semak=off) — kaunter auto SPPKMS lama TIDAK diganggu. Bayaran jenis') }} <strong>{{ __('ASET') }}</strong>
                        {{ __('di-SKIP (borang aset lama kompleks — hantar manual). Rekod yang dibatalkan di sini') }}
                        {{ __('perlu') }} <strong>{{ __('dipadam manual') }}</strong> {{ __('di SPPKMS lama (amaran Telegram dihantar).') }}
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header fw-bold"><i class="bi bi-key me-1"></i>{{ __('Kredensial SPPKMS Lama') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('tetapan.dualwrite.kredensial') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="sppkms_login">{{ __('Login') }}</label>
                            <input type="text" name="sppkms_login" id="sppkms_login" maxlength="100"
                                   class="form-control @error('sppkms_login') is-invalid @enderror"
                                   placeholder="{{ $loginMasked ?? __('cth: malmutaqqin') }}" autocomplete="off">
                            @error('sppkms_login')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @if ($loginMasked)
                                <small class="text-success"><i class="bi bi-check-circle"></i>
                                    {{ __('Tersimpan dalam vault:') }} {{ $loginMasked }}</small>
                            @endif
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="sppkms_password">{{ __('Kata Laluan') }}</label>
                            <input type="password" name="sppkms_password" id="sppkms_password" maxlength="200"
                                   class="form-control @error('sppkms_password') is-invalid @enderror"
                                   placeholder="{{ $adaPassword ? __('••••••••  (tersimpan)') : '' }}" autocomplete="new-password">
                            @error('sppkms_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @if ($adaPassword)
                                <small class="text-success"><i class="bi bi-check-circle"></i>
                                    {{ __('Kata laluan TELAH dikonfigurasi (isi semula untuk ganti).') }}</small>
                            @endif
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-save me-1"></i>{{ __('Simpan ke Vault') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Status & jadual sync --}}
        <div class="col-xl-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-arrow-left-right me-1"></i>{{ __('Status Penghantaran') }}</span>
                    <form method="POST" action="{{ route('tetapan.dualwrite.tertunggak') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-primary"
                                {{ ($statistik['PENDING'] ?? 0) ? '' : 'disabled' }}>
                            <i class="bi bi-arrow-repeat me-1"></i>{{ __('Hantar Semua Tertunggak') }} ({{ $statistik['PENDING'] ?? 0 }})
                        </button>
                    </form>
                </div>
                <div class="card-body">
                    {{-- Statistik kecil --}}
                    <div class="row g-2 mb-3">
                        @foreach (['PENDING' => 'warning', 'DONE' => 'success', 'FAILED' => 'danger', 'SKIPPED' => 'secondary'] as $st => $warna)
                            <div class="col-6 col-md-3">
                                <a href="{{ route('tetapan.dualwrite', ['status' => $st]) }}" class="text-decoration-none">
                                    <div class="border rounded p-2 text-center {{ $tapis === $st ? 'border-'.$warna : '' }}">
                                        <div class="fs-4 fw-bold text-{{ $warna }}">{{ $statistik[$st] ?? 0 }}</div>
                                        <small class="text-muted">{{ $st }}</small>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>

                    @if ($tapis)
                        <div class="mb-2">
                            <a href="{{ route('tetapan.dualwrite') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i>{{ __('Buang penapis:') }} {{ $tapis }}
                            </a>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-sm table-hover table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th><th>{{ __('Masa') }}</th><th>{{ __('Jenis') }}</th><th>{{ __('Sumber') }}</th><th>{{ __('Recno') }}</th>
                                    <th>{{ __('Cubaan') }}</th><th>{{ __('Status') }}</th><th>{{ __('Ralat Terakhir') }}</th><th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($senarai as $sync)
                                    <tr class="{{ $sync->status === 'FAILED' ? 'table-danger' : '' }}">
                                        <td>{{ $sync->id }}</td>
                                        <td><small>{{ $sync->created_at }}</small></td>
                                        <td><small>{{ $sync->source_type }}</small></td>
                                        <td><small>#{{ $sync->source_id }}</small></td>
                                        <td><small>{{ $sync->sppkms_recno ?? '—' }}</small></td>
                                        <td>{{ $sync->attempts }}</td>
                                        <td>
                                            <span class="badge bg-{{ ['PENDING' => 'warning', 'DONE' => 'success', 'FAILED' => 'danger', 'SKIPPED' => 'secondary'][$sync->status] ?? 'light' }}">
                                                {{ $sync->status }}
                                            </span>
                                        </td>
                                        <td><small class="text-danger">{{ \Illuminate\Support\Str::limit($sync->last_error, 80) ?: '—' }}</small></td>
                                        <td>
                                            @if ($sync->status === 'FAILED')
                                                <form method="POST" action="{{ route('tetapan.dualwrite.retry', $sync) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-arrow-clockwise"></i> {{ __('Cuba Semula') }}
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="9" class="text-center text-muted">{{ __('Tiada rekod sync.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $senarai->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
