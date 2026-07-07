# SPKM — Sistem Pengurusan Kewangan Masjid

Sistem kewangan masjid multi-tenant dengan perakaunan **double-entry** penuh, jejak audit hash-chain, dan pembantu AI.

## Ciri utama

- **Perakaunan double-entry**: kutipan, perbelanjaan, aset & susut nilai, FD/pelaburan, PWR, jurnal, tutup tahun — semua melalui satu laluan tulis (`JournalService`, Σdebit = Σkredit dikuatkuasakan).
- **Multi-tenant (multi-masjid)**: isolasi data per-masjid melalui skop global `masjid_id` + middleware konteks (anti-IDOR).
- **Peranan**: admin (superadmin — akses penuh semua tenant), bendahari (maker), pengerusi (pelulus), pentadbir, setiausaha, juruaudit, pemerhati.
- **Laporan**: penyata bulanan/tahunan 2 lajur, imbangan duga, untung rugi, kunci kira-kira, lejer, statistik, laporan program.
- **AI & integrasi**: pipeline Telegram→AI→draf→sahkan; **Semak Penyata (AI)** — muat naik penyata bank, AI senaraikan transaksi & padankan dengan rekod (kunci pusat, kuota bulanan per-tenant); API awam `/v1`; backup Google Drive; dual-write ke sistem legasi (toggle).

## Keperluan & pemasangan

- PHP 8.3+, MariaDB/MySQL, Node.js (Vite), Composer.
- Salin `.env.example` → `.env`, isi `DB_DATABASE=spkm` dsb., kemudian:

```bash
composer install
npm install && npm run build
php artisan migrate
php artisan serve
php artisan queue:work --queue=default,ai,webhook,backup,sync
php artisan schedule:work
```

## Ujian

```bash
php artisan test          # PHPUnit — atas klon spkm_test (lihat phpunit.xml)
npx playwright test       # E2E — lihat tests-e2e/README.md
```

## Dokumen penting

- `SENARAI-SEMAK-GOLIVE.md` — senarai semak go-live (WAJIB sebelum produksi)
- `HANDOVER.md` — keadaan semasa + baki kerja
- `LAPORAN-AUDIT-MENYELURUH-20260702.md` — laporan audit menyeluruh

UI dalam **Bahasa Melayu** (kunci i18n = string BM; terjemahan EN dalam `lang/en.json`).
