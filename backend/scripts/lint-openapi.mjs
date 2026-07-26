import { spawnSync } from 'node:child_process';
import process from 'node:process';

const executable = process.platform === 'win32' ? 'redocly.cmd' : 'redocly';
const result = spawnSync(
  executable,
  ['lint', '../docs/openapi.yaml', '--config', '../redocly.yaml', ...process.argv.slice(2)],
  {
    stdio: 'inherit',
    env: { ...process.env, REDOCLY_TELEMETRY: 'off' },
  },
);

if (result.error) {
  console.error(`Unable to execute Redocly: ${result.error.message}`);
  process.exit(1);
}

process.exit(result.status ?? 1);
