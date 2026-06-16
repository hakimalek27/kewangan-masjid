{{-- Kepala PDF kongsi: $namaMasjid, $tajukLaporan, $tempoh (+ $masjidSemasa global) --}}
@php
    // Logo sebagai data-URI base64 — dompdf tidak boleh muat URL asset() dengan pasti
    $__logo = null;
    try {
        $__lp = ($masjidSemasa ?? null)?->logoPath();
        if ($__lp) {
            $__ext = strtolower(pathinfo($__lp, PATHINFO_EXTENSION)) ?: 'png';
            $__logo = 'data:image/'.$__ext.';base64,'.base64_encode(file_get_contents($__lp));
        }
    } catch (\Throwable) {
        $__logo = null;
    }
@endphp
<style>
    @page { margin: 10mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
    h1 { font-size: 14px; margin: 0; text-align: center; }
    h2 { font-size: 12px; margin: 2px 0; text-align: center; }
    .tempoh { text-align: center; margin-bottom: 12px; font-size: 10px; }
    .kepala-masjid { text-align: center; margin-bottom: 6px; }
    .kepala-masjid img { max-height: 56px; }
    .kepala-alamat { text-align: center; font-size: 9px; color: #333; margin-bottom: 6px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #444; padding: 3px 5px; }
    th { background: #eee; }
    .end { text-align: right; }
    .seksyen { background: #ddd; font-weight: bold; }
    .jumlah { font-weight: bold; background: #f5f5f5; }
    .merah { color: #c00; }
</style>
@if ($__logo)
    <div class="kepala-masjid"><img src="{{ $__logo }}" alt="logo"></div>
@endif
<h1>{{ $namaMasjid }}</h1>
@if (($masjidSemasa ?? null)?->alamatPenuh())
    <div class="kepala-alamat">
        {{ $masjidSemasa->alamatPenuh() }}@if ($masjidSemasa->telefon) &middot; {{ __('No. Tel:') }} {{ $masjidSemasa->telefon }}@endif
    </div>
@endif
<h2>{{ $tajukLaporan }}</h2>
<div class="tempoh">{{ $tempoh }}</div>
