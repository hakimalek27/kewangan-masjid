# LAPORAN UJIAN AKHIR — SPAKM (Sistem Pengurusan & Audit Kewangan Masjid)
**Tarikh:** 12–13 Jun 2026 · **Projek:** `sppkms-v2` (Laravel 13 + MariaDB) · **Status: ✅ SEMUA LULUS**

## 1. RINGKASAN KEPUTUSAN
| Suite | Keputusan |
|---|---|
| **Ujian automatik penuh** | **179/179 LULUS (924 assertion)** — `php artisan test` |
| Integriti double-entry (DB sebenar) | OK — 3,779 voucher, Σdebit = Σkredit semuanya |
| Rantai audit hash-chain | OK — utuh |
| Binaan aset frontend (Vite) | OK |
| Pemasangan segar (install.sql + data sejarah) | **TALLY OK** (disahkan berasingan) |

## 1b. AUDIT MENYELURUH 2-PUSINGAN (multi-ejen + pengesahan adversarial)
Audit berbilang-ejen merentas 9+7 dimensi (kod teras, transaksi, laporan, keselamatan, web,
spec-56-halaman, konformans-plan, integriti-data) dengan setiap penemuan disahkan oleh
2 pengesah adversarial. **17 isu disahkan, SEMUA dibaiki; 3 false-positive ditolak.**
**Tiada satu pun menyentuh ketepatan jurnal/double-entry/tally** — semua di lapisan
integrasi/operasi/UI. Teras perakaunan kekal bersih.
- **Pusingan 1 (9 dibaiki):** webhook kini dicetus dari observer (web+API+draf, bukan API
  sahaja) + event draft/report; retry AI berfungsi (AI_FAILED→failed(), Telegram diasingkan);
  retensi backup (job PruneBackups + jadual); dual-write buang `semak`, betulkan kunci `bank`,
  exception pasca-POST elak pendua; timezone Asia/Kuala_Lumpur; `$timeout` setiap job + retry_after;
  gzip dump; folder Drive hierarki + deleteFile.
- **Pusingan 2 (8 dibaiki):** lampiran perbelanjaan kini PRIVATE + endpoint berpagar-auth +
  mimes (baiki pendedahan PII/stored-XSS); Penyata Ikut Bank kini tapis SEBENAR per akaun
  bank; tukar kata laluan dibenarkan semua peranan (self-service); FD edit; Aset lihat;
  eksport PDF/Excel Carta Akaun+Jurnal+Lejer; Wizard Setup; susut nilai bulanan berjadual;
  pautan Panduan/WhatsApp + kad Pengumuman dashboard.
- **False-positive ditolak (disahkan):** bayaran NON_CASH "kredit bank" (replikasi setia
  SPPKMS asal); lejer tarikh vs period_ym (reka bentuk sengaja, setia V1); 102 entri ke
  COA header (artifak data migrasi V1 — `JournalService` BARU memang menolak COA header).
- **Baki (penambahbaikan pilihan) — KINI SIAP (13 Jun):**
  - **Notifikasi baki rendah bank:** tetapan ambang `baki_rendah_ambang` di Kawalan Dalaman +
    `BakiRendahService` + jadual harian 08:00 (ErrorLog INFO sekali sehari + Telegram) + item loceng topbar.
  - **Amaran defisit dana pada borang bayaran:** endpoint `/dana/semak` + komponen `x-fund-warning`
    masa-nyata pada borang Perbelanjaan (hormati `fund_deficit_alert`; dikesan utk dana 300-04xxx).
  - **Multi-bahasa BM/EN penuh:** ~95 blade UI dibalut `__()` (kekuali pdf/ — penyata rasmi kekal BM);
    lang/en.json kini **1,057 kunci** (0 kunci UI tertinggal terjemahan); suis BM|EN topbar.
  - **Ujian:** +11 (BakiRendahDanaTest 8, LocaleTest 3). **Jumlah 164/164 LULUS (886 assertion).**

## 1e. SKOP "100%" YANG JUJUR + PEMULIHAN MEDAN KEBOLEHKESANAN (15 Jun)
**Penjelasan jujur skop "100%":** dakwaan "tally 100% + audit utuh" merujuk **KETEPATAN ANGKA KEWANGAN**
sahaja — `verify-balance` (Σdebit=Σkredit setiap voucher), `verify-audit-chain` (hash-chain log), dan
`TallySejarahTest` (penyata bulanan/KKK/imbangan vs V1 ke sen). Ia **TIDAK** merangkumi kelengkapan medan
deskriptif/kebolehkesanan (nama pemberi, saksi, tarikh bank-in). Jurang itu nyata — kini ditutup.
- **Label kaedah dipulih (GL TAK disentuh):** kutipan 394 TUNAI + 6 CEK; pembayaran 421 CEK + 6 no_cek
  (dari jadual v1_*). Disahkan TIADA laporan guna `kaedah` utk amaun (StatementService ikut coa_id; cara_bayar
  cuma pisah PWR — EFT↔CEK tak terkesan). Angka report SEBELUM=SELEPAS identik; GL Dr Bank kekal (setia V1).
- **Medan sumber dipulih dari V1** (lihatInfo.php; V2 tiada data sejarah): **nama_pemberi 2314/2331 (99%),
  tar_bankin 100%, saksi1 317, dibank_oleh 191** — backfill berpagar (no_resit+jumlah, isi-kosong-sahaja,
  deskriptif → GL/tally tak terjejas). Backup: `spm-explore/shots/pra-backfill-detail-20260615.sql`.
- **Insiden dikendali:** satu ejen siasatan tersilap cipta "Baki Awal 2026" palsu (RM164,602.24) → dikesan
  & dibuang tepat → **Aset KKK pulih 183,155.95**, 3777 voucher seimbang, audit 7 baris.
- **Command advisory baharu** `sppkms:verify-completeness` (pantau kelengkapan; medan kekal optional).
- **181/181 ujian LULUS (935 assertion).** Status kelengkapan: nama_pemberi 99%, tar_bankin 100%, pemohon 83%.

## 1d. FASA D — DETAIL MASJID GLOBAL + OUTPUT RESIT/BAUCER FORMAT A4 (13 Jun)
- **Detail masjid keluar di SEMUA tempat (tiada placeholder):** `Masjid::semasa()` (memo per-permintaan, null-safe) + `logoUrl()`/`alamatPenuh()`; `View::composer('*')` kongsi `$masjidSemasa`. Logo+nama+alamat+tel di dashboard, logo+nama di sidebar, logo base64 + alamat di kepala PDF penyata. Memo dibatal selepas kemaskini Info Masjid / muat naik logo.
- **Output resit kutipan & baucer bayaran format A4** (ikut imej rujukan): layout cetak khusus `layouts/cetak.blade.php` (@page A4 margin 0 — ngam2 atas A4), partial `cetak/_kepala`+`_resit`+`_baucer`, terbilang `App\Support\Terbilang` ("RINGGIT MALAYSIA … SAHAJA"). Mod **dipilih masa cetak** (?mod=SIGNATURE = blok tandatangan / DISCLAIMER = baris cetakan berkomputer); lalai PenyataSetting (SIGNATURE krn jadual kosong). Baucer SIGNATURE = 3 tandatangan Disediakan/Disahkan/Diterima (BENDAHARI/PENGERUSI/PENERIMA).
- **Resit 2 salinan / 1 A4:** pilihan 1 resit/A4 ATAU 2 resit serupa atas-bawah (setiap separuh 148mm = sama tepat, koyak garis tengah; label Salinan Pembayar/Bendahari). View detail KEKAL butiran audit + pemilih mod + butang cetak.
- **Ujian baharu:** +10 `TerbilangTest` (unit), +5 `CetakResitBaucerTest` (feature). **Jumlah 179/179 LULUS (924 assertion).** view:cache bersih. Tiada teras perakaunan/tally disentuh.

## 1c. VERIFIKASI DUAL-WRITE HIDUP (13 Jun) + PEMBERSIHAN DB
- **Rekod ujian manual id 2335 DIBUANG** dari DB produksi `sppkms` (3779→3777 voucher) atas
  kebenaran pengguna; verify-balance/chain + tally kekal OK (BS 183,155.95 SEIMBANG).
- **Dual-write disahkan HIDUP terhadap SPPKMS produksi** (login + 3 rekod RM1 → padam semula;
  baseline pulih; SPPKMS sahkan bersih 0 sisa). Verifikasi mendedahkan & MEMBETULKAN 3 isu
  sebenar dalam `SppkmsDualWriteService` (yang ujian-mock TIDAK dapat tangkap):
  1. **kutipan `bank`** = nombor SLOT ("1") bukan label "Slot 1 : AFFIN MAX" (rakaman POST sebenar).
  2. **belanja** perlu medan `hantar=''` (nama butang submit; PHP semak `isset`) + `is_auto_active=0` —
     tanpa ini borang render semula & TIADA rekod tercipta (didedah oleh ujian hidup).
  3. **`ekstrakRecno`→`tafsirRespons`**: parsing recno rapuh (tertangkap `paparMasjid.php?recno=49`
     masjid_id) → kini Location-first (`recno=`/`id=`), penanda kejayaan SPPKMS ("Berjaya…Disimpan"
     untuk rekupmen yang pos-ke-diri), gagal → SppkmsPostSentException (elak pendua).
  - Hasil akhir: kutipan/belanja/rekupmen ketiga-tiga **DONE** (kutipan & belanja dengan recno
    sebenar; rekupmen DONE via penanda kejayaan). Config `dualwrite.verify` (SSL CA, lalai true).
  - Skrip verifikasi (kekal sebagai rekod): `spm-explore/70-75-*.mjs`.

## 2. TALLY DATA SEJARAH (paling kritikal — ke sen)
Dibandingkan dengan angka yang disahkan tally 100% lawan SPPKMS/V1 (`spm-explore/shots/*`):
- ✅ **Penyata Pendapatan 30/30 bulan** (2024-01 → 2026-06) — pendapatan, perbelanjaan & lebihan setiap bulan PADAN KE SEN.
- ✅ **Ringkasan tahunan** 2024/2025/2026 padan (955,006.47 / 919,444.89 / 432,291.17 …).
- ✅ **Kunci Kira-Kira @ Jun 2026** — setiap baris padan (Bank 173,758.17; PWR 1,953.78; Rahmah Madani −23,850.00; Akaun Sementara 10,140.00; Dana Terkumpul 125,393.52; Lebihan Terkumpul 70,327.43; Total Aset 183,155.95) + **SEIMBANG ✔**.
- ✅ **Imbangan Duga** Dr = Cr.
- ✅ **Rekonsiliasi tunai tahunan** (Terima/Bayar buku tunai) padan 3 tahun.
- ✅ **Laporan Program** — 82 program; IHYA RAMADAN +163,187.39, QURBAN +50,901.73, BOWLING −3,077.20, KELAS TALAQQI −38,093.60 padan.
- ✅ **Penyata bulanan 2 lajur seimbang** untuk kesemua 30 bulan (kiri = kanan).

## 3. REPLIKASI UJIAN EMPIRIK (matriks Dr/Cr — 12 ujian, 195 assertion)
Setiap jenis: baseline → cipta RM1.00 → delta TEPAT ikut `LAPORAN-IMPAK-EMPIRIK.md` → VOID → **residual KOSONG**:
Kutipan bank · Kutipan tunai · Kutipan tabung (denominasi auto-jumlah) · Bayaran EFT · Bayaran PWR · Beli aset (+daftar aset & SNT auto; VOID cascade — quirk lama dibaiki) · Belanja jurnal susut nilai · Rekupmen (pemindahan, BUKAN P&L) · FD baru & matang · Dividen FD (padam FD tak sentuh dividen — tingkah laku asal dikekal) · Kaunter auto/manual.

## 4. UJIAN FUNGSI & INTEGRASI
- ✅ Smoke SEMUA route GET bernama (≈90 halaman) — 200 OK sebagai admin.
- ✅ Borang web hujung-ke-hujung (kutipan/belanja/rekupmen/padam) + validasi medan wajib + peranan (viewer disekat menulis, 403).
- ✅ Pipeline AI: webhook Telegram (secret+allowlist) → muat turun → SHA-256 anti-pendua → ekstrak (3 dialect, mock) → draf PENDING_REVIEW → sahkan → jurnal + lampiran. **AI tidak pernah pos terus.** Kunci API tersulit dalam vault (`sec_*`).
- ✅ API awam /v1: token, scope, **idempotency** (ulangan key = tiada rekod kedua), rate limit 429, jurnal seimbang dalam respons, void, balance-sheet via API = 183155.95, format ralat ikut spec.
- ✅ Backup: queue per-transaksi (observer afterCommit), penyulitan + checksum, uji-pulih, dump mysqldump sebenar, log offsite; halaman pemantauan admin; **bug hash-chain (double-encode) ditemui & dibaiki** semasa fasa 7.
- ✅ Dual-write: toggle on/off, medan borang lama tepat (`semak='off'` — kaunter SPPKMS tak diganggu), idempoten, ASET→SKIPPED, gagal→retry+alert. (Ujian dengan HTTP mock — TIADA panggilan sebenar ke produksi.)
- ✅ Lanjutan: susut nilai bulanan idempoten, pelupusan aset seimbang, **tutup tahun (period 'YYYY-13') TIDAK mengubah laporan sejarah** (dibuktikan), belanjawan + amaran, maker-checker (melebihi ambang → kelulusan dahulu, tiada jurnal sehingga lulus), rekonsiliasi bank (auto-match ±3 hari), amaran dana defisit (Rahmah Madani −23,850 dikesan).

## 5. PEMASANGAN SEGAR
DB baharu daripada `install.sql` → migration rangka kerja → muat `shots/v1-migrated-data.sql` → laporan tally serta-merta (BS 183,155.95 SEIMBANG; P&L 2024 padan). **Nota pembaikan pakej:** `v1-migrated-data.sql` dijana semula (versi lama tiada kolum `period_ym`); `install.sql` & `schema-sppkms.sql` dibetulkan (`250-06000` kini boleh-pos, selaras sistem asal).

## 6. ISU BAKI / NOTA OPERASI
1. **Item di-remark pengguna (belum tindakan, ikut arahan):** Akaun Sementara RM10,140 (jelaskan selepas guna); Rahmah Madani −23,850 (terimaan 2023 belum direkod); orphan aset id 111 di SPPKMS produksi (padam via admin/DB).
2. **Dual-write ke SPPKMS sebenar belum diuji secara langsung** (hanya mock) — ujian 1 rekod RM1 sebenar memerlukan kebenaran anda & kredensial dalam tetapan.
3. **Perlu konfigurasi pengguna untuk go-live:** API key AI provider (Tetapan AI), token bot Telegram + `TELEGRAM_WEBHOOK_SECRET` + setWebhook, service account Google Drive, kredensial SPPKMS (jika dual-write). Queue worker: `php artisan queue:work --queue=ai,webhook,backup,sync` + `php artisan schedule:work`.
4. Login lalai: `admin/admin12345`, `malmutaqqin/alm12345` — **tukar selepas log masuk pertama**.

## 7. CARA JALANKAN
```
cd "C:\Projek Coding\Sistem Kewangan Masjid\sppkms-v2"
php artisan serve          # http://localhost:8000
php artisan queue:work --queue=ai,webhook,backup,sync   # terminal kedua
php artisan schedule:work  # terminal ketiga (penjadual)
php artisan test           # 130 ujian
php artisan sppkms:verify-balance ; php artisan sppkms:verify-audit-chain
```
