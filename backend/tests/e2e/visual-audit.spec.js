import { expect, test } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import { resolve } from 'node:path';

const auditRoot = resolve(process.cwd(), '..', 'memory', 'ui-audit', '2026-07-12');
const roles = [
  ['super-admin', 'superadmin@pesantenku.test'],
  ['owner', 'owner@pesantenku.test'],
  ['keuangan', 'keuangan@pesantenku.test'],
  ['akademik', 'akademik@pesantenku.test'],
  ['tu', 'tu@pesantenku.test'],
  ['santri', 'santri@pesantenku.test'],
  ['wali-murid', 'wali@pesantenku.test'],
  ['guru', 'guru@pesantenku.test'],
  ['musyrif', 'musyrif@pesantenku.test'],
  ['klinik', 'klinik@pesantenku.test'],
];

function slug(value) {
  return value
    .replace(/^https?:\/\/[^/]+/, '')
    .replace(/^\/+|\/+$/g, '')
    .replace(/[^a-z0-9]+/gi, '-')
    .replace(/^-|-$/g, '') || 'root';
}

async function login(page, panel, email) {
  await page.context().clearCookies();
  await page.goto('/login', { waitUntil: 'domcontentloaded' });
  await page.locator('input[autocomplete="username"]').fill(email);
  await page.locator('input[type="password"]').fill('password');
  await page.getByRole('button', { name: /Masuk/ }).click();
  await expect(page).toHaveURL(new RegExp(`/dashboard/${panel.replace('-', '\\-')}`));
  await expect(page.getByLabel('Buka menu akun')).toBeVisible();
}

async function inspectLayout(page) {
  return page.evaluate(() => {
    const viewportWidth = document.documentElement.clientWidth;
    const documentOverflow = document.documentElement.scrollWidth - viewportWidth;
    const candidates = [...document.querySelectorAll('main button, main a, main input, main select, main textarea, main h1, main h2, main h3, header button, header a, form button, form input')];
    const outside = [];
    const clipped = [];

    for (const element of candidates) {
      const style = getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      if (style.display === 'none' || style.visibility === 'hidden' || Number(style.opacity) < 0.05 || rect.width < 1 || rect.height < 1) continue;

      let parent = element.parentElement;
      let insideHorizontalScroller = false;
      while (parent && parent !== document.body) {
        const parentStyle = getComputedStyle(parent);
        if (['auto', 'scroll'].includes(parentStyle.overflowX) && parent.scrollWidth > parent.clientWidth + 1) {
          insideHorizontalScroller = true;
          break;
        }
        parent = parent.parentElement;
      }

      if (!insideHorizontalScroller && (rect.left < -1 || rect.right > viewportWidth + 1)) {
        outside.push({
          tag: element.tagName.toLowerCase(),
          text: (element.getAttribute('aria-label') || element.textContent || '').trim().slice(0, 80),
          left: Math.round(rect.left),
          right: Math.round(rect.right),
        });
      }

      const hasDirectText = [...element.childNodes].some((node) => node.nodeType === Node.TEXT_NODE && node.textContent.trim());
      const measuresText = ['INPUT', 'SELECT', 'TEXTAREA'].includes(element.tagName) || (element.tagName === 'BUTTON' && hasDirectText);
      if (measuresText && element.scrollWidth > element.clientWidth + 2 && style.textOverflow !== 'ellipsis') {
        clipped.push({
          tag: element.tagName.toLowerCase(),
          text: (element.getAttribute('aria-label') || element.textContent || element.value || '').trim().slice(0, 80),
          clientWidth: element.clientWidth,
          scrollWidth: element.scrollWidth,
        });
      }
    }

    return { documentOverflow, outside, clipped };
  });
}

async function capture(page, directory, name, fullPage = true) {
  await mkdir(directory, { recursive: true });
  await page.screenshot({ path: resolve(directory, `${name}.png`), fullPage, animations: 'disabled' });
  return inspectLayout(page);
}

test('landing 3D renders, responds to scroll, and fits the viewport', async ({ page }, testInfo) => {
  const directory = resolve(auditRoot, testInfo.project.name, 'public');
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  const canvas = page.locator('canvas').first();
  await expect(canvas).toBeVisible({ timeout: 20_000 });
  await expect(page.locator('.landing-three-scene')).toHaveClass(/is-ready/);

  const canvasBox = await canvas.boundingBox();
  expect(canvasBox).toBeTruthy();
  const firstFrame = await page.screenshot({ clip: canvasBox, animations: 'disabled' });
  const landingLayout = await capture(page, directory, 'landing-top', false);
  await page.mouse.move(canvasBox.x + canvasBox.width * 0.8, canvasBox.y + canvasBox.height * 0.3);
  await page.waitForTimeout(350);
  const secondFrame = await page.screenshot({ clip: canvasBox, animations: 'disabled' });
  const initialScrollProgress = Number(await page.locator('.landing-three-scene').getAttribute('data-scroll-progress'));
  await page.evaluate(() => {
    window.scrollTo({ top: Math.min(420, document.documentElement.scrollHeight - window.innerHeight), behavior: 'instant' });
    window.dispatchEvent(new Event('scroll'));
  });
  await expect.poll(async () => Number(await page.locator('.landing-three-scene').getAttribute('data-scroll-progress'))).toBeGreaterThan(initialScrollProgress);
  await page.screenshot({ path: resolve(directory, 'landing-scrolled.png'), fullPage: false, animations: 'disabled' });

  expect(firstFrame.length).toBeGreaterThan(5_000);
  expect(createHash('sha256').update(firstFrame).digest('hex')).not.toBe(createHash('sha256').update(secondFrame).digest('hex'));
  expect(landingLayout.documentOverflow).toBeLessThanOrEqual(1);
  expect(landingLayout.outside).toEqual([]);
  expect(landingLayout.clipped).toEqual([]);
});

test('login remains readable and contained', async ({ page }, testInfo) => {
  const directory = resolve(auditRoot, testInfo.project.name, 'public');
  await page.goto('/login', { waitUntil: 'domcontentloaded' });
  const layout = await capture(page, directory, 'login');
  expect(layout.documentOverflow).toBeLessThanOrEqual(1);
  expect(layout.outside).toEqual([]);
  expect(layout.clipped).toEqual([]);
});

for (const [panel, email] of roles) {
  test(`${panel} visual audit covers dashboard and every menu`, async ({ page }, testInfo) => {
    test.setTimeout(240_000);
    const directory = resolve(auditRoot, testInfo.project.name, panel);
    await login(page, panel, email);

    const links = await page.locator(`a[href^="/${panel}/module/"]`).evaluateAll((items) => [...new Set(items.map((item) => item.href))]);
    const routes = [page.url(), ...links];
    const report = [];

    for (const route of routes) {
      const response = await page.goto(route, { waitUntil: 'domcontentloaded' });
      await page.locator('main').waitFor({ state: 'visible' });
      await page.waitForTimeout(150);
      const layout = await capture(page, directory, slug(route));
      report.push({ route: new URL(route).pathname, status: response?.status() || 200, ...layout });
    }

    await writeFile(resolve(directory, 'report.json'), `${JSON.stringify(report, null, 2)}\n`);
    const failures = report.filter((entry) => entry.status >= 400 || entry.documentOverflow > 1 || entry.outside.length || entry.clipped.length);
    expect(failures).toEqual([]);
  });
}
