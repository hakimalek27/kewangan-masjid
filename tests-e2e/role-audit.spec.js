// Audit peranan MENYELURUH (Chromium) — 4 akaun sebenar, satu demi satu:
//   SUPERADMIN (admin)    : akses penuh SEMUA tenant — baca+tulis+kawalan sistem
//   PENTADBIR MASJID      : urus tetapan masjid + pengguna, TIADA kewangan/sistem
//   BENDAHARI             : kewangan penuh masjid sendiri, TIADA sistem
//   VIEWER (pemerhati)    : penyata SAHAJA, hanya masjid yang di-assign
// Prasyarat (seed ke klon spkm_test — lihat tests-e2e/README.md):
//   admin/admin12345 · malmutaqqin/alm12345 · pentadbir_uji/uji12345 ·
//   viewer_uji/uji12345 (assign masjid "UJIAN BROWSER MASJID B") · masjid B + COA.
import { test, expect } from '@playwright/test'

test.describe.configure({ mode: 'serial' })

const ACC = {
  admin: { login: 'admin', pass: 'admin12345' },
  bendahari: { login: 'malmutaqqin', pass: 'alm12345' },
  pentadbir: { login: 'pentadbir_uji', pass: 'uji12345' },
  viewer: { login: 'viewer_uji', pass: 'uji12345' },
}
const MASJID_B = 'UJIAN BROWSER'

async function login(page, akaun) {
  await page.goto('/')
  await page.fill('input[name="login"]', akaun.login)
  await page.fill('input[name="password"]', akaun.pass)
  await Promise.all([
    page.waitForURL(/\/(dashboard|sistem|penyata)/, { timeout: 15000 }),
    page.press('input[name="password"]', 'Enter'),
  ])
}

/** Isi & hantar borang kutipan TUNAI melalui borang sebenar (butang klik sebenar). */
async function rekodKutipanTunai(page, jumlah) {
  await page.goto('/kutipan/baru', { waitUntil: 'domcontentloaded' })
  const butangSimpan = page.locator('button[type="submit"]', { hasText: 'Simpan Kutipan' })
  await expect(butangSimpan).toBeVisible() // butang tulis HADIR
  await page.evaluate((jml) => {
    const coa = document.querySelector('select[name="coa_id"]')
    const opt = [...coa.options].find((o) => o.textContent.includes('400-03010'))
      || [...coa.options].find((o) => o.value !== '')
    coa.value = opt.value
    coa.dispatchEvent(new Event('change', { bubbles: true }))
    const kaedah = document.querySelector('select[name="kaedah"]')
    kaedah.value = 'TUNAI'
    kaedah.dispatchEvent(new Event('change', { bubbles: true }))
    // Tarikh HARI INI supaya rekod muncul dalam senarai (tapisan lalai bulan semasa)
    document.querySelector('input[name="tarikh"]').value = new Date().toISOString().slice(0, 10)
    const j = document.querySelector('input[name="jumlah"]')
    j.value = jml
    j.dispatchEvent(new Event('input', { bubbles: true }))
    const auto = document.querySelector('input[name="auto_resit"]')
    if (auto && !auto.checked) auto.click()
    const semakan = document.querySelector('input[name="semakan"]')
    if (semakan && !semakan.checked) semakan.click()
  }, jumlah)
  await Promise.all([
    page.waitForLoadState('domcontentloaded'),
    butangSimpan.click(),
  ])
  // Tiada ralat validasi & tidak kembali ke borang dengan ralat
  await expect(page.locator('.invalid-feedback, .alert-danger')).toHaveCount(0)
}

/* ================================================================ SUPERADMIN */

test('SUPERADMIN: landing Konsol Sistem + menu PENUH + penukar masjid', async ({ page }) => {
  await login(page, ACC.admin)
  await expect(page).toHaveURL(/\/sistem/)
  await expect(page.locator('body')).toContainText('Konsol Sistem')

  // Menu penuh: kumpulan kewangan + sistem KEDUA-DUANYA kelihatan (akses penuh)
  const sidebar = page.locator('#sidebar, .sidebar').first()
  for (const label of ['Penerimaan / Kutipan', 'Perbelanjaan', 'Penyata', 'Lanjutan', 'Pentadbiran', 'Tetapan']) {
    await expect(sidebar, `menu "${label}" mesti ada untuk superadmin`).toContainText(label)
  }

  // Penukar masjid hadir dengan >= 2 masjid
  expect(await page.locator('form[action$="/masjid/tukar"]').count()).toBeGreaterThan(1)
})

test('SUPERADMIN: SEMUA jenis halaman akses 200 (kewangan + tetapan + admin)', async ({ page }) => {
  test.setTimeout(120000)
  await login(page, ACC.admin)
  const halaman = [
    '/dashboard', '/kutipan/baru', '/kutipan/senarai', '/belanja/baru', '/belanja/senarai',
    '/akaun/untung-rugi', '/akaun/kunci-kira-kira', '/penyata/bulanan',
    '/bank', '/tetapan/kawalan', '/tetapan/tutup-tahun', '/tetapan/pengguna', '/tetapan/masjid',
    '/belanjawan', '/dana', '/rekonsiliasi', '/semak-penyata', '/kelulusan', '/draf',
    '/sistem', '/admin/pemantauan', '/admin/audit', '/admin/backup', '/admin/semak-penyata',
    '/tetapan/ai', '/tetapan/api', '/tetapan/masjid-baru',
  ]
  const gagal = []
  for (const p of halaman) {
    const r = await page.request.get(p)
    if (r.status() >= 400) gagal.push(`${p} → ${r.status()}`)
  }
  expect(gagal, `Halaman gagal utk superadmin: ${gagal.join(', ')}`).toHaveLength(0)
})

test('SUPERADMIN: TULIS kewangan sebenar (kutipan) berjaya end-to-end', async ({ page }) => {
  await login(page, ACC.admin)
  await rekodKutipanTunai(page, '7.89')
  await page.goto('/kutipan/senarai', { waitUntil: 'domcontentloaded' })
  await expect(page.locator('body')).toContainText('7.89') // rekod muncul dalam senarai
})

test('SUPERADMIN: tukar masjid → konteks data ikut, dan kembali', async ({ page }) => {
  await login(page, ACC.admin)
  await page.goto('/dashboard', { waitUntil: 'domcontentloaded' })

  // Tukar ke masjid B melalui borang penukar sebenar
  await page.evaluate((nama) => {
    const borang = [...document.querySelectorAll('form[action$="/masjid/tukar"]')]
      .find((f) => f.textContent.includes(nama))
    borang.querySelector('button[type="submit"]').click()
  }, MASJID_B)
  await page.waitForLoadState('domcontentloaded')
  await page.goto('/dashboard', { waitUntil: 'domcontentloaded' })
  await expect(page.locator('body')).toContainText(MASJID_B) // konteks = masjid B

  // Kembali ke masjid 49
  await page.evaluate(() => {
    const borang = [...document.querySelectorAll('form[action$="/masjid/tukar"]')]
      .find((f) => f.textContent.includes('AL-MUTTAQIN'))
    borang.querySelector('button[type="submit"]').click()
  })
  await page.waitForLoadState('domcontentloaded')
  await page.goto('/dashboard', { waitUntil: 'domcontentloaded' })
  await expect(page.locator('body')).toContainText('AL-MUTTAQIN')
})

test('SUPERADMIN: kawalan Semak Penyata (AI) — toggle, kunci, kuota semua tenant', async ({ page }) => {
  await login(page, ACC.admin)
  await page.goto('/admin/semak-penyata', { waitUntil: 'domcontentloaded' })
  await expect(page.locator('#api_key')).toBeVisible()                       // borang kunci pusat
  await expect(page.locator('form[action$="/semak-penyata/toggle"] button')).toBeVisible() // toggle
  const body = page.locator('body')
  await expect(body).toContainText('AL-MUTTAQIN')  // jadual kuota merentas tenant
  await expect(body).toContainText(MASJID_B)
})

/* ================================================================ PENTADBIR */

test('PENTADBIR: urus tetapan masjid + pengguna; TIADA kewangan; TIADA sistem', async ({ page }) => {
  await login(page, ACC.pentadbir)
  await expect(page).toHaveURL(/\/dashboard/)

  // BOLEH: tetapan masjid, bank, pengguna (200)
  for (const p of ['/bank', '/tetapan/masjid', '/tetapan/pengguna', '/sewa/senarai', '/peti-besi/senarai']) {
    expect((await page.request.get(p)).status(), p).toBe(200)
  }

  // Borang kutipan BOLEH DIBACA tetapi butang tulis TIADA (bukan maker)
  await page.goto('/kutipan/baru', { waitUntil: 'domcontentloaded' })
  expect(await page.locator('button[type="submit"]', { hasText: 'Simpan Kutipan' }).count()).toBe(0)

  // TIDAK BOLEH: konsol sistem + halaman admin (403)
  for (const p of ['/sistem', '/admin/pemantauan', '/admin/backup', '/admin/semak-penyata', '/tetapan/ai', '/tetapan/api', '/tetapan/masjid-baru']) {
    expect((await page.request.get(p)).status(), p).toBe(403)
  }
})

/* ================================================================ BENDAHARI */

test('BENDAHARI: kewangan penuh + Semak Penyata; TIADA halaman sistem', async ({ page }) => {
  await login(page, ACC.bendahari)
  await expect(page).toHaveURL(/\/dashboard/)

  // TULIS kewangan sebenar berjaya
  await rekodKutipanTunai(page, '6.54')
  await page.goto('/kutipan/senarai', { waitUntil: 'domcontentloaded' })
  await expect(page.locator('body')).toContainText('6.54')

  // Semak Penyata (AI) — halaman ada, borang upload wujud
  await page.goto('/semak-penyata', { waitUntil: 'domcontentloaded' })
  await expect(page.locator('form[action$="/semak-penyata/muat-naik"]')).toHaveCount(1)

  // TIDAK BOLEH: sistem/admin (403)
  for (const p of ['/sistem', '/admin/pemantauan', '/admin/semak-penyata', '/tetapan/ai', '/tetapan/masjid-baru']) {
    expect((await page.request.get(p)).status(), p).toBe(403)
  }

  // Menu TIDAK menunjukkan Pentadbiran (sistem sahaja utk admin)
  await page.goto('/dashboard', { waitUntil: 'domcontentloaded' })
  await expect(page.locator('#sidebar, .sidebar').first()).not.toContainText('Pemantauan Sistem')
})

/* ================================================================== VIEWER */

test('VIEWER: laporan SAHAJA (baca) + hanya masjid yang di-assign superadmin', async ({ page }) => {
  await login(page, ACC.viewer)
  await expect(page).toHaveURL(/\/penyata\/bulanan/) // landing = penyata

  // Halaman BUKAN-laporan DIALIH ke penyata (deny-by-default viewer.guard)
  for (const p of ['/dashboard', '/kutipan/senarai', '/belanja', '/semak-penyata', '/bank', '/tetapan/pengguna']) {
    await page.goto(p, { waitUntil: 'domcontentloaded' })
    await expect(page, `${p} mesti dialih ke penyata`).toHaveURL(/\/penyata\/bulanan/)
  }

  // Dibenarkan BACA: penyata + laporan perakaunan + statistik (model SaaS — JAWI/MAIWP)
  for (const p of ['/penyata/bulanan', '/penyata/bank', '/penyata/tahunan',
                   '/akaun/untung-rugi', '/akaun/kunci-kira-kira', '/akaun/imbangan-duga',
                   '/akaun/lejer', '/akaun/program', '/statistik/kutipan', '/statistik/belanja']) {
    expect((await page.request.get(p)).status(), p).toBe(200)
  }

  // Penukar masjid: HANYA masjid sendiri + masjid di-assign (2 sahaja)
  await page.goto('/penyata/bulanan', { waitUntil: 'domcontentloaded' })
  const borangTukar = page.locator('form[action$="/masjid/tukar"]')
  expect(await borangTukar.count()).toBe(2)
  await expect(page.locator('body')).toContainText(MASJID_B)

  // Boleh tukar ke masjid B yang di-assign → kekal penyata sahaja
  await page.evaluate((nama) => {
    const borang = [...document.querySelectorAll('form[action$="/masjid/tukar"]')]
      .find((f) => f.textContent.includes(nama))
    borang.querySelector('button[type="submit"]').click()
  }, MASJID_B)
  await page.waitForLoadState('domcontentloaded')
  await page.goto('/dashboard', { waitUntil: 'domcontentloaded' })
  await expect(page).toHaveURL(/\/penyata\/bulanan/) // masih viewer, masih penyata sahaja

  // Butang tulis/simpan TIADA pada penyata (borang tukar-masjid & logout dikecualikan)
  expect(await page.locator('button[type="submit"]:visible', { hasText: 'Simpan' }).count()).toBe(0)
})
