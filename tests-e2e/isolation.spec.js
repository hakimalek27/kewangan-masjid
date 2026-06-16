// Ujian pelayar (Playwright) — model peranan baharu + pengasingan merentas-masjid.
// Klon sppkms_test (data boleh-buang): admin/admin12345 (admin), malmutaqqin/alm12345
// (bendahari, masjid 49), bdh_browser_b/ujianB12345 (bendahari, masjid B sementara).
import { test, expect } from '@playwright/test'

const ACC = {
  admin: { login: 'admin', pass: 'admin12345' },
  bendahari49: { login: 'malmutaqqin', pass: 'alm12345' },
  bendahariB: { login: 'bdh_browser_b', pass: 'ujianB12345' },
}
const KUTIPAN_49 = 1 // resit ACTIVE milik masjid 49

async function login(page, akaun) {
  await page.goto('/')
  await page.fill('input[name="login"]', akaun.login)
  await page.fill('input[name="password"]', akaun.pass)
  await Promise.all([
    page.waitForURL(/\/(dashboard|sistem|penyata)/, { timeout: 15000 }),
    page.press('input[name="password"]', 'Enter'),
  ])
}

test('admin: mendarat di KONSOL SISTEM; boleh baca Kawalan (suis OFF); penukar masjid hadir', async ({ page }) => {
  await login(page, ACC.admin)
  await expect(page).toHaveURL(/\/sistem/)                 // pendaratan = Konsol Sistem, BUKAN dashboard 1 masjid
  await expect(page.locator('body')).toContainText('Konsol Sistem')

  const resp = await page.goto('/tetapan/kawalan')
  expect(resp.status()).toBe(200)                          // admin BOLEH baca tetapan
  await expect(page.locator('#approval_enabled')).not.toBeChecked() // maker-checker lalai OFF
  expect(await page.locator('form[action$="/masjid/tukar"]').count()).toBeGreaterThan(0) // ada >1 masjid
})

test('bendahari (masjid 49): Konsol Sistem 403; boleh borang kutipan & resit sendiri', async ({ page }) => {
  await login(page, ACC.bendahari49)
  expect((await page.goto('/sistem')).status()).toBe(403)            // Konsol Sistem = admin sahaja
  expect((await page.goto('/kutipan/baru')).status()).toBe(200)      // borang tulis dibenarkan (maker)
  expect((await page.goto(`/kutipan/${KUTIPAN_49}`)).status()).toBe(200) // resit masjid sendiri
})

test('bendahari masjid B: TIDAK boleh buka resit masjid 49 (404 silang-masjid)', async ({ page }) => {
  await login(page, ACC.bendahariB)
  expect((await page.goto(`/kutipan/${KUTIPAN_49}`)).status()).toBe(404) // anti cross-masjid (IDOR)
})
