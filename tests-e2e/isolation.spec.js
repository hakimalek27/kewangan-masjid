// Ujian pelayar (Playwright) — pengasingan peranan & merentas-masjid + lokasi/lalai
// suis maker-checker. Dijalankan terhadap DB KLON sppkms_test (data boleh-buang):
//   - admin/admin12345 (role admin, masjid 49)
//   - malmutaqqin/alm12345 (role bendahari, masjid 49)
//   - bdh_browser_b/ujianB12345 (role bendahari, masjid B sementara) — dicipta utk ujian
import { test, expect } from '@playwright/test'

const ACC = {
  admin: { login: 'admin', pass: 'admin12345' },
  bendahari49: { login: 'malmutaqqin', pass: 'alm12345' },
  bendahariB: { login: 'bdh_browser_b', pass: 'ujianB12345' },
}
const KUTIPAN_49 = 1 // resit ACTIVE milik masjid 49 dalam sppkms_test

async function login(page, akaun) {
  await page.goto('/')
  await page.fill('input[name="login"]', akaun.login)
  await page.fill('input[name="password"]', akaun.pass)
  await Promise.all([
    page.waitForURL(/\/dashboard/, { timeout: 15000 }),
    page.press('input[name="password"]', 'Enter'),
  ])
}

test('admin: buka Tetapan Kawalan; suis maker-checker LALAI OFF; penukar masjid hadir', async ({ page }) => {
  await login(page, ACC.admin)

  const resp = await page.goto('/tetapan/kawalan')
  expect(resp.status()).toBe(200) // admin BOLEH akses tetapan kawalan
  await expect(page.locator('#approval_enabled')).toBeVisible()
  await expect(page.locator('#approval_enabled')).not.toBeChecked() // lalai OFF
  // Admin ada >1 masjid (49 + B) → penukar masjid dipaparkan (≥1; layout responsif boleh ulang)
  expect(await page.locator('form[action$="/masjid/tukar"]').count()).toBeGreaterThan(0)
})

test('bendahari (masjid 49): Tetapan Kawalan 403; nampak resit sendiri; boleh borang kutipan', async ({ page }) => {
  await login(page, ACC.bendahari49)

  expect((await page.goto('/tetapan/kawalan')).status()).toBe(403)        // admin sahaja
  expect((await page.goto(`/kutipan/${KUTIPAN_49}`)).status()).toBe(200)  // resit masjid sendiri
  expect((await page.goto('/kutipan/baru')).status()).toBe(200)           // borang tulis dibenarkan
})

test('bendahari masjid B: TIDAK boleh buka resit masjid 49 (404 silang-masjid)', async ({ page }) => {
  await login(page, ACC.bendahariB)

  expect((await page.goto('/dashboard')).status()).toBe(200)
  expect((await page.goto(`/kutipan/${KUTIPAN_49}`)).status()).toBe(404)  // anti cross-masjid (IDOR)
})
