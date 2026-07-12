import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  timeout: 240_000,
  expect: { timeout: 12_000 },
  retries: 0,
  fullyParallel: true,
  workers: 4,
  reporter: [['list']],
  use: {
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'desktop-a',
      metadata: { roles: ['super-admin', 'owner', 'keuangan', 'akademik', 'tu'], workflows: true },
      use: { ...devices['Desktop Chrome'], baseURL: 'https://e2e-desktop-a.pesantrenku.localhost', viewport: { width: 1440, height: 1000 } },
    },
    {
      name: 'desktop-b',
      metadata: { roles: ['santri', 'wali-murid', 'guru', 'musyrif', 'klinik'], workflows: false },
      use: { ...devices['Desktop Chrome'], baseURL: 'https://e2e-desktop-b.pesantrenku.localhost', viewport: { width: 1440, height: 1000 } },
    },
    {
      name: 'mobile-a',
      metadata: { roles: ['super-admin', 'owner', 'keuangan', 'akademik', 'tu'], workflows: true },
      use: { ...devices['Pixel 7'], baseURL: 'https://e2e-mobile-a.pesantrenku.localhost' },
    },
    {
      name: 'mobile-b',
      metadata: { roles: ['santri', 'wali-murid', 'guru', 'musyrif', 'klinik'], workflows: false },
      use: { ...devices['Pixel 7'], baseURL: 'https://e2e-mobile-b.pesantrenku.localhost' },
    },
  ],
});
