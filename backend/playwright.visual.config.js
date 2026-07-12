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
  REVERB_APP_ID: 'pesantenku-visual',
  REVERB_APP_KEY: 'pesantenku-visual-key',
  REVERB_APP_SECRET: 'pesantenku-visual-secret',
  REVERB_ALLOWED_ORIGINS: '127.0.0.1,localhost',
};
const runId = process.env.PESANTRENKU_VISUAL_RUN || Date.now().toString(36);

export default defineConfig({
  testDir: './tests/e2e',
  timeout: 240_000,
  expect: { timeout: 12_000 },
  retries: 0,
  reporter: [['list']],
  use: {
    trace: 'retain-on-failure',
    viewport: { width: 1440, height: 1000 },
  },
  webServer: [
    {
      command: 'php artisan reverb:start --host=127.0.0.1 --port=8280',
      port: 8280,
      reuseExistingServer: false,
      timeout: 120_000,
      env: { ...commonEnv, REVERB_SERVER_HOST: '127.0.0.1', REVERB_SERVER_PORT: '8280' },
    },
    {
      command: 'php artisan serve --host=127.0.0.1 --port=8200',
      url: 'http://127.0.0.1:8200/health',
      reuseExistingServer: false,
      timeout: 120_000,
      env: {
        ...commonEnv,
        APP_URL: 'http://127.0.0.1:8200',
        REVERB_PORT: '8280',
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: resolve('database/e2e-desktop.sqlite'),
        CACHE_PREFIX: `visual_desktop_${runId}_`,
      },
    },
    {
      command: 'php artisan serve --host=127.0.0.1 --port=8201',
      url: 'http://127.0.0.1:8201/health',
      reuseExistingServer: false,
      timeout: 120_000,
      env: {
        ...commonEnv,
        APP_URL: 'http://127.0.0.1:8201',
        REVERB_PORT: '8280',
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: resolve('database/e2e-mobile.sqlite'),
        CACHE_PREFIX: `visual_mobile_${runId}_`,
      },
    },
  ],
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'], baseURL: 'http://127.0.0.1:8200' },
    },
    {
      name: 'mobile-chromium',
      use: { ...devices['Pixel 7'], baseURL: 'http://127.0.0.1:8201' },
    },
  ],
});
