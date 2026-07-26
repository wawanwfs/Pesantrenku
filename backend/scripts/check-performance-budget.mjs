import { readdir, stat } from 'node:fs/promises';
import { resolve, relative } from 'node:path';

const root = resolve('public/build/assets');
const limits = {
  totalBytes: Number(process.env.PESANTRENKU_ASSET_BUDGET_TOTAL || 1_500_000),
  maxJavaScriptBytes: Number(process.env.PESANTRENKU_ASSET_BUDGET_JS || 550_000),
  maxCssBytes: Number(process.env.PESANTRENKU_ASSET_BUDGET_CSS || 250_000),
};

async function files(dir) {
  const entries = await readdir(dir, { withFileTypes: true });
  const result = [];
  for (const entry of entries) {
    const path = resolve(dir, entry.name);
    if (entry.isDirectory()) result.push(...await files(path));
    else result.push(path);
  }
  return result;
}

let assets;
try {
  assets = await files(root);
} catch (error) {
  console.error(`Build assets tidak tersedia di ${root}. Jalankan npm run build terlebih dahulu.`);
  process.exit(1);
}

let total = 0;
const failures = [];
for (const path of assets) {
  const bytes = (await stat(path)).size;
  total += bytes;
  const name = relative(root, path);
  if (/\.m?js$/i.test(name) && bytes > limits.maxJavaScriptBytes) failures.push(`${name}: ${bytes} > ${limits.maxJavaScriptBytes}`);
  if (/\.css$/i.test(name) && bytes > limits.maxCssBytes) failures.push(`${name}: ${bytes} > ${limits.maxCssBytes}`);
}
if (total > limits.totalBytes) failures.push(`total assets: ${total} > ${limits.totalBytes}`);
if (failures.length) {
  console.error('Performance budget gagal:\n'+failures.map((item) => `- ${item}`).join('\n'));
  process.exit(1);
}
console.log(`Performance budget PASS: ${assets.length} files, ${total} bytes.`);
