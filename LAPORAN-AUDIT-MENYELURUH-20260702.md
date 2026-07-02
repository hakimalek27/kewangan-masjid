# LAPORAN AUDIT MENYELURUH END-TO-END — SPAKM (sppkms-v2)
**Tarikh:** 2 Julai 2026 · **Kaedah:** 6 ejen selari (setiap sektor) + pengesahan langsung kod/DB oleh penyelaras · **Baseline:** 247/247 ujian LULUS; DB prod `sppkms` = 3,776 voucher POSTED + 2 VOID, Σdebit=Σkredit=RM4,716,998.65.

> Skop: teras perakaunan, pengasingan multi-penyewa, peranan/kebenaran, keselamatan aplikasi, API/AI/backup/dual-write, laporan & tally. Setiap penemuan disahkan dengan membaca kod dan/atau query DB sebelum dilaporkan.

---

## VERDIK RINGKAS
Sistem **kukuh pada teras**: double-entry mustahil tak seimbang (satu-satunya pintu tulis = JournalService + VoidService, kedua-dua guna bccomp + tolak COA header/tak-aktif + tolak silang-penyewa), wang disimpan DECIMAL(15,2), pengasingan multi-masjid rapat (tiada IDOR hidup), muat naik fail/rahsia/XSS/SQLi semua bersih. **Tiada isu yang menjejaskan ketepatan angka kewangan sedia ada.**

Isu sebenar terletak pada **(a) 4 pepijat kod** (2 kelihatan sekarang, 2 laten selepas tutup-tahun/beban serentak), **(b) go-live config + kredensial lalai**, dan **(c) beberapa keputusan tata-kelola (SoD)** untuk pengguna putuskan.

---

## A. GO-LIVE BLOCKER (config & kredensial — WAJIB sebelum guna sebenar)

| # | Isu | Bukti | Tindakan |
|---|-----|-------|----------|
| A1 | `APP_DEBUG=true`, `APP_ENV=local`, `LOG_LEVEL=debug` | `.env:2,5` | Produksi: `APP_DEBUG=false`, `APP_ENV=production`, `LOG_LEVEL=warning`. Digabung dengan render JSON `/v1/*` (`bootstrap/app.php:90`) → stack trace bocor ke klien API. |
| A2 | Kata laluan lalai **masih sah & aktif** dalam DB prod | Disahkan `password_verify`: `admin/admin12345`=YA, `malmutaqqin/alm12345`=YA | Paksa tukar sebelum go-live. |
| A3 | `SESSION_SECURE_COOKIE=false` | `.env:33` | `true` di bawah HTTPS. |
| A4 | `TELEGRAM_WEBHOOK_SECRET` kosong | `.env` | Isi sebelum aktif Telegram (kini fail-closed 403 — selamat tetapi ciri mati). |
| A5 | Residu ujian dalam DB prod: masjid 50 "test" (129 COA benih + user id 3 + 3 baris audit, **0 transaksi**) | `SELECT ... masjid_id=50` | Buang masjid 50 + user `test`. Ini juga punca "rantai audit putus" yang dilihat verify-audit-chain (lihat B1). |

---

## B. PEPIJAT KOD DISAHKAN

### B1. [TINGGI] Rantai hash audit PUTUS pada setiap tulisan audit silang-masjid — DISAHKAN (repro)
**Fail:** `app/Services/Security/AuditTrailService.php:31-35`
**Punca:** query `prev_hash` masih membawa **skop global `BelongsToMasjid`** (`WHERE masjid_id = <masjid SESI>`) DAN menambah `when($masjidId, ...)` (`WHERE masjid_id = <masjid sasaran>`). Bila kedua-dua berbeza (cth admin sesi masjid 49 meng-onboard masjid 50), jadi `WHERE masjid_id=49 AND masjid_id=50` → 0 baris → `prev_hash=NULL` bagi **setiap** panggilan → rantai putus.
**Repro:** ikat `current.masjid_id=49`, log ke masjid 50 → ketiga-tiga baris `prev=NULL` (disahkan pada sppkms_test). Repro konsol tanpa sesi TIDAK mencetuskannya (itu sebab semakan awal tersilap "residu sahaja").
**Kesan:** Setiap onboarding masjid / tindakan audit admin merentas masjid memutuskan rantai tamper-detection. Kelihatan sekarang pada masjid 50 (#29, #30).
**Fix:** `AuditTrail::withoutMasjidScope()` dalam query prev + `$masjidId !== null ? where(...) : whereNull('masjid_id')`.

### B2. [TINGGI] Penyata Bulanan **Ikut Bank** tak seimbang bila ada REKUPMEN — DISAHKAN (kod + data)
**Fail:** `app/Http/Controllers/Web/Penyata/PenyataController.php:54-56`
**Punca:** `pindahan` (rekupmen PWR) ditambah pada **kedua-dua** `jumlahKiri` dan `jumlahKanan`. Betul untuk penyata gabungan (rekupmen bank↔PWR neutral). Tapi untuk **satu bank**, rekupmen ialah aliran keluar bersih yang sudah diserap `baki_akhir` → menambahnya di kiri buat KIRI > KANAN sebanyak jumlah rekupmen.
**Bukti:** Bank 1 (250-05010) Jan 2024 → KIRI 204,602.97 vs KANAN 203,658.07, selisih **944.90 = jumlah rekupmen bulan itu**. **13 bulan** terjejas dalam data prod → banner merah "TIDAK SEIMBANG" kelihatan sekarang.
**Fix:** bila `$bank` diberi, jangan tambah pindahan di kiri — papar rekupmen sebagai aliran keluar di kanan sahaja.

### B3. [TINGGI — LATEN] `profitLoss` tak kecualikan period `YYYY-13`/`YYYY-00` untuk julat merentas tahun
**Fail:** `app/Services/Laporan/ReportService.php:59` (`whereBetween('jv.period_ym', [...])`)
**Punca:** `'2024-13'` (voucher penutupan YearEnd) berada ANTARA `'2024-01'` dan `'2025-12'` dalam perbandingan rentetan. Selepas tutup-tahun pertama, P&L julat merentas tahun akan menyerap voucher penutupan → hasil/belanja tahun tertutup lenyap dari laporan.
**Belum tercetus** (tiada voucher `-13` dalam DB), **tetapi pasti berlaku selepas penutupan tahun pertama.** TB/BS (guna `<=`) memang PATUT masuk `-00`/`-13` — itu betul; hanya P&L perlu dibaiki.
**Fix:** tambah `whereRaw("SUBSTR(jv.period_ym,6,2) BETWEEN '01' AND '12'")` dalam `profitLoss()`.

### B4. [TINGGI] Idempotency API ada TOCTOU race → tulisan kewangan berganda bawah retry serentak
**Fail:** `app/Http/Middleware/Api/ApiIdempotency.php:42-69`
**Punca:** semakan `Cache::get` (miss) + `ApiRequestLog` (miss, kerana log ditulis pada `terminate()` SELEPAS respons) → dua POST serentak dengan `Idempotency-Key` sama (kes retry-on-timeout) kedua-dua lulus → dua rekod. Dengan `receipt_no/voucher_no="auto"`, setiap satu dapat voucher_ref unik berbeza → dua resit/bayaran SAH berganda (UNIQUE tak menangkap).
**Fix:** `Cache::lock($cacheKey)` atau `Cache::add` sentinel atom SEBELUM `$next()`.

### B5. [TINGGI — LATEN, aktif bila masjid ke-2 wujud] Kebocoran baca silang-penyewa melalui tugasan `masjid_ids` pemerhati — DISAHKAN (kod)
**Fail:** `app/Http/Requests/Tetapan/PenggunaRequest.php:43` + `app/Http/Controllers/Web/Tetapan/PenggunaController.php:56,148-150`
**Punca:** `masjid_ids.*` divalidasi dengan `Rule::exists('masjid','id')` **tanpa skop** untuk SEMUA pelakon (termasuk bukan-admin). Pada laluan CREATE, `authorize()` pulang true (tiada `route('pengguna')`), dan `segerakTugasan` sync pivot `user_masjid` untuk VIEWER **tanpa hadkan ke masjid pelakon sendiri**. `masjid_id` utama dipaksa ke masjid semasa, tetapi pivot `masjid_ids` TIDAK.
**Eksploit:** Bendahari masjid A `POST /tetapan/pengguna` cipta viewer (kata laluan pilihan sendiri) dengan `masjid_ids[]=<B>`. `accessibleMasjidIds()` viewer = `[A] + pivot[B]` → viewer boleh tukar ke masjid B (`MasjidSwitchController`+`SetMasjidContext` benarkan) dan baca semua penyata/transaksi/laporan masjid B. Bendahari kawal kredensial → capai baca silang-penyewa.
**Terlindung sekarang** kerana hanya 1 masjid sebenar wujud (masjid 50 = residu ujian) — aktif sebaik masjid ke-2 di-onboard.
**Fix:** untuk bukan-admin, hadkan `masjid_ids.*` kepada `accessibleMasjidIds()` pelakon (atau paksa kosong).

---

## C. CONCURRENCY / EDGE-CASE (SEDERHANA)

| # | Fail | Isu | Fix |
|---|------|-----|-----|
| C1 | `AuditTrailService.php:31-35` | "Fork" rantai audit: dua log() serentak masjid sama boleh kongsi prev_hash → PUTUS palsu | Kunci baris induk per-masjid / kolum `seq` unik |
| C2 | `DepreciationService.php:127-129` | `accumulated_depn` lost-update (baca luar transaksi, tiada lock) → susut nilai boleh lebih kos aset | `increment('accumulated_depn', ...)` atom |
| C3 | `DepreciationService.php:157,183` | Pelupusan aset tiada lock/semak-semula dalam transaksi → klik dua kali = 2 voucher pelupusan | lock+semak status dalam `DB::transaction` (macam VoidService) |
| C4 | `YearEndService.php:92-105` | Tutup tahun kira baki **luar** transaksi + guna baki **kumulatif tanpa had bawah** → (a) TOCTOU dgn pos serentak; (b) tutup 2024 semasa 2023 belum tutup → gabung 2 tahun jadi satu entri penutupan, tak boleh dibetul (kunci period) | Dalam transaksi: kunci period → kira baki (berhad julat tahun) → pos; wajibkan tahun N-1 ditutup dahulu |
| C5 | `PerakaunanController.php:282-285`, `PenyataController.php:253`, `StatistikController.php:89` | Input `bln` web tak divalidasi → `?bln=13`/`?bln=0` hasilkan period `-13`/`-00` (masuk voucher penutupan selepas tutup-tahun). API selamat (`date_format:Y-m`) | `max(1,min(12,(int)$bln))` |
| C6 | `belanja/jurnal.blade.php:36` + `JurnalRequest.php:15` | Jurnal manual boleh Cr akaun tunai (250-05/06) tanpa baris pembayaran → penyata tunai jadi tak seimbang | Sekat julat COA tunai di JurnalRequest / amaran |
| C7 | `StatementService.php:168-180` (`ringkasanTunai`) + `DashboardService.php:21-28,42-46` | **Kelihatan sekarang:** kad Dashboard "Perbelanjaan" & trend gandakan REKUPMEN (bayar_bank sudah masuk REKUPMEN + bayar_pwr semua PWR). 2025: dashboard 899,569.50 vs penyata belanja 897,644.85, lebih **1,924.65 = jumlah REKUPMEN**. Dashboard ≠ Penyata | Kecualikan `jenis='REKUPMEN'` dalam ringkasanTunai/trendBulanan |
| C8 | Data: 102 entri POSTED ke COA header 600-12000/600-15000 (~RM76k) | Warisan migrasi V1 (tarikh 2024–2026; migrasi tulis terus SQL, pintas guard). Kod baru menolak. **Implikasi tutup-tahun:** `YearEndService` langkau `is_header` → baki ~RM76k ini **tak pernah ditutup ke Dana Terkumpul** → komposisi ekuiti tercemar selepas tutup-tahun pertama (BS masih seimbang secara matematik) | Sebelum tutup-tahun: re-point ke COA anak / kosongkan `is_header` |

---

## D. TATA-KELOLA / SoD (keputusan PENGGUNA — bukan pepijat kod)

> Reka bentuk peranan 7m/7n sengaja beri tetapan aras-masjid kepada bendahari/pentadbir. Audit membangkitkan implikasi SoD untuk anda putuskan.

- **D1 [TINGGI implikasi] Bendahari (maker) kawal suis maker-checker.** `routes/web.php:365-367` beri `/tetapan/kawalan` (POST) kpd `bendahari,pentadbir`; bendahari boleh matikan `approval_enabled` / naikkan ambang yang mengawal bayarannya sendiri. Docstring `KawalanController`/`ApprovalService` kata "admin sahaja" — **percanggahan docstring vs route** (docstring stale). Suis lalai OFF, jadi maker-checker opsyenal.
- **D2 [TINGGI implikasi] Bendahari boleh cipta/reset kata laluan Pengerusi (checker).** `PenggunaController` benarkan bendahari urus mana-mana peranan bukan-admin masjidnya → boleh cipta pengerusi terkawal → luluskan bayaran sendiri. (Naik taraf ke `admin` DIBLOK dengan betul.)
- **D3 [SEDERHANA] Pentadbir (sepatutnya tiada tulis kewangan) boleh pos jurnal** melalui `bank.opening.*` (OpeningBalanceService) dan `tutuptahun.tutup` (YearEndService voucher POSTED). Percanggahan dgn takrif peranan. Putuskan: kekalkan (setup) atau alih ke `role:bendahari`/`admin` sahaja.
- **D4 [SEDERHANA] setiausaha & pentadbir baca penuh semua data kewangan** (semua GET kewangan tiada gate peranan; hanya viewer disekat). Tak selari takrif "bukan-kewangan". Pendedahan baca, tiada laluan tulis.

---

## E. HARDENING / RENDAH

- **E1** `/v1/auth/token` tiada throttle → brute force client secret tanpa halangan. Tambah throttle IP. (`routes/api_v1.php:24`)
- **E2** Semua model `$guarded=[]` — selamat kini (semua controller guna `validated()`), laten. Cadang `$fillable` eksplisit pada `AppUser` (role/masjid_id/is_active). (`app/Models/*`)
- **E3** Idempotency key tak di-namespace ikut path → guna kunci sama merentas `/receipts` & `/payments` replay respons salah (bayaran senyap hilang). Masuk path+hash body ke kunci. (`ApiIdempotency.php:39`)
- **E4** Kunci Gemini dalam URL query → bocor ke log ralat bila sambungan gagal. Pindah ke header / strip URL log. (`GeminiDialect.php:24,37`)
- **E5** 500 generik tak ikut envelope API `{error:{code,message}}`. Tambah renderable catch-all `Throwable` utk `v1/*`. (`bootstrap/app.php:100`)
- **E6** `GoogleDriveBackupService::ujiPulih` tak pernah dipanggil — backup tak round-trip restore-test. Jadualkan berkala. (`GoogleDriveBackupService.php:89`)
- **E7** Dual-write: window "posted-then-local-commit-fail" boleh POST berganda ke SPPKMS lama; VOID tak beri amaran padam-manual untuk rekod FAILED. (`SppkmsDualWriteService.php:99`, `QueuesDualWrite.php:62`)
- **E8** Import penyata bank tiada dedup → import CSV dua kali gandakan baris (tak sentuh lejar). (`ReconciliationService.php:63`)
- **E9** SSRF webhook subscription (`target_url` admin-only, `url` sahaja) — sekat julat privat/link-local. (`ApiController.php:93`)
- **E10** Prompt-injection dokumen boleh naikkan `confidence` AI → tindas amaran keyakinan-rendah. Impak terhad (manusia bendahari mesti sahkan; tiada auto-pos). (`CallAiExtraction.php:235`)
- **E11** Lejer/Buku Jurnal tapis ikut `jv.tarikh`; Penyata/TB/BS ikut `period_ym` — 4 voucher (RK001532/1533/2041/2102) tarikh≠period → lejar bulan ≠ penyata bulan. Dokumentasikan/seragamkan paksi masa.
- **E12** Nombor baucer/resit manusia berganda: `pembayaran.baucer_no` 124 kumpulan pendua (4 kosong), `kutipan.no_resit` 4 kumpulan — kebolehkesanan terjejas (laporan SUM baris, jadi angka TETAP betul; `journal_voucher.voucher_ref` 0 pendua). Warisan V1; pertimbang UNIQUE/amaran untuk rekod baru.
- **E13** View DB `v_program_report` tiada tapisan status/void (akan kira DELETED/CANCELLED). **Tak digunakan kod** (ReportService guna query tapisan-POSTED sendiri) → tiada impak hidup; betulkan view jika di-query terus.

---

## F. DISAHKAN SELAMAT (bukti utama)

1. **Double-entry** — 0 voucher tak seimbang, 0 entri yatim, Σd=Σk keseluruhan & per-voucher; JournalService bccomp + tolak COA header/tak-aktif + tolak Dr&Cr serentak/negatif/sifar; mirror CHECK DB `chk_je_amount`.
2. **VOID** — lockForUpdate + AlreadyVoidedException (double-void selamat); pembalik simetri penuh tarikh+period asal; asal+pembalik dua-dua VOID; semua laporan tapis POSTED → residual kosong.
3. **Number sequence** — lockForUpdate + UNIQUE `(masjid_id,jenis)`; `next()` assert transactionLevel>0; rollback pulih kaunter tanpa jurang.
4. **Baki awal / susut nilai / tutup-tahun** — idempoten via UNIQUE (`OB-<thn>`, `uq_dep_bulan`, `YE-<thn>`); sejarah P&L/BS tak berubah (TB/BS `<=`, P&L `01..12`).
5. **Multi-penyewa** — skop global konsisten; `SetMasjidContext` sebelum `SubstituteBindings` (anti-IDOR route-binding); fix BankStatementLine kekal; `withoutMasjidScope()` sentiasa disusuli `where('masjid_id')`; FK guna `existsMasjid()`. **Tiada IDOR hidup.**
6. **Keselamatan** — rahsia AES dalam secret_vault (jadual lain `*_ref`); lampiran PII disk private + route berpagar + nosniff; XSS `{!! !!}` hanya formatter wang; SQLi tiada (parameter terikat); honeypot + rate-limit + session regenerate; HMAC webhook; Telegram fail-closed.
7. **API/AI** — token cache 3600s + hash_equals + IP allowlist + skop per-endpoint; AI tak pernah pos terus (draf PENDING_REVIEW → confirm bendahari sahaja); ExtractionResult::fromJson selamat malformed; dual-write idempoten firstOrCreate UNIQUE.
8. **Tally** — kutipan/pembayaran ACTIVE = Dr/Cr tunai voucher; penyata gabungan seimbang 31/31 bulan; PDF/Excel dari servis sama (angka konsisten); pembundaran ~1e-9 « ambang 0.005.

**Artifak data diketahui (bukan pepijat):** 102 entri POSTED ke COA header (600-12000: 41/RM46,703.60; 600-15000: 61/RM29,540.01) — warisan migrasi V1; kod baru menolak pos COA header.

---

## STATUS PELAKSANAAN (2 Jul 2026) — SEMUA DIBAIKI, 262/262 UJIAN LULUS
Dilaksanakan pada cabang `fix/audit-20260702` (pelan: `PELAN-PEMBAIKAN-AUDIT-20260702.md`; sejarah: `spm-explore/SEJARAH-KERJA.md` §7p).
- **DIBAIKI + diuji:** A1–A5, B1, B2, B3, B4, B5, C1–C6, C8, E1, E2, E4, E5, E6, E7, E8, E9, E13.
- **DIDOKUMEN (sengaja tidak diubah):** C7 (dashboard rekupmen = V1-faithful buku-tunai; mengubah akan pecah tally 899,569.50); D1–D4 (SoD — pengguna pilih kekal bendahari/pentadbir; docstring dibetulkan); E10 (prompt-injection — impak terhad, manusia sahkan); E11 (paksi tarikh vs period_ym — sengaja); E12 (baucer_no pendua warisan — angka betul, UNIQUE retro akan gagal).
- **Pengesahan prod:** verify-balance 3778 seimbang; verify-audit-chain 9 baris utuh; tally 2024 belanja 927720.76 (tak berubah).
- **Baki go-live:** merge cabang; regen install.sql untuk fresh install; tukar kata laluan sebenar (`php artisan sppkms:reset-default-passwords`).

---

## KEUTAMAAN PEMBAIKAN
1. **Go-live:** A1–A5 (config + kredensial + buang masjid 50).
2. **Pepijat kelihatan sekarang:** B1 (rantai audit), B2 (penyata bank rekupmen), C7 (dashboard vs penyata).
3. **Sebelum masjid ke-2 di-onboard:** B5 (kebocoran baca silang-penyewa viewer).
4. **Sebelum tutup-tahun pertama:** B3 (P&L period-13) + C4 + C8 (header-COA ekuiti).
5. **Sebelum dedah API beban tinggi:** B4 (idempotency race).
6. **Concurrency + validasi:** C2, C3, C5, C6.
7. **Keputusan pengguna:** D1–D4 (SoD).
8. **Hardening:** E1–E13.
