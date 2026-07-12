import { defineConfig, devices } from '@playwright/test';
import { resolve } from 'node:path';

const commonEnv = {
  APP_ENV: 'local',
  CACHE_STORE: 'file',
  SESSION_DRIVER: 'file',
  QUEUE_CONNECTION: 'sync',
  BROADCAST_CONNECTION: 'reverb',
  PESANTRENKU_OPERATION_DISK: 'local',
  PESANTRENKU_OTEL_ENABLED: 'false',
  OTEL_SDK_DISABLED: 'true',
  OTEL_PHP_AUTOLOAD_ENABLED: 'false',
  PESANTRENKU_NOTIFICATION_SSE: 'false',
  PESANTRENKU_NOTIFICATION_WEBSOCKET: 'true',
  REVERB_APP_ID: 'pesantenku-e2e',
  REVERB_APP_KEY: 'pesantenku-e2e-key',
  REVERB_APP_SECRET: 'pesantenku-e2e-secret',
  REVERB_ALLOWED_ORIGINS: '127.0.0.1,localhost',
};

const allRoles = [
  'super-admin',
  'owner',
  'keuangan',
  'akademik',
  'tu',
  'santri',
  'wali-murid',
  'guru',
  'musyrif',
  'klinik',
];

export default defineConfig({
  testDir: './tests/e2e',
  timeout: 180_000,
  expect: { timeout: 8_000 },
  retries: 1,
  reporter: [['list']],
  use: {
    trace: 'retain-on-failure',
    viewport: { width: 1440, height: 1000 },
  },
  webServer: [
    {
      command: 'php artisan reverb:start --host=127.0.0.1 --port=8080',
      port: 8080,
      reuseExistingServer: !process.env.CI,
      timeout: 120_000,
      env: { ...commonEnv, REVERB_SERVER_HOST: '127.0.0.1', REVERB_SERVER_PORT: '8080' },
    },
    {
      command: 'php artisan serve --host=127.0.0.1 --port=8100',
      url: 'http://127.0.0.1:8100/health',
      reuseExistingServer: false,
      timeout: 120_000,
      env: { ...commonEnv, APP_URL: 'http://127.0.0.1:8100', DB_CONNECTION: 'sqlite', DB_DATABASE: resolve('database/e2e-desktop.sqlite'), CACHE_PREFIX: 'e2e_desktop_' },
    },
    {
      command: 'php artisan serve --host=127.0.0.1 --port=8101',
      url: 'http://127.0.0.1:8101/health',
      reuseExistingServer: false,
      timeout: 120_000,
      env: { ...commonEnv, APP_URL: 'http://127.0.0.1:8101', DB_CONNECTION: 'sqlite', DB_DATABASE: resolve('database/e2e-mobile.sqlite'), CACHE_PREFIX: 'e2e_mobile_' },
    },
  ],
  projects: [
    {
      name: 'chromium',
      metadata: { roles: allRoles, workflows: true },
      use: { ...devices['Desktop Chrome'], baseURL: 'http://127.0.0.1:8100' },
    },
    {
      name: 'mobile-chromium',
      metadata: { roles: allRoles, workflows: true },
      use: { ...devices['Pixel 7'], baseURL: 'http://127.0.0.1:8101' },
    },
  ],
});
