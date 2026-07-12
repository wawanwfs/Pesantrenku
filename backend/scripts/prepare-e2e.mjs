import { existsSync, rmSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { spawnSync } from 'node:child_process';

const databases = ['database/e2e-desktop.sqlite', 'database/e2e-mobile.sqlite'];

for (const database of databases) {
  const absolute = resolve(database);
  if (existsSync(absolute)) rmSync(absolute, { force: true });
  writeFileSync(absolute, '');
  const result = spawnSync('php', ['artisan', 'migrate:fresh', '--seed', '--force'], {
    stdio: 'inherit',
    env: {
      ...process.env,
      APP_ENV: 'local',
      DB_CONNECTION: 'sqlite',
      DB_DATABASE: absolute,
      CACHE_STORE: 'file',
      SESSION_DRIVER: 'file',
      QUEUE_CONNECTION: 'sync',
      BROADCAST_CONNECTION: 'reverb',
      PESANTRENKU_OPERATION_DISK: 'local',
      PESANTRENKU_OTEL_ENABLED: 'false',
      OTEL_SDK_DISABLED: 'true',
      OTEL_PHP_AUTOLOAD_ENABLED: 'false',
    },
  });
  if (result.status !== 0) process.exit(result.status || 1);
}
