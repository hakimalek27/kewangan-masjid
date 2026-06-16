@props([
    'coaInput' => 'coa_id',     {{-- id elemen select COA pada borang --}}
    'jumlahInput' => 'jumlah',  {{-- id elemen input jumlah pada borang --}}
])

@php
    // Boleh dimatikan melalui /tetapan/kawalan (Setting 'budget_warning')
    $aktif = \App\Support\Setting::get('budget_warning', 'on') !== 'off';
@endphp

@if ($aktif)
<div x-data="{
        amaran: false,
        info: null,
        async semak() {
            const coa = document.getElementById('{{ $coaInput }}')?.value;
            const jum = document.getElementById('{{ $jumlahInput }}')?.value;
            if (!coa || !jum || parseFloat(jum) <= 0) { this.amaran = false; this.info = null; return; }
            try {
                const r = await fetch('{{ route('belanjawan.semak') }}?coa_id=' + encodeURIComponent(coa) + '&jumlah=' + encodeURIComponent(jum),
                    { headers: { 'Accept': 'application/json' } });
                if (!r.ok) return;
                const d = await r.json();
                this.info = d.ada ? d : null;
                this.amaran = !!d.melebihi;
            } catch (e) { /* best-effort — jangan ganggu borang */ }
        },
        pasang() {
            ['{{ $coaInput }}', '{{ $jumlahInput }}'].forEach((id) => {
                const el = document.getElementById(id);
                el?.addEventListener('change', () => this.semak());
                el?.addEventListener('input', () => { clearTimeout(this._t); this._t = setTimeout(() => this.semak(), 400); });
            });
        }
     }" x-init="pasang()">
    <template x-if="amaran">
        <div class="alert alert-danger py-2 small mb-3">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <strong>{{ __('AMARAN BELANJAWAN:') }}</strong> {{ __('jumlah ini akan') }} <strong>{{ __('MELEBIHI peruntukan tahunan') }}</strong> {{ __('akaun dipilih.') }}
            {{ __('Peruntukan:') }} RM <span x-text="info?.peruntukan"></span> ·
            {{ __('Sebenar setakat ini:') }} RM <span x-text="info?.sebenar"></span> ·
            {{ __('Baki selepas bayaran ini:') }} <strong class="text-danger">RM <span x-text="info?.baki"></span></strong>
        </div>
    </template>
    <template x-if="!amaran && info">
        <div class="alert alert-success py-2 small mb-3">
            <i class="bi bi-check-circle me-1"></i>
            {{ __('Dalam peruntukan belanjawan — baki selepas bayaran ini:') }} RM <span x-text="info?.baki"></span>
            ({{ __('peruntukan') }} RM <span x-text="info?.peruntukan"></span>).
        </div>
    </template>
</div>
@endif
