@props([
    'name' => 'coa_id',
    'jenis' => null,        {{-- tapis ikut jenis COA: Hasil/Belanja/Aset/Liabiliti/Ekuiti atau array --}}
    'julat' => null,        {{-- tapis ikut awalan kod, cth ['200-01'] atau ['400','450','300'] --}}
    'required' => true,
    'selected' => null,
    'placeholder' => '-- Pilih Akaun --',
    'label' => null,
    'mapping' => false,     {{-- true = tunjuk label tempatan (coa_local_mapping) dengan ⭐ di atas --}}
])

@php
    $masjidId = app()->bound('current.masjid_id') ? app('current.masjid_id') : config('spkm.masjid_id');

    $q = \App\Models\Coa::withoutMasjidScope()
        ->where('masjid_id', $masjidId)
        ->where('is_header', 0)->where('is_active', 1);

    if ($jenis) $q->whereIn('jenis', (array) $jenis);
    if ($julat) $q->where(function ($qq) use ($julat) {
        foreach ((array) $julat as $j) $qq->orWhere('kod', 'like', $j.'%');
    });

    $senarai = $q->orderBy('kod')->get(['id', 'kod', 'nama']);

    $labelTempatan = collect();
    if ($mapping) {
        $labelTempatan = \DB::table('coa_local_mapping as m')
            ->join('coa as c', 'c.id', '=', 'm.coa_id')
            ->where('m.masjid_id', $masjidId)
            ->orderBy('m.local_label')
            ->get(['m.local_label', 'c.id', 'c.kod']);
    }
@endphp

<div class="mb-3">
    @if ($label)<label class="form-label" for="{{ $name }}">{{ __($label) }} @if($required)<span class="text-danger">*</span>@endif</label>@endif
    <select name="{{ $name }}" id="{{ $name }}" data-searchable data-placeholder="{{ __($placeholder) }}"
            {{ $required ? 'required' : '' }} {{ $attributes->merge(['class' => 'form-select']) }}>
        <option value="">{{ __($placeholder) }}</option>
        @if ($labelTempatan->isNotEmpty())
            <optgroup label="⭐ {{ __('Label Tempatan Masjid') }}">
                @foreach ($labelTempatan as $m)
                    <option value="{{ $m->id }}" @selected(old($name, $selected) == $m->id && $loop->first)>⭐ {{ $m->local_label }} ({{ $m->kod }})</option>
                @endforeach
            </optgroup>
        @endif
        @foreach ($senarai as $coa)
            <option value="{{ $coa->id }}" @selected(old($name, $selected) == $coa->id)>{{ $coa->kod }} {{ $coa->nama }}</option>
        @endforeach
    </select>
</div>
