@php /** @var \App\Models\Sewaan|null $sewaan */ $sewaan = $sewaan ?? null; @endphp

<div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label class="form-label" for="nama_penyewa">{{ __('Nama Penyewa') }} <span class="text-danger">*</span></label>
            <input type="text" name="nama_penyewa" id="nama_penyewa" class="form-control" required
                   value="{{ old('nama_penyewa', $sewaan?->nama_penyewa) }}" maxlength="200">
        </div>
    </div>
    <div class="col-md-3">
        <div class="mb-3">
            <label class="form-label" for="no_kp">{{ __('No. KP') }}</label>
            <input type="text" name="no_kp" id="no_kp" class="form-control"
                   value="{{ old('no_kp', $sewaan?->no_kp) }}" maxlength="30">
        </div>
    </div>
    <div class="col-md-3">
        <div class="mb-3">
            <label class="form-label" for="no_akaun">{{ __('No. Akaun') }}</label>
            <input type="text" name="no_akaun" id="no_akaun" class="form-control"
                   value="{{ old('no_akaun', $sewaan?->no_akaun) }}" maxlength="60">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="mb-3">
            <label class="form-label" for="tempoh_sewa">{{ __('Tempoh Sewa') }}</label>
            <input type="text" name="tempoh_sewa" id="tempoh_sewa" class="form-control"
                   value="{{ old('tempoh_sewa', $sewaan?->tempoh_sewa) }}" maxlength="100">
        </div>
    </div>
    <div class="col-md-3">
        <div class="mb-3">
            <label class="form-label" for="jenis_sewa">{{ __('Jenis Sewa') }}</label>
            <input type="text" name="jenis_sewa" id="jenis_sewa" class="form-control"
                   value="{{ old('jenis_sewa', $sewaan?->jenis_sewa) }}" maxlength="100">
        </div>
    </div>
    <div class="col-md-2">
        <div class="mb-3">
            <label class="form-label" for="kadar_sewa">{{ __('Kadar Sewa (RM)') }}</label>
            <input type="number" step="0.01" min="0" name="kadar_sewa" id="kadar_sewa" class="form-control text-rm"
                   value="{{ old('kadar_sewa', $sewaan?->kadar_sewa) }}" placeholder="0.00">
        </div>
    </div>
    <div class="col-md-2">
        <div class="mb-3">
            <label class="form-label" for="deposit_amaun">{{ __('Amaun Deposit (RM)') }}</label>
            <input type="number" step="0.01" min="0" name="deposit_amaun" id="deposit_amaun" class="form-control text-rm"
                   value="{{ old('deposit_amaun', $sewaan?->deposit_amaun) }}" placeholder="0.00">
        </div>
    </div>
    <div class="col-md-2">
        <div class="mb-3">
            <label class="form-label" for="deposit_resit">{{ __('No. Resit Deposit') }}</label>
            <input type="text" name="deposit_resit" id="deposit_resit" class="form-control"
                   value="{{ old('deposit_resit', $sewaan?->deposit_resit) }}" maxlength="60">
        </div>
    </div>
</div>

<div class="mb-3">
    <label class="form-label" for="catatan">{{ __('Catatan') }}</label>
    <input type="text" name="catatan" id="catatan" class="form-control"
           value="{{ old('catatan', $sewaan?->catatan) }}" maxlength="500">
</div>
