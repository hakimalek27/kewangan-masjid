// Konfigurasi ujian pelayar (E2E) — TERPISAH daripada PHPUnit (testDir: tests-e2e).
// Jalankan terhadap pelayan tempatan yang menunjuk ke DB KLON spkm_test (bukan prod).
import { defineConfig } from '@playwright/test'

export default defineConfig({
  testDir: './tests-e2e',
  timeout: 30000,
  fullyParallel: false,
  workers: 1,
  reporter: [['list']],
  use: {
    baseURL: process.env.PW_BASE_URL || 'http://127.0.0.1:8123',
    headless: true,
    ignoreHTTPSErrors: true,
    actionTimeout: 15000,
    navigationTimeout: 20000,
  },
  projects: [{ name: 'chromium' }],
})
