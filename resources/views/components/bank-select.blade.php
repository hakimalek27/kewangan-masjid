@props(['name' => 'bank_account_id', 'required' => false, 'label' => 'Bank', 'selected' => null])

@php
    $masjidId = app()->bound('current.masjid_id') ? app('current.masjid_id') : config('spkm.masjid_id');
    $banks = \App\Models\BankAccount::withoutMasjidScope()
        ->where('masjid_id', $masjidId)->where('status', 'AKTIF')
        ->orderBy('slot')->get();
@endphp

<div class="mb-3">
    <label class="form-label" for="{{ $name }}">{{ __($label) }} @if($required)<span class="text-danger">*</span>@endif</label>
    <select name="{{ $name }}" id="{{ $name }}" {{ $required ? 'required' : '' }}
            {{ $attributes->merge(['class' => 'form-select']) }}>
        <option value="">{{ __('-- Pilih Bank --') }}</option>
        @foreach ($banks as $bank)
            <option value="{{ $bank->id }}" @selected(old($name, $selected) == $bank->id)>
                {{ __('Slot') }} {{ $bank->slot }} : {{ $bank->nama_bank }} ({{ $bank->no_akaun }})
            </option>
        @endforeach
    </select>
</div>
