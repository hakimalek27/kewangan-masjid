# PELAN PEMBAIKAN AUDIT END-TO-END (A→Z) — SPAKM (sppkms-v2)
**Tarikh:** 2 Julai 2026 · **Rujukan:** `LAPORAN-AUDIT-MENYELURUH-20260702.md` · **Baseline:** 247/247 ujian LULUS

## PRINSIP KERJA
1. **Teras perakaunan TIDAK disentuh** melainkan perlu — setiap fasa diikuti `php artisan test` + `verify-balance` + `verify-audit-chain` mesti kekal HIJAU.
2. Semua perubahan **DB prod** (`sppkms`) dibuat SELEPAS `mysqldump` backup + kebenaran eksplisit; kod & ujian guna `sppkms_test`.
3. Setiap pepijat = **ujian gagal dahulu (repro) → fix → ujian lulus** (TDD di mana praktikal).
4. Commit berasingan per fasa (mesej jelas) supaya boleh roll-back berperingkat.
5. Fasa disusun ikut risiko & kebergantungan: Go-live → pepijat kelihatan → laten pra-tutup-tahun → concurrency → tata-kelola → hardening.

---

## FASA 0 — PERSEDIAAN & JARING KESELAMATAN
- [ ] Hidupkan MariaDB; sahkan baseline: `php artisan test` (247), `sppkms:verify-balance`, `sppkms:verify-audit-chain`.
- [ ] Backup penuh DB prod: `mysqldump sppkms > spm-explore/shots/pra-fix-audit-20260702.sql`.
- [ ] Pastikan `sppkms_test` klon segar & selari skema prod (ENUM role dll).
- [ ] Cipta cabang kerja `fix/audit-20260702` (atau susun commit berperingkat).
- **Verifikasi:** ketiga-tiga hijau sebelum sebarang perubahan.

---

## FASA 1 — GO-LIVE HARDENING (config + kredensial + residu)  ⟶ tiada kesan kod perakaunan
### 1a. Bersihkan residu ujian masjid 50 dari DB prod  *(perlu kebenaran; ini juga membaiki verify-audit-chain)*
- [ ] Backup fokus: `mysqldump sppkms masjid app_user coa audit_trail user_masjid user_setting > shots/pra-buang-masjid50.sql`.
- [ ] Sahkan sekali lagi masjid 50 = 0 transaksi (journal_voucher/kutipan/pembayaran).
- [ ] Padam ikut susunan FK: `user_masjid` (user_id=3 / masjid_id=50) → `user_setting` (user 3) → `coa WHERE masjid_id=50` (129) → `app_user id=3 'test'` → `audit_trail WHERE masjid_id=50` (28,29,30) → `masjid id=50`.
- **Verifikasi:** `verify-audit-chain` → "rantai utuh" (kini hanya masjid 49, 27 baris utuh); `verify-balance` tak berubah; tally KKK tak berubah.

### 1b. Sediakan `.env.production` template + dokumentasi go-live
- [ ] Cipta `.env.production.example`: `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=warning`, `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=strict`, ruang `TELEGRAM_WEBHOOK_SECRET`, `GDRIVE_SA_JSON_PATH`, kredensial DB bukan-root.
- [ ] Tambah seksyen "Senarai Semak Go-Live" dalam `README.md` (optimize:clear, restart web/OPcache, tukar kata laluan).

### 1c. Paksa tukar kata laluan lalai
- [ ] Tambah lajur/flag `must_change_password` (jika belum ada) ATAU command `sppkms:reset-default-passwords` yang set kata laluan rawak + paksa tukar pada login pertama.
- [ ] Middleware/redirect: jika `must_change_password=1` → paksa ke halaman tukar kata laluan sebelum akses lain.
- [ ] Ujian: login dengan akaun ber-flag → diarah ke tukar kata laluan.
- **Nota:** tukar kata laluan sebenar akaun prod = tindakan pengguna (saya tak set kata laluan pilihan pengguna).

**Commit F1:** "Go-live hardening: remove test masjid residue, prod env template, forced password change"

---

## FASA 2 — PEPIJAT KELIHATAN SEKARANG
### 2a. B1 — Rantai hash audit silang-masjid  `AuditTrailService.php:31-35`
- [ ] Ujian repro (Feature): ikat `current.masjid_id=A`, `log()` ke masjid B dua/tiga kali → assert `prev_hash` berantai (bukan NULL) & `verify-audit-chain` lulus.
- [ ] Fix: query prev guna `AuditTrail::withoutMasjidScope()` + `$masjidId !== null ? where('masjid_id',$masjidId) : whereNull('masjid_id')` (buang `when()`).
- [ ] Ujian onboarding (`MasjidOnboardingTest`): selepas cipta masjid, rantai audit masjid baharu utuh.
- **Verifikasi:** `verify-audit-chain` hijau; ujian sedia ada tak regres.

### 2b. B2 — Penyata Ikut Bank tak seimbang dengan REKUPMEN  `PenyataController.php:54-56`
- [ ] Ujian (Feature): panggil penyata bulan ber-rekupmen untuk satu bank → assert `jumlah_kiri === jumlah_kanan`; dan penyata gabungan masih seimbang.
- [ ] Fix di `paparBulanan()`: bila `$bank` diberi → `jumlahKiri = bakiAwal + terimaan`; `jumlahKanan = belanja + pindahan + bakiAkhir` (pindahan sebelah kanan sahaja). Bila gabungan (`$bank===null`) → kekal tambah dua sisi (betul).
- [ ] Kemas view `penyata/*` supaya paparan baris "Pelarasan Pindahan PWR" ikut sisi betul mengikut mod (bank vs gabungan).
- [ ] Sahkan `yearlyStatement` TIDAK terjejas (tiada penapis bank — kekal betul).
- **Verifikasi:** 13 bulan prod (contoh Jan/Mac/Mei 2024/2025) kini seimbang; ujian tally sejarah kekal lulus.

### 2c. C7 — Dashboard "Perbelanjaan" gandakan REKUPMEN  `StatementService::ringkasanTunai:168-181` + `DashboardService::trendBulanan:32`
- [ ] Ujian: `ringkasanTunai(2025)` `bayar_tunai` === penyata `jumlah_belanja` (tiada rekupmen).
- [ ] Fix: buang `'REKUPMEN'` dari `whereIn('jenis',[...])` dalam `bayarBank` (rekupmen = pindahan dalaman, bukan belanja). Terapkan padanan dalam `trendBulanan`.
- **Verifikasi:** dashboard "Perbelanjaan" 2025 = 897,644.85 (padan penyata); statistik tak berubah.

**Commit F2:** "Fix visible reporting bugs: audit chain cross-masjid, by-bank statement rekupmen, dashboard expense double-count"

---

## FASA 3 — LATEN PRA-TUTUP-TAHUN (WAJIB sebelum tutup-tahun pertama)
### 3a. B3 — P&L merentas tahun serap period `-13`/`-00`  `ReportService::profitLoss:56-59`
- [ ] Ujian: seed voucher period `2024-13` (penutupan) + `2025-00` (OB) → `profitLoss('2024-01','2025-12')` mesti KECUALIKAN kedua-duanya.
- [ ] Fix: tambah `->whereRaw("SUBSTR(jv.period_ym,6,2) BETWEEN '01' AND '12'")` dalam `profitLoss()` sahaja (TB/BS guna `<=` — kekal betul).
- **Verifikasi:** P&L 30 bulan sejarah tak berubah; ujian tally sejarah lulus.

### 3b. C4 — Tutup tahun: kira baki dalam transaksi + berhad julat tahun + wajib tahun N-1 tutup  `YearEndService.php:92-146`
- [ ] Ujian: (i) tutup 2024 tanpa tutup 2023 → di-tolak dengan mesej "tutup tahun sebelum dahulu"; (ii) selepas tutup, Hasil/Belanja tahun itu = 0; (iii) pratonton = nilai sebenar ditutup.
- [ ] Fix: dalam satu `DB::transaction` — (1) `lockUntil` period dahulu / semak tahun N-1 sudah tutup (`voucherPenutupan(N-1)` wujud atau tiada transaksi tahun N-1); (2) kira baki berhad `whereBetween [YYYY-01, YYYY-12]` (bukan kumulatif `<=`); (3) pos voucher penutupan.
- **Verifikasi:** tiada regresi; simulasi tutup 2024 pada `sppkms_test` → BS ekuiti betul.

### 3c. C8 — 102 entri POSTED ke COA header (~RM76k) tak ditutup YearEnd  *(keputusan + migrasi data prod)*
- [ ] Siasat: senaraikan 102 entri (600-12000, 600-15000) + tentukan COA anak sepadan (600-120xx / 600-150xx) via pemetaan/butiran.
- [ ] **Pilihan A (disyorkan):** re-point `journal_entry.coa_id` header → COA anak yang betul (dalam transaksi, backup dahulu, verify-balance selepas). Kekalkan jumlah & tarikh.
- [ ] **Pilihan B:** jika tiada anak sesuai, cipta COA anak "lain-lain" di bawah header dan re-point.
- [ ] Ujian: selepas re-point, 0 entri POSTED ke `is_header=1`; simulasi tutup-tahun → ekuiti Dana Terkumpul betul.
- **Nota:** menyentuh GL prod → **perlu kebenaran + backup**; angka agregat P&L/BS TIDAK berubah (hanya pindah dari induk ke anak).

**Commit F3:** "Year-end integrity: exclude closing periods from P&L, transactional/bounded year-end, re-point header-COA postings"

---

## FASA 4 — CONCURRENCY & VALIDASI INPUT
### 4a. B4 — Idempotency API race  `ApiIdempotency.php:42-69`
- [ ] Ujian: dua permintaan `Idempotency-Key` sama serentak → hanya SATU rekod dicipta (yang kedua dapat replay/409).
- [ ] Fix: `Cache::lock($cacheKey, 10)->block(5)` atau `Cache::add($sentinel)` atom SEBELUM `$next()`; lepas selepas simpan respons.
- [ ] E3 (sekali): masukkan `path`+hash body ke dalam kunci idempotency (elak replay silang-endpoint).

### 4b. C2 — Susut nilai lost-update  `DepreciationService.php:127-129`
- [ ] Fix: `FixedAsset::whereKey($id)->increment('accumulated_depn', $amaun)` atom dalam transaksi (bukan baca-kira-tulis).
- [ ] Ujian: dua jana bulan berbeza untuk aset sama → accumulated betul, tak lebih kos.

### 4c. C3 — Pelupusan aset double-post  `DepreciationService.php:157,183`
- [ ] Fix: `lockForUpdate` + semak `status==='AKTIF'` semula DALAM `DB::transaction` (corak sama VoidService).
- [ ] Ujian: dua panggilan lupus serentak/berturut → hanya 1 voucher pelupusan.

### 4d. C5 — Validasi input bulan  `PerakaunanController:282`, `PenyataController:253`, `StatistikController:89`
- [ ] Fix: hadkan `bln` → `max(1,min(12,(int)$bln))` (atau FormRequest `between:1,12`).
- [ ] Ujian: `?bln=13`/`?bln=0` → guna 12/1 atau 422 (tak hasilkan period `-13`/`-00`).

### 4e. C6 — Jurnal manual boleh Cr akaun tunai  `JurnalRequest.php:15` + `belanja/jurnal.blade.php:36`
- [ ] Fix: tolak COA `250-05%`/`250-06%` dalam `JurnalRequest` (rule tersuai) + buang dari dropdown Cr; papar amaran.
- [ ] Ujian: jurnal Cr 250-05010 → validasi gagal.

### 4f. C1 — "Fork" rantai audit serentak  `AuditTrailService`
- [ ] Fix: kunci baris induk per-masjid sebelum baca hujung rantai (cth `SELECT ... FOR UPDATE` pada baris terakhir masjid, atau advisory lock), ATAU tambah kolum unik `(masjid_id, seq)`.
- [ ] Ujian serentak (jika praktikal) / semakan logik.

**Commit F4:** "Concurrency hardening + input validation: idempotency lock, atomic depreciation, disposal lock, month bounds, journal cash-COA guard, audit chain locking"

---

## FASA 5 — TATA-KELOLA / SoD (keputusan pengguna diperlukan — lihat soalan di bawah)
> Saya akan **tanya pengguna** dahulu sebelum melaksana kerana ini keputusan dasar, bukan pepijat.
- [ ] **D1/D3:** Alih `kawalan.simpan` + `tutuptahun.tutup` + `bank.opening.*` ke gate yang dipersetujui (cth `role:admin` atau kekal bendahari) + betulkan docstring supaya SELARAS route.
- [ ] **D2:** Sekat bendahari/pentadbir dari cipta/ubah peranan `pengerusi` (jaga SoD checker) — atau terima risiko & dokumentasi.
- [ ] **B5:** `PenggunaRequest.php:43` — hadkan `masjid_ids.*` bukan-admin ke `accessibleMasjidIds()` (fix kebocoran viewer silang-masjid). **Ini pepijat, akan dibaiki tanpa mengira keputusan D.**
- [ ] **D4:** Putuskan sama ada setiausaha/pentadbir patut baca penuh kewangan (jika tidak, tambah gate GET).
- [ ] Ujian `RoleMatrixTest` dikemas ikut keputusan.

**Commit F5:** "SoD adjustments + fix viewer cross-masjid assignment leak"

---

## FASA 6 — HARDENING KEUTAMAAN RENDAH
- [ ] E1: throttle `POST /v1/auth/token` (RateLimiter IP+client_key).
- [ ] E2: `$fillable` eksplisit pada `AppUser` (role/masjid_id/is_active) + model tulis-pengguna sensitif.
- [ ] E4: kunci Gemini ke header / strip URL dari mesej log ralat.
- [ ] E5: renderable catch-all `Throwable` untuk `v1/*` → envelope `{error:{code,message}}`.
- [ ] E6: jadualkan `GoogleDriveBackupService::ujiPulih` berkala (cth mingguan) + alert bila gagal.
- [ ] E7: dual-write — kendali window "posted-then-commit-fail" (tandakan FAILED-with-recno) + amaran padam-manual untuk FAILED.
- [ ] E8: dedup import penyata bank (hash baris / UNIQUE).
- [ ] E9: sekat julat IP privat/link-local pada `target_url` webhook.
- [ ] E10: kekalkan amaran keyakinan-rendah AI tak boleh ditindas sepenuhnya (paras keyakinan clamp + selalu papar sumber).
- [ ] E11: dokumentasikan/seragamkan paksi masa lejer (`tarikh`) vs penyata (`period_ym`).
- [ ] E12: pertimbang UNIQUE/amaran `baucer_no`/`no_resit` untuk rekod BARU (jangan kuatkuasa retro atas data V1).
- [ ] E13: betulkan view `v_program_report` (tambah tapisan status/POSTED) walau tak diguna.

**Commit F6:** "Low-priority hardening: API throttle, fillable, error envelope, backup restore test, dual-write edge, misc"

---

## FASA 7 — PENGESAHAN MENYELURUH & PENUTUP
- [ ] `php artisan test` — semua LULUS (jangkaan >247, + ujian baharu setiap fasa).
- [ ] `sppkms:verify-balance` (voucher seimbang) + `sppkms:verify-audit-chain` (rantai utuh) HIJAU.
- [ ] Ujian tally sejarah (`TallySejarahTest`, `PenyataTahunanTest`) LULUS — angka sejarah tak berubah.
- [ ] Smoke Playwright (`tests-e2e`) 3/3 (isolasi + peranan).
- [ ] Kemas kini `SEJARAH-KERJA.md` (§7p) + `LAPORAN-UJIAN-V2.md`.
- [ ] Ringkasan akhir: setiap penemuan audit → status (fixed / keputusan pengguna / diterima).

---

## KEPUTUSAN PENGGUNA DIPERLUKAN SEBELUM FASA 3c & 5
1. **C8 (header-COA):** benarkan re-point 102 entri GL prod (backup dahulu, angka agregat tak berubah)? 
2. **D1/D3 (SoD):** siapa patut kawal `kawalan`/`tutup-tahun`/`baki-awal` — kekal bendahari, atau alih ke admin?
3. **D2:** sekat bendahari dari urus akaun pengerusi?
4. **D4:** hadkan bacaan kewangan setiausaha/pentadbir?
> B5 (kebocoran viewer) & semua pepijat lain akan dibaiki tanpa mengira jawapan di atas.

## SKOP MINIMUM DISYORKAN (jika mahu pantas & selamat)
**Fasa 0 + 1 + 2 + B5** — semua boleh dibuat tanpa menyentuh teras perakaunan, membaiki semua isu yang kelihatan/kritikal, dan disahkan penuh dengan ujian. Fasa 3 dibuat sebelum tutup-tahun pertama; Fasa 4–6 ikut keutamaan.
