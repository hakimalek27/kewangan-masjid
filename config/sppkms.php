<?php

/**
 * Konfigurasi SPAKM — Sistem Pengurusan & Audit Kewangan Masjid.
 * Struktur menu mereplikasi SPPKMS V2.0 (9 kumpulan, 56 halaman) 1-ke-1 —
 * rujuk SPESIFIKASI-BINA-SEMULA.md Bahagian 3.
 */
return [

    'masjid_id' => (int) env('SPPKMS_MASJID_ID', 49),

    'legacy_url' => env('SPPKMS_LEGACY_URL', 'https://spm.mesrasuci.com'),

    // Fasa 7 — laluan binari mysqldump untuk backup harian
    // (Windows/XAMPP cth: C:\Users\hakim\xampp\mysql\bin\mysqldump.exe)
    'mysqldump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),

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
                ['Dual-Write SPPKMS', 'admin.dualwrite'],
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
                ['Tukar Kata Laluan', 'tetapan.katalaluan'],
                ['Pengurusan Pengguna', 'tetapan.pengguna'],
            ],
        ],
    ],
];
