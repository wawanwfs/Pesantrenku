// Audit aksesibilitas otomatis (axe-core, WCAG 2.1 A/AA) terhadap server preview.
//   npm i --no-save @axe-core/playwright
//   node scripts/ui-preview/server.mjs 4173 &
//   node scripts/ui-preview/a11y-audit.mjs [light,dark] [halaman-per-panel]
// Memeriksa dashboard + beberapa modul tiap panel, lalu keadaan interaktif (palet, bantuan, menu akun,
// notifikasi, modal tambah/lihat, navigasi seluler) pada desktop dan seluler.
import { chromium } from '@playwright/test';

let AxeBuilder;
try {
  ({ default: AxeBuilder } = await import('@axe-core/playwright'));
} catch {
  console.error('Pasang dulu: npm i --no-save @axe-core/playwright');
  process.exit(2);
}

const base = process.env.PREVIEW_URL || 'http://127.0.0.1:4173';
const themes = (process.argv[2] || 'light,dark').split(',');
const perPanel = Number(process.argv[3] || 3);
const panels = ['super-admin', 'owner', 'keuangan', 'akademik', 'tu', 'santri', 'wali-murid', 'guru', 'musyrif', 'klinik'];
const tags = ['wcag2a', 'wcag2aa', 'wcag21aa'];
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
const findings = new Map();
let pages = 0;

function record(theme, label, violations) {
  for (const violation of violations) {
    for (const node of violation.nodes) {
      const key = `${theme} [${label}] ${violation.id} (${violation.impact}) ${node.target.join(' ').slice(0, 100)}`;
      if (!findings.has(key)) findings.set(key, (node.any[0]?.message || node.failureSummary || '').split('\n')[0].slice(0, 170));
    }
  }
}

for (const theme of themes) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, colorScheme: theme });
  await context.addInitScript((value) => window.localStorage.setItem('pk-theme', value), theme);
  const page = await context.newPage();
  for (const path of ['/', '/login']) {
    await page.goto(base + path, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    record(theme, path, (await new AxeBuilder({ page }).withTags(tags).analyze()).violations);
    pages += 1;
  }
  for (const panel of panels) {
    await page.goto(`${base}/dashboard/${panel}`, { waitUntil: 'networkidle' });
    const links = await page.$$eval('aside nav a', (anchors) => [...new Set(anchors.map((a) => a.getAttribute('href')))]);
    for (const href of [`/dashboard/${panel}`, ...links.slice(1, perPanel + 1)]) {
      await page.goto(base + href, { waitUntil: 'networkidle' });
      await page.waitForTimeout(1000);
      record(theme, href, (await new AxeBuilder({ page }).withTags(tags).analyze()).violations);
      pages += 1;
    }
  }
  await context.close();

  for (const viewport of [{ width: 1440, height: 900 }, { width: 390, height: 844 }]) {
    const ctx = await browser.newContext({ viewport, colorScheme: theme });
    await ctx.addInitScript((value) => window.localStorage.setItem('pk-theme', value), theme);
    const ui = await ctx.newPage();
    const scan = async (label) => {
      await ui.waitForTimeout(450);
      record(`${theme}@${viewport.width}`, label, (await new AxeBuilder({ page: ui }).withTags(tags).analyze()).violations);
      pages += 1;
    };
    await ui.goto(`${base}/keuangan/module/santri`, { waitUntil: 'networkidle' });
    await ui.waitForTimeout(1000);
    await ui.keyboard.press('Control+k'); await scan('palet'); await ui.keyboard.press('Escape');
    await ui.keyboard.press('?'); await scan('bantuan'); await ui.keyboard.press('Escape');
    await ui.goto(`${base}/dashboard/keuangan?guide=1`, { waitUntil: 'networkidle' });
    await ui.waitForTimeout(1800);
    await scan('panduan singkat'); await ui.keyboard.press('Escape');
    await ui.goto(`${base}/keuangan/module/santri`, { waitUntil: 'networkidle' });
    await ui.waitForTimeout(800);
    await ui.getByLabel('Buka menu akun').click(); await scan('menu akun'); await ui.keyboard.press('Escape'); await ui.mouse.click(5, 5);
    await ui.getByLabel('Buka notifikasi').click(); await scan('notifikasi'); await ui.mouse.click(5, 5);
    await ui.getByRole('button', { name: /Tambah Santri/ }).click(); await scan('modal tambah'); await ui.keyboard.press('Escape');
    await ui.getByLabel('Lihat detail').first().click(); await scan('modal lihat'); await ui.keyboard.press('Escape');
    if (viewport.width < 600) { await ui.getByLabel('Buka menu', { exact: true }).click(); await scan('navigasi seluler'); }
    await ctx.close();
  }
}

for (const [key, message] of findings) console.log(`${key}\n    ${message}`);
console.log(`\nHalaman/keadaan diaudit: ${pages}, temuan unik: ${findings.size}`);
await browser.close();
process.exit(findings.size ? 1 : 0);
