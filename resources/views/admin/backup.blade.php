@extends('layouts.app')

@section('title', __('Backup Luar Tapak (Google Drive)'))

@section('content')
    <div class="row g-3">
        {{-- Konfigurasi --}}
        <div class="col-xl-5">
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-bold"><i class="bi bi-gear me-1"></i>{{ __('Konfigurasi Backup') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.backup.simpan') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label" for="provider">{{ __('Penyedia') }}</label>
                            <input type="text" id="provider" class="form-control" value="Google Drive (GDRIVE)" disabled>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="gdrive_folder_id">{{ __('ID Folder Google Drive') }}</label>
                            <input type="text" name="gdrive_folder_id" id="gdrive_folder_id"
                                   class="form-control @error('gdrive_folder_id') is-invalid @enderror"
                                   maxlength="120" value="{{ old('gdrive_folder_id', $config->gdrive_folder_id ?? '') }}"
                                   placeholder="cth: 1AbCdEfGh...">
                            @error('gdrive_folder_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <small class="text-muted">{{ __('Kongsikan folder kepada e-mel service account terlebih dahulu.') }}</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="sa_json">{{ __('Fail Service Account JSON') }}</label>
                            <input type="file" name="sa_json" id="sa_json" accept=".json,application/json"
                                   class="form-control @error('sa_json') is-invalid @enderror">
                            @error('sa_json')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <small class="text-{{ $adaSaJson ? 'success' : 'muted' }}">
                                @if ($adaSaJson)
                                    <i class="bi bi-check-circle"></i> {{ __('Fail service account TELAH dikonfigurasi (muat naik semula untuk ganti).') }}
                                @else
                                    {{ __('Belum dikonfigurasi — muat naik fail JSON kunci service account.') }}
                                @endif
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label d-block">{{ __('Mod Backup') }}</label>
                            @php $modSemasa = explode(',', (string) old('backup_mode_csv', $config->backup_mode ?? 'PER_TRANSAKSI,HARIAN,LOG')); @endphp
                            @foreach (['PER_TRANSAKSI' => 'Per transaksi (setiap kutipan/bayaran/jurnal)', 'HARIAN' => 'Dump DB harian (02:00)', 'LOG' => 'Log audit & keselamatan (setiap jam)'] as $mod => $label)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="backup_mode[]" value="{{ $mod }}"
                                           id="mod_{{ $mod }}" @checked(in_array($mod, $modSemasa, true))>
                                    <label class="form-check-label" for="mod_{{ $mod }}">{{ __($label) }}</label>
                                </div>
                            @endforeach
                            @error('backup_mode')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="retention_days">{{ __('Tempoh Simpanan (hari)') }}</label>
                            <input type="number" name="retention_days" id="retention_days" min="30" max="36500"
                                   class="form-control @error('retention_days') is-invalid @enderror"
                                   value="{{ old('retention_days', $config->retention_days ?? 3650) }}">
                            @error('retention_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                                   @checked(old('is_active', $config->is_active ?? false))>
                            <label class="form-check-label" for="is_active">{{ __('Aktifkan backup automatik') }}</label>
                        </div>

                        <div class="alert alert-info small mb-3">
                            <i class="bi bi-info-circle me-1"></i>{{ __('Semua fail disulitkan dengan APP_KEY sebelum dimuat naik (.enc).') }}
                            <strong>{{ __('Backup APP_KEY secara BERASINGAN') }}</strong> — {{ __('tanpanya fail tidak boleh dipulihkan.') }}
                        </div>

                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Simpan Konfigurasi') }}</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Status & log --}}
        <div class="col-xl-7">
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-cloud-arrow-up me-1"></i>{{ __('Status Backup') }}
                        @if ($config?->last_backup_at)
                            <small class="text-muted ms-2">{{ __('terakhir:') }} {{ $config->last_backup_at }}</small>
                        @endif
                    </span>
                    <span>
                        <form method="POST" action="{{ route('admin.backup.sekarang') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success"
                                    onclick="return confirm('{{ __('Mulakan backup penuh DB sekarang?') }}')">
                                <i class="bi bi-play-circle me-1"></i>{{ __('Backup Sekarang') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.backup.tertunggak') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-primary" {{ $bilPending ? '' : 'disabled' }}>
                                <i class="bi bi-arrow-repeat me-1"></i>{{ __('Proses Tertunggak') }} ({{ $bilPending }})
                            </button>
                        </form>
                    </span>
                </div>
                <div class="card-body">
                    @if ($bilFailed)
                        <div class="alert alert-danger small">
                            <i class="bi bi-exclamation-triangle me-1"></i>{{ $bilFailed }} {{ __('item barisan backup berstatus GAGAL.') }}
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-sm table-hover table-bordered align-middle">
                            <thead class="table-light">
                                <tr><th>#</th><th>{{ __('Masa') }}</th><th>{{ __('Jenis') }}</th><th>{{ __('Fail') }}</th><th>{{ __('Saiz') }}</th><th>{{ __('Status') }}</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($logs as $log)
                                    <tr class="{{ $log->status === 'FAILED' ? 'table-danger' : '' }}">
                                        <td>{{ $log->id }}</td>
                                        <td><small>{{ $log->created_at }}</small></td>
                                        <td><small>{{ $log->jenis }}</small></td>
                                        <td>
                                            <small>{{ \Illuminate\Support\Str::limit($log->file_name, 50) ?: '—' }}</small>
                                            @if ($log->error_text)
                                                <div class="small text-danger">{{ \Illuminate\Support\Str::limit($log->error_text, 80) }}</div>
                                            @endif
                                        </td>
                                        <td><small>{{ $log->size_bytes ? number_format($log->size_bytes / 1024, 1).' KB' : '—' }}</small></td>
                                        <td><span class="badge bg-{{ $log->status === 'OK' ? 'success' : 'danger' }}">{{ $log->status }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted">{{ __('Belum ada backup dijalankan.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <small class="text-muted">{{ __('20 rekod terakhir. Checksum SHA-256 disimpan bagi setiap fail untuk uji pulih.') }}</small>
                </div>
            </div>
        </div>
    </div>
@endsection
