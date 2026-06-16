{{-- CSS cetak penyata — 1 muka A3 portrait. Inline (tanpa pergantungan build Vite). --}}
<style>
    @media print {
        @page { size: A3 portrait; margin: 10mm; }
        .sidebar, .topbar, footer, .btn, .no-print, .d-print-none { display: none !important; }
        main { padding: 0 !important; }
        body { background: #fff !important; }
        .card { border: 0 !important; box-shadow: none !important; }
        .card-header { background: #fff !important; border: 0 !important; }
        .penyata-cetak { break-inside: avoid; }
    }
</style>
