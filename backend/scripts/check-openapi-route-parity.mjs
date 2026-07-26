import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..');
const routes = fs.readFileSync(path.join(root, 'routes/api.php'), 'utf8');
const openapi = fs.readFileSync(path.join(root, '../docs/openapi.yaml'), 'utf8');
const operations = new Set();
const routePattern = /Route::(get|post|put|patch|delete)\(\s*(['"])(\/.*?)\2/g;

for (const match of routes.matchAll(routePattern)) {
  const [, method, , routePath] = match;
  if (!routePath.includes('$')) {
    operations.add(`${method.toUpperCase()} ${routePath}`);
  }
}

const documented = new Set();
let currentPath = null;
for (const line of openapi.split(/\r?\n/)) {
  const pathMatch = line.match(/^  (\/[^:]+):\s*$/);
  if (pathMatch) {
    currentPath = pathMatch[1];
    continue;
  }

  const methodMatch = line.match(/^    (get|post|put|patch|delete):\s*$/);
  if (currentPath && methodMatch) {
    documented.add(`${methodMatch[1].toUpperCase()} ${currentPath}`);
  }

  if (/^[^\s]/.test(line) && !line.startsWith('paths:')) {
    currentPath = null;
  }
}

const missing = [...operations].filter((operation) => !documented.has(operation)).sort();
if (missing.length > 0) {
  console.error('OpenAPI tidak mendokumentasikan route API literal berikut:');
  for (const operation of missing) {
    console.error(`- ${operation}`);
  }
  process.exit(1);
}

console.log(`OpenAPI route parity passed (${operations.size} literal operations documented).`);
