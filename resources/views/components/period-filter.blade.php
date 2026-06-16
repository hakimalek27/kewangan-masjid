@props(['bulan' => true, 'tahun' => true, 'tahunDari' => 2020, 'tahunHingga' => 2030])

@php
    $namaBulan = [1=>'Januari','Februari','Mac','April','Mei','Jun','Julai','Ogos','September','Oktober','November','Disember'];
@endphp

@if ($bulan)
    <div class="col-auto">
        <select name="bln" class="form-select">
            @foreach ($namaBulan as $i => $nama)
                <option value="{{ $i }}" @selected((int) request('bln', now()->month) === $i)>{{ __($nama) }}</option>
            @endforeach
        </select>
    </div>
@endif
@if ($tahun)
    <div class="col-auto">
        <select name="year" class="form-select">
            @for ($y = $tahunHingga; $y >= $tahunDari; $y--)
                <option value="{{ $y }}" @selected((int) request('year', now()->year) === $y)>{{ $y }}</option>
            @endfor
        </select>
    </div>
@endif
