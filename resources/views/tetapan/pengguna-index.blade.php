@extends('layouts.app')

@section('title', __('Pengurusan Pengguna'))

@section('content')
<div class="card shadow-sm mb-4">
    <div class="card-header fw-bold d-flex justify-content-between align-items-center">
        <span>{{ __('Senarai Pengguna') }}</span>
        <a href="{{ route('tetapan.masjid.baru') }}" class="btn btn-sm btn-success">
            <i class="bi bi-building-add me-1"></i>{{ __('Masjid Baru') }}
        </a>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Log Masuk') }}</th>
                    <th>{{ __('Nama Penuh') }}</th>
                    <th>{{ __('Masjid') }}</th>
                    <th>{{ __('Peranan') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Log Masuk Terakhir') }}</th>
                    <th class="no-print" style="width:90px">{{ __('Tindakan') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($senarai as $u)
                    <tr>
                        <td>{{ $u->login }}</td>
                        <td>{{ $u->nama_penuh }}</td>
                        <td>{{ $u->masjid?->nama ?? '—' }}</td>
                        <td><span class="badge text-bg-secondary">{{ $u->role->label() }}</span></td>
                        <td><span class="badge {{ $u->is_active ? 'text-bg-success' : 'text-bg-danger' }}">{{ $u->is_active ? __('AKTIF') : __('TIDAK AKTIF') }}</span></td>
                        <td>{{ $u->last_login_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="no-print">
                            <a href="{{ route('tetapan.pengguna.edit', $u) }}" class="btn btn-sm btn-outline-primary">{{ __('Edit') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">{{ __('Tiada pengguna.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-bold">{{ __('Tambah Pengguna Baru') }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('tetapan.pengguna.simpan') }}">
            @csrf
            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="login">{{ __('Nama Log Masuk') }} <span class="text-danger">*</span></label>
                        <input type="text" name="login" id="login" class="form-control" required
                               value="{{ old('login') }}" maxlength="60" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="nama_penuh">{{ __('Nama Penuh') }} <span class="text-danger">*</span></label>
                        <input type="text" name="nama_penuh" id="nama_penuh" class="form-control" required
                               value="{{ old('nama_penuh') }}" maxlength="200">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" for="role">{{ __('Peranan') }} <span class="text-danger">*</span></label>
                        <select name="role" id="role" class="form-select" required>
                            @foreach ($roles as $r)
                                <option value="{{ $r->value }}" @selected(old('role') === $r->value)>{{ $r->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="kata_laluan">{{ __('Kata Laluan (min 6)') }} <span class="text-danger">*</span></label>
                        <input type="password" name="kata_laluan" id="kata_laluan" class="form-control" required
                               minlength="6" autocomplete="new-password">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="masjid_id">{{ __('Masjid (asal)') }} <span class="text-danger">*</span></label>
                        <select name="masjid_id" id="masjid_id" class="form-select" required>
                            @foreach ($masjids as $m)
                                <option value="{{ $m->id }}" @selected((int) old('masjid_id', app('current.masjid_id')) === (int) $m->id)>{{ $m->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label" for="masjid_ids">{{ __('Masjid Ditugaskan') }} <span class="text-muted small">{{ __('(untuk Pemerhati sahaja)') }}</span></label>
                        <select name="masjid_ids[]" id="masjid_ids" class="form-select" multiple size="4">
                            @foreach ($masjids as $m)
                                <option value="{{ $m->id }}" @selected(in_array($m->id, old('masjid_ids', [])))>{{ $m->nama }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">{{ __('Tahan Ctrl/Cmd untuk pilih beberapa. Diabaikan jika peranan bukan Pemerhati.') }}</div>
                    </div>
                </div>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                       @checked(old('is_active', '1'))>
                <label class="form-check-label" for="is_active">{{ __('Aktif') }}</label>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>{{ __('Tambah Pengguna') }}</button>
        </form>
    </div>
</div>
@endsection
