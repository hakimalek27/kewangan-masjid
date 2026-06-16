# Ujian Pelayar (Playwright) — pengasingan peranan & merentas-masjid

Ujian E2E ini mengesahkan, dalam pelayar sebenar, bahawa:

- **admin** boleh buka **Tetapan Kawalan** (`/tetapan/kawalan`) dan suis Maker-Checker
  **lalai OFF**; penukar masjid dipaparkan apabila ada >1 masjid.
- **bendahari** (masjid sendiri) **disekat 403** daripada Tetapan Kawalan (admin sahaja),
  tetapi boleh lihat resit masjidnya & borang kutipan.
- **bendahari masjid lain TIDAK boleh** buka resit masjid lain melalui id (**404** —
  anti-IDOR merentas penyewa).

## Jangan sentuh pangkalan data produksi

Ujian ini dijalankan terhadap **klon `sppkms_test`**, bukan `sppkms`.

## Cara jalan

1. **Semai akaun ujian masjid B** (sekali) ke dalam klon:

   ```bash
   DB_DATABASE=sppkms_test php artisan tinker --execute="\$m=App\Models\Masjid::firstOrCreate(['nama'=>'UJIAN BROWSER MASJID B']); App\Models\AppUser::updateOrCreate(['login'=>'bdh_browser_b'],['masjid_id'=>\$m->id,'nama_penuh'=>'Bendahari B Ujian','role'=>'bendahari','password_hash'=>Illuminate\Support\Facades\Hash::make('ujianB12345'),'is_active'=>1]);"
   ```

2. **Hidupkan pelayan** menunjuk ke klon (port 8123):

   ```bash
   php artisan config:clear
   DB_DATABASE=sppkms_test php artisan serve --host=127.0.0.1 --port=8123
   ```

3. **Jalankan ujian** (terminal lain):

   ```bash
   npx playwright test
   ```

4. **Buang akaun ujian** selepas selesai (kembalikan klon):

   ```bash
   DB_DATABASE=sppkms_test php artisan tinker --execute="App\Models\AppUser::where('login','bdh_browser_b')->delete(); App\Models\Masjid::where('nama','UJIAN BROWSER MASJID B')->delete();"
   ```

Akaun sedia ada dalam klon: `admin/admin12345` (admin), `malmutaqqin/alm12345` (bendahari) — kedua-dua masjid 49.
