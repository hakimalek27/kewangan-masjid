<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('tajuk', __('Cetak')) — {{ $masjidSemasa?->nama ?? config('app.name') }}</title>
    @vite(['resources/css/app.css'])
    <style>
        /* ===== Saiz A4 tepat — margin pencetak ditiadakan, ruang dalam dikawal padding ===== */
        @page { size: A4 portrait; margin: 0; }
        html, body { margin: 0; padding: 0; background: #fff; }

        .kertas-a4 {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #fff;
            box-sizing: border-box;
        }
        /* 1 dokumen / A4 */
        .resit-penuh { padding: 14mm 15mm; box-sizing: border-box; }
        /* 2 resit / A4 — setiap separuh TEPAT 148mm (jumlah 296mm, elak limpah ke
           muka surat kedua); kedua-dua serupa supaya atas & bawah sama saiz */
        .resit-separuh { height: 148mm; padding: 11mm 15mm; box-sizing: border-box; overflow: hidden; }
        .resit-separuh + .resit-separuh { border-top: 1px dashed #888; }
        .koyak-nota { font-size: 9px; color: #888; text-align: center; margin: 2px 0 0; }

        /* ===== Tipografi dokumen ===== */
        .doc { font-family: Arial, "Helvetica Neue", sans-serif; font-size: 12px; color: #000; line-height: 1.4; }
        .doc .tanpa-garis, .doc .tanpa-garis td { border: none !important; vertical-align: top; }
        .doc table.butiran { width: 100%; border-collapse: collapse; }
        .doc table.items { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .doc table.items th, .doc table.items td { border: 1px solid #000; padding: 5px 8px; }
        .doc table.items thead th { background: #eee; }
        .doc .garis-isi { display: inline-block; border-bottom: 1px solid #000; min-width: 210px; }
        .doc .tajuk-dok { font-weight: bold; font-size: 14px; }
        .doc .nama-masjid { font-weight: bold; font-size: 15px; }
        .doc hr.pemisah { border: none; border-top: 1.5px solid #000; margin: 5px 0 10px; }
        .doc .ttd-line { border-top: 1px solid #000; padding-top: 3px; }
        .doc .disclaimer { text-align: center; font-weight: bold; letter-spacing: .3px; }

        /* ===== Bar alat (skrin sahaja) ===== */
        .cetak-toolbar { position: sticky; top: 0; z-index: 10; background: #1d3557; color: #fff;
            padding: 8px 14px; display: flex; gap: 8px; align-items: center; }
        .cetak-toolbar .tajuk { font-weight: 600; margin-right: auto; }

        @media screen {
            body { background: #e9ecef; }
            .kertas-a4 { box-shadow: 0 0 10px rgba(0,0,0,.25); margin: 16px auto; }
        }
        @media print {
            .cetak-toolbar, .no-print { display: none !important; }
            .kertas-a4 { width: auto; margin: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="cetak-toolbar no-print">
        <span class="tajuk">@yield('tajuk', __('Cetak'))</span>
        <button type="button" class="btn btn-sm btn-light" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>{{ __('Cetak') }}
        </button>
        @yield('toolbar-tambahan')
        <button type="button" class="btn btn-sm btn-outline-light" onclick="window.close()">{{ __('Tutup') }}</button>
    </div>

    @yield('kertas')

    <script>
        @if (request()->boolean('auto'))
            window.addEventListener('load', function () { window.print(); });
        @endif
    </script>
</body>
</html>
