<?php

/**
 * Konfigurasi SPKM — Sistem Pengurusan Kewangan Masjid.
 * Struktur menu mereplikasi SPPKMS V2.0 (9 kumpulan, 56 halaman) 1-ke-1 —
 * rujuk SPESIFIKASI-BINA-SEMULA.md Bahagian 3.
 */
return [

    'masjid_id' => (int) env('SPKM_MASJID_ID', 49),

    'legacy_url' => env('SPPKMS_LEGACY_URL', 'https://spm.mesrasuci.com'),

    // Fasa 7 — laluan binari mysqldump untuk backup harian
    // (Windows/XAMPP cth: C:\Users\hakim\xampp\mysql\bin\mysqldump.exe)
    'mysqldump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),

    // Semak Penyata (AI) — folder bin Poppler (pdftoppm/pdfinfo) untuk pecah PDF
    // imbasan (scan) kepada imej setiap muka sebelum OCR. Kosong = guna PATH.
    // Windows winget: ...\WinGet\Packages\oschwartz10612.Poppler_*\poppler-*\Library\bin
    'poppler_bin' => env('POPPLER_BIN', 'C:\Users\hakim\AppData\Local\Microsoft\WinGet\Packages\oschwartz10612.Poppler_Microsoft.Winget.Source_8wekyb3d8bbwe\poppler-25.07.0\Library\bin'),
    // DPI render muka PDF (150 = seimbang kejelasan vs saiz); pages per panggilan AI.
    'penyata_dpi' => (int) env('SPKM_PENYATA_DPI', 150),
    'penyata_pages_per_call' => (int) env('SPKM_PENYATA_PAGES_PER_CALL', 1),
    // PDF DIGITAL (teks terbenam) — muka teks per panggilan AI (teks kecil → boleh
    // gabung banyak muka satu panggilan; jauh lebih pantas daripada OCR imej).
    'penyata_text_pages_per_call' => (int) env('SPKM_PENYATA_TEXT_PAGES_PER_CALL', 8),
    // OCR IMBASAN SELARI — bilangan muka diproses serentak bila superadmin hidupkan
    // toggle 'sp_ocr_selari' (potong ~10min → ~2-3min utk scan 70+ muka).
    'ocr_selari_bil' => (int) env('SPKM_OCR_SELARI_BIL', 5),
    // Had kos lalai USD setiap permintaan scan (pemutus keselamatan token) — superadmin
    // boleh ubah di Tetapan AI (0 = tiada had). Elak fail rosak/banyak muka makan token.
    'penyata_had_usd_lalai' => (float) env('SPKM_PENYATA_HAD_USD', 2.0),
    // Kadar KOS pukul-rata USD/1000 token — HANYA fallback terakhir bila provider tiada
    // kadar input/output & tiada kadar global. Anggaran kasar (gpt-4o campur) supaya kos
    // tidak pernah kosong bila token digunakan. Kadar TEPAT = kadar provider (di atas).
    'penyata_kos_blended_lalai' => (float) env('SPKM_PENYATA_KOS_BLENDED', 0.006),

    // Siri penomboran (jadual number_sequence) — kaunter berasingan setiap siri
    'siri' => ['RESIT', 'PV', 'PWR', 'JNL', 'VKUTIPAN'],

    // COA tetap yang dirujuk logik sistem
    'coa' => [
        'dana_terkumpul'  => '100-10000',
        'tunai_di_tangan' => '250-06000',
        'akaun_sementara' => '300-99990',
        'susut_nilai'     => '650-10000',
    ],

    /*
     | Katalog provider AI untuk "Semak Penyata (AI)" — dropdown di /admin/semak-penyata.
     | base_url TANPA '/v1/chat/completions' (OpenAiDialect tambah sendiri). Semua
     | serasi-OpenAI. Pilih provider → auto-isi base_url + senarai model. 'custom'
     | membenarkan taip URL & model sendiri. Model MESTI sokong vision untuk OCR.
     */
    'ai_provider_catalog' => [
        ['key' => 'openai', 'label' => 'OpenAI', 'base_url' => '',
            'models' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4.1', 'gpt-4.1-mini'], 'pdf' => true],
        ['key' => 'openrouter', 'label' => 'OpenRouter', 'base_url' => 'https://openrouter.ai/api',
            'models' => ['openai/gpt-4o', 'openai/gpt-4o-mini', 'google/gemini-2.0-flash-001',
                'anthropic/claude-3.5-sonnet', 'qwen/qwen-2-vl-72b-instruct', 'meta-llama/llama-3.2-90b-vision-instruct'], 'pdf' => false],
        ['key' => 'deepseek', 'label' => 'DeepSeek (teks — bukan OCR)', 'base_url' => 'https://api.deepseek.com',
            'models' => ['deepseek-chat'], 'pdf' => false],
        ['key' => 'ollama', 'label' => 'Ollama (local)', 'base_url' => 'http://localhost:11434',
            'models' => ['llama3.2-vision', 'llava', 'llava:13b', 'minicpm-v', 'moondream'], 'pdf' => false],
        ['key' => 'groq', 'label' => 'Groq', 'base_url' => 'https://api.groq.com/openai',
            'models' => ['llama-3.2-90b-vision-preview', 'llama-3.2-11b-vision-preview'], 'pdf' => false],
        ['key' => 'mistral', 'label' => 'Mistral', 'base_url' => 'https://api.mistral.ai',
            'models' => ['pixtral-large-latest', 'pixtral-12b-2409'], 'pdf' => false],
        ['key' => 'custom', 'label' => 'Custom (taip URL & model sendiri)', 'base_url' => '',
            'models' => [], 'pdf' => false],
    ],

    /*
     | Harga rasmi model AI — USD per 1000 token [input, output]. Digunakan untuk
     | auto-isi kadar di borang provider (superadmin boleh override). Sumber: laman
     | rasmi provider (Jul 2026) — SEMAK SEMULA berkala kerana harga boleh berubah.
     |  - gpt-4o        $2.50/$10 per 1M   → 0.0025 / 0.01
     |  - gpt-4o-mini   $0.15/$0.60 per 1M → 0.00015 / 0.0006
     |  - gpt-4.1       $5/$15 per 1M      → 0.005 / 0.015
     |  - gpt-4.1-mini  $0.40/$1.60 per 1M → 0.0004 / 0.0016
     |  - deepseek V4 Flash (deepseek-chat) $0.14/$0.28 per 1M → 0.00014 / 0.00028
     */
    'ai_harga_model' => [
        'gpt-4o' => [0.0025, 0.01],
        'openai/gpt-4o' => [0.0025, 0.01],
        'gpt-4o-mini' => [0.00015, 0.0006],
        'openai/gpt-4o-mini' => [0.00015, 0.0006],
        'gpt-4.1' => [0.005, 0.015],
        'gpt-4.1-mini' => [0.0004, 0.0016],
        'deepseek-chat' => [0.00014, 0.00028],
        'deepseek-v4-flash' => [0.00014, 0.00028],
    ],

    /*
     | Menu sidebar. Setiap item: [label, nama route].
     | Route yang belum dibina (fasa akan datang) dipaparkan tetapi menuju
     | halaman "dalam pembinaan" — dibuang apabila fasa siap.
     */
    'menu' => [
        [
            'label' => 'Dashboard',
            'icon'  => 'bi-speedometer2',
            'items' => [['Dashboard', 'dashboard']],
        ],
        [
            'label' => 'AI & Integrasi',
            'icon'  => 'bi-robot',
            'items' => [
                ['Kotak Draf AI', 'draf.index'],
                ['Tetapan AI & Telegram', 'tetapan.ai'],
                ['API Awam', 'tetapan.api'],
            ],
        ],
        [
            'label' => 'Pentadbiran',
            'icon'  => 'bi-shield-lock',
            'items' => [
                ['Pemantauan Sistem', 'admin.pemantauan'],
                ['Jejak Audit', 'admin.audit'],
                ['Log Ralat', 'admin.ralat'],
                ['Keselamatan', 'admin.keselamatan'],
                ['Backup Luar Tapak', 'admin.backup'],
                ['Semak Penyata (AI) — Kawalan', 'admin.semakpenyata'],
            ],
        ],
        [
            'label' => 'Bank & PWR',
            'icon'  => 'bi-bank',
            'items' => [
                ['Setting Bank', 'bank.index'],
                ['Set Baki Awal', 'bank.opening'],
                ['Baki Terkini', 'bank.baki'],
                ['Daftar Buku Cek', 'cek.daftar'],
                ['Laporan Buku Cek', 'cek.senarai'],
                ['Daftar Cek Batal', 'cekbatal.daftar'],
                ['Laporan Cek Batal', 'cekbatal.senarai'],
            ],
        ],
        [
            'label' => 'Penerimaan / Kutipan',
            'icon'  => 'bi-cash-coin',
            'items' => [
                ['Kutipan Baru', 'kutipan.baru'],
                ['Kutipan Tabung', 'kutipan.tabung'],
                ['Terimaan Dividen', 'kutipan.dividen'],
                ['Senarai Kutipan', 'kutipan.senarai'],
                ['Kutipan Jumaat', 'kutipan.jumaat'],
                ['Kutipan Harian', 'kutipan.harian'],
            ],
        ],
        [
            'label' => 'Perbelanjaan',
            'icon'  => 'bi-credit-card',
            'items' => [
                ['Menu Perbelanjaan', 'belanja.menu'],
                ['Perbelanjaan Baru', 'belanja.baru'],
                ['Perbelanjaan Aset', 'belanja.aset'],
                ['Perbelanjaan Bukan Tunai', 'belanja.jurnal'],
                ['Rekupmen PWR', 'belanja.rekupmen'],
                ['Buku Tunai Pembayaran', 'belanja.senarai'],
            ],
        ],
        [
            'label' => 'PWR',
            'icon'  => 'bi-wallet2',
            'items' => [
                ['Baki Di Tangan', 'pwr.baki'],
                ['Pembayaran PWR', 'pwr.bayar'],
                ['Buku Tunai PWR', 'pwr.buku'],
                ['Penyata PWR', 'pwr.penyata'],
            ],
        ],
        [
            'label' => 'Aset / Pelaburan / Sewa',
            'icon'  => 'bi-building',
            'items' => [
                ['Daftar Aset Lama', 'aset.daftarlama'],
                ['Senarai Aset', 'aset.senarai'],
                ['Daftar Pelaburan Lama', 'fd.daftarlama'],
                ['Daftar Pelaburan Baru', 'fd.baru'],
                ['Senarai Pelaburan Terdahulu', 'fd.senarailama'],
                ['Senarai Pelaburan Terkini', 'fd.senarai'],
                ['Daftar Peti Besi', 'petibesi.daftar'],
                ['Senarai Peti Besi', 'petibesi.senarai'],
                ['Daftar Sewa', 'sewa.daftar'],
                ['Senarai Sewaan', 'sewa.senarai'],
            ],
        ],
        [
            'label' => 'Penyata',
            'icon'  => 'bi-file-earmark-text',
            'items' => [
                ['Setting Penyata', 'penyata.setting'],
                ['Penyata Bulanan', 'penyata.bulanan'],
                ['Penyata Bulanan Ikut Bank', 'penyata.bank'],
                ['Penyata Tahunan', 'penyata.tahunan'],
            ],
        ],
        [
            'label' => 'Statistik',
            'icon'  => 'bi-bar-chart',
            'items' => [
                ['Statistik Kutipan Tahunan', 'statistik.kutipan'],
                ['Statistik Kutipan Bulanan', 'statistik.kutipan_coa'],
                ['Statistik Perbelanjaan Tahunan', 'statistik.belanja'],
                ['Statistik Perbelanjaan Bulanan', 'statistik.belanja_coa'],
                ['Statistik Kutipan Jumaat', 'statistik.jumaat'],
            ],
        ],
        [
            'label' => 'Penyata Perakaunan',
            'icon'  => 'bi-journal-bookmark',
            'items' => [
                ['Carta Akaun', 'akaun.coa'],
                ['Buku Jurnal', 'akaun.jurnal'],
                ['Laporan Jurnal', 'akaun.laporanjurnal'],
                ['Lejer Am', 'akaun.lejer'],
                ['Lejer Mengikut Akaun', 'akaun.lejerakaun'],
                ['Imbangan Duga', 'akaun.imbangan'],
                ['Untung Rugi', 'akaun.untungrugi'],
                ['Kunci Kira-Kira', 'akaun.kunci'],
                ['Laporan Program', 'akaun.program'],
            ],
        ],
        [
            // Fasa 9 — Perakaunan Lanjutan (selepas Penyata Perakaunan)
            'label' => 'Lanjutan',
            'icon'  => 'bi-stars',
            'items' => [
                ['Belanjawan', 'belanjawan.index'],
                ['Dana & Tabung', 'dana.index'],
                ['Rekonsiliasi Bank', 'rekonsiliasi.index'],
                ['Semak Penyata (AI)', 'semakpenyata.index'],
                ['Susut Nilai', 'susutnilai.index'],
                ['Kelulusan', 'kelulusan.index'],
                ['Tutup Tahun', 'tutuptahun.index'],
                ['Kawalan', 'kawalan.index'],
            ],
        ],
        [
            'label' => 'Tetapan',
            'icon'  => 'bi-gear',
            'items' => [
                ['Wizard Setup', 'tetapan.wizard'],
                ['Set Resit/Baucer', 'tetapan.resit'],
                ['Set Kod Penerimaan/Perbelanjaan', 'tetapan.mapping'],
                ['Carian Padanan Kod Akaun', 'tetapan.semak'],
                ['Info Masjid', 'tetapan.masjid'],
                ['Dual-Write SPPKMS', 'tetapan.dualwrite'],
                ['Tukar Kata Laluan', 'tetapan.katalaluan'],
                ['Pengurusan Pengguna', 'tetapan.pengguna'],
            ],
        ],
    ],
];
