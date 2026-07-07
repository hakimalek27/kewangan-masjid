@extends('layouts.app')

@section('title', __('Semak Penyata (AI) — Kawalan'))

@section('content')
    <div class="row g-3">
        {{-- Kiri: toggle global + kunci pusat --}}
        <div class="col-xl-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-bold"><i class="bi bi-toggles me-1"></i>{{ __('Tetapan Global') }}</div>
                <div class="card-body">
                    <p class="small text-muted">
                        {{ __('Apabila DIHIDUPKAN, tenant boleh muat naik penyata bank untuk dianalisis AI. Menggunakan SATU kunci OpenAI pusat untuk semua tenant.') }}
                    </p>
                    <form method="POST" action="{{ route('admin.semakpenyata.toggle') }}">
                        @csrf
                        <input type="hidden" name="aktif" value="{{ $aktif ? 0 : 1 }}">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-{{ $aktif ? 'success' : 'secondary' }}">
                                {{ $aktif ? __('HIDUP') : __('MATI') }}
                            </span>
                            <button type="submit" class="btn btn-sm btn-{{ $aktif ? 'outline-danger' : 'success' }}"
                                    onclick="return confirm('{{ $aktif ? __('Matikan') : __('Hidupkan') }} {{ __('ciri Semak Penyata (AI)?') }}')">
                                <i class="bi bi-power me-1"></i>{{ $aktif ? __('Matikan') : __('Hidupkan') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header fw-bold"><i class="bi bi-key me-1"></i>{{ __('Kunci OpenAI Pusat') }}</div>
                <div class="card-body">
                    @if ($keyMasked)
                        <p class="small mb-2">{{ __('Kunci semasa') }}: <code>{{ $keyMasked }}</code></p>
                    @else
                        <p class="small text-danger mb-2"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Belum dikonfigurasi — ciri tidak akan berfungsi.') }}</p>
                    @endif
                    <form method="POST" action="{{ route('admin.semakpenyata.kunci') }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small" for="api_key">{{ __('Kunci API OpenAI') }}</label>
                            <input type="password" name="api_key" id="api_key" maxlength="200"
                                   class="form-control form-control-sm" autocomplete="off"
                                   placeholder="{{ $keyMasked ? __('(biar kosong untuk kekal)') : 'sk-...' }}">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small" for="model">{{ __('Model') }}</label>
                            <input type="text" name="model" id="model" maxlength="80" required
                                   class="form-control form-control-sm" value="{{ old('model', $model) }}" placeholder="gpt-4o">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small" for="base_url">{{ __('Base URL (pilihan)') }}</label>
                            <input type="url" name="base_url" id="base_url" maxlength="200"
                                   class="form-control form-control-sm" value="{{ old('base_url', $baseUrl) }}"
                                   placeholder="https://api.openai.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small" for="kos_per_1k">{{ __('Kos USD per 1000 token (anggaran)') }}</label>
                            <input type="number" step="0.0001" min="0" max="10" name="kos_per_1k" id="kos_per_1k"
                                   class="form-control form-control-sm" value="{{ old('kos_per_1k', $kosPer1k) }}" placeholder="0.005">
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>{{ __('Simpan') }}</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Kanan: kuota per-tenant --}}
        <div class="col-xl-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-bold"><i class="bi bi-people me-1"></i>{{ __('Kuota Per-Tenant (bulan semasa)') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Masjid') }}</th>
                                <th class="text-center">{{ __('Guna') }}</th>
                                <th class="text-center">{{ __('Had') }}</th>
                                <th class="text-center">{{ __('Top-up') }}</th>
                                <th class="text-center">{{ __('Baki') }}</th>
                                <th class="text-end">{{ __('Tindakan') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tenants as $t)
                                <tr>
                                    <td>{{ $t['nama'] }}</td>
                                    <td class="text-center">{{ $t['guna'] }}</td>
                                    <td class="text-center">
                                        @if ($t['had'] === 0)
                                            <span class="badge bg-secondary">{{ __('Mati') }}</span>
                                        @else
                                            {{ $t['had'] }}
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $t['topup'] > 0 ? '+'.$t['topup'] : '—' }}</td>
                                    <td class="text-center"><span class="badge bg-{{ $t['baki'] > 0 ? 'success' : 'danger' }}">{{ $t['baki'] }}</span></td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <form method="POST" action="{{ route('admin.semakpenyata.kuota', $t['id']) }}" class="d-inline-flex gap-1">
                                                @csrf
                                                <input type="number" name="kuota" min="0" max="1000" value="{{ $t['had'] }}"
                                                       class="form-control form-control-sm" style="width:5rem" title="{{ __('Had bulanan (0 = mati)') }}">
                                                <button class="btn btn-sm btn-outline-primary" title="{{ __('Simpan had') }}"><i class="bi bi-check-lg"></i></button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.semakpenyata.topup', $t['id']) }}" class="d-inline-flex gap-1">
                                                @csrf
                                                <input type="number" name="tambah" min="1" max="100" value="1"
                                                       class="form-control form-control-sm" style="width:4rem" title="{{ __('Top-up bulan ini') }}">
                                                <button class="btn btn-sm btn-outline-success" title="{{ __('Top-up') }}"><i class="bi bi-plus-lg"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Log penggunaan --}}
            <div class="card shadow-sm">
                <div class="card-header fw-bold"><i class="bi bi-clock-history me-1"></i>{{ __('Log Penggunaan') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>{{ __('Masjid') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th class="text-center">{{ __('Baris') }}</th>
                                <th class="text-center">{{ __('Auto padan') }}</th>
                                <th class="text-end">{{ __('Token') }}</th>
                                <th class="text-end">{{ __('Kos (USD)') }}</th>
                                <th>{{ __('Tarikh') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($log as $l)
                                <tr>
                                    <td>{{ $l->id }}</td>
                                    <td><small>{{ $namaMasjid[$l->masjid_id] ?? $l->masjid_id }}</small></td>
                                    <td>
                                        @php $warna = ['SEDIA'=>'success','GAGAL'=>'danger','AI_PROCESSING'=>'info','UPLOADED'=>'secondary'][$l->status] ?? 'secondary'; @endphp
                                        <span class="badge bg-{{ $warna }}">{{ $l->status }}</span>
                                    </td>
                                    <td class="text-center">{{ $l->bil_baris }}</td>
                                    <td class="text-center">{{ $l->bil_auto_padan }}</td>
                                    <td class="text-end">{{ $l->tokens_used ? number_format($l->tokens_used) : '—' }}</td>
                                    <td class="text-end">{{ $l->cost_usd !== null ? number_format((float) $l->cost_usd, 4) : '—' }}</td>
                                    <td><small>{{ $l->created_at?->format('Y-m-d H:i') }}</small></td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted py-3">{{ __('Tiada rekod penggunaan lagi.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($log->hasPages())
                    <div class="card-footer">{{ $log->links() }}</div>
                @endif
            </div>
        </div>
    </div>
@endsection
