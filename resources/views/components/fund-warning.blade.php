@props([
    'coaInput' => 'coa_id',     {{-- id elemen select COA pada borang --}}
    'jumlahInput' => 'jumlah',  {{-- id elemen input jumlah pada borang --}}
])

@php
    // Boleh dimatikan melalui /tetapan/kawalan (Setting 'fund_deficit_alert')
    $aktif = \App\Support\Setting::get('fund_deficit_alert', 'on') !== 'off';
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
                const r = await fetch('{{ route('dana.semak') }}?coa_id=' + encodeURIComponent(coa) + '&jumlah=' + encodeURIComponent(jum),
                    { headers: { 'Accept': 'application/json' } });
                if (!r.ok) return;
                const d = await r.json();
                this.info = d.dana ? d : null;
                this.amaran = !!d.defisit_selepas;
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
            <strong>{{ __('AMARAN DANA:') }}</strong> {{ __('bayaran ini menyebabkan dana') }}
            <strong x-text="info?.nama"></strong> <strong>{{ __('DEFISIT') }}</strong>.
            {{ __('Baki semasa:') }} RM <span x-text="info?.baki"></span> ·
            {{ __('Baki selepas bayaran ini:') }} <strong class="text-danger">RM <span x-text="info?.baki_selepas"></span></strong>.
            {{ __('Dana tidak dibenarkan defisit — sila semak sebelum teruskan.') }}
        </div>
    </template>
    <template x-if="!amaran && info">
        <div class="alert alert-info py-2 small mb-3">
            <i class="bi bi-piggy-bank me-1"></i>
            {{ __('Akaun dana') }} <span x-text="info?.nama"></span> — {{ __('baki semasa') }} RM <span x-text="info?.baki"></span>;
            {{ __('baki selepas bayaran ini:') }} RM <span x-text="info?.baki_selepas"></span>.
        </div>
    </template>
</div>
@endif
