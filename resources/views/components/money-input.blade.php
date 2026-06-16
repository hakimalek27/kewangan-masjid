@props(['name' => 'jumlah', 'label' => 'Jumlah (RM)', 'required' => true, 'value' => null])

<div class="mb-3">
    <label class="form-label" for="{{ $name }}">{{ __($label) }} @if($required)<span class="text-danger">*</span>@endif</label>
    <input type="number" step="0.01" min="0.01" name="{{ $name }}" id="{{ $name }}"
           value="{{ old($name, $value) }}" {{ $required ? 'required' : '' }}
           {{ $attributes->merge(['class' => 'form-control text-rm']) }} placeholder="0.00">
</div>
