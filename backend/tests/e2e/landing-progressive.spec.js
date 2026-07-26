import { expect, test } from '@playwright/test';
import { installCspGuard } from './csp-guard.js';

installCspGuard(test);
test('landing remains usable while the Three.js runtime loads progressively', async ({ page }) => {
  let releaseRuntime;
  await page.route(/LandingThreeRuntime[^/]*\.js/, async (route) => {
    await new Promise((resolve) => {
      releaseRuntime = resolve;
    });
    await route.continue();
  });

  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await expect(page.getByRole('heading', { name: /Satu ruang tenang/ })).toBeVisible();
  await expect(page.locator('.landing-three-scene')).toHaveClass(/is-loading/);
  await expect(page.getByText('Menyiapkan kampus')).toBeVisible();
  await expect(page.locator('.landing-three-scene canvas')).toHaveCount(0);
  await expect.poll(() => Boolean(releaseRuntime)).toBe(true);

  releaseRuntime();
  await expect(page.locator('.landing-three-scene canvas')).toBeVisible({ timeout: 20_000 });
  await expect(page.locator('.landing-three-scene')).toHaveClass(/is-ready/);
});

test('landing uses its static fallback without downloading Three.js when WebGL is unavailable', async ({ page }) => {
  await page.addInitScript(() => {
    const getContext = HTMLCanvasElement.prototype.getContext;
    HTMLCanvasElement.prototype.getContext = function patchedGetContext(type, ...args) {
      if (type === 'webgl' || type === 'webgl2') return null;
      return getContext.call(this, type, ...args);
    };
  });

  let runtimeRequested = false;
  page.on('request', (request) => {
    if (request.url().includes('LandingThreeRuntime')) runtimeRequested = true;
  });

  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await expect(page.getByRole('heading', { name: /Satu ruang tenang/ })).toBeVisible();
  await expect(page.locator('.landing-three-scene')).toHaveClass(/is-fallback/);
  await expect(page.locator('.landing-three-fallback')).toBeVisible();
  expect(runtimeRequested).toBe(false);
});

test('landing exposes module, role, and workflow sections without unverified audit content', async ({ page }) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  await expect(page.getByRole('heading', { name: /Modul yang saling terhubung/ })).toBeVisible();
  await expect(page.getByRole('heading', { name: /Satu sistem, banyak peran/ })).toBeVisible();
  await expect(page.getByRole('heading', { name: /Kerja yang semestinya mengalir/ })).toBeVisible();
  await expect(page.locator('#peran article')).toHaveCount(10);
  await expect(page.getByText('Admin Keuangan', { exact: true })).toBeVisible();
  await expect(page.getByText('Wali Murid', { exact: true })).toBeVisible();
  await expect(page.getByText(/production-ready/i)).toHaveCount(0);
  await expect(page.getByText(/status sinkronisasi/i)).toHaveCount(0);
});

test('mobile landing navigation closes with Escape and restores focus', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'mobile-chromium', 'Regresi ini khusus navigasi landing seluler.');

  await page.goto('/', { waitUntil: 'domcontentloaded' });
  const menuButton = page.getByRole('button', { name: 'Buka menu', exact: true });
  const mobileMenu = page.getByRole('navigation', { name: 'Navigasi seluler' });

  await expect(menuButton).toHaveAttribute('aria-expanded', 'false');
  await menuButton.click();
  await expect(menuButton).toHaveAttribute('aria-expanded', 'true');
  await expect(mobileMenu).toBeVisible();
  await expect(mobileMenu.getByRole('link', { name: 'Modul', exact: true })).toBeFocused();

  await page.keyboard.press('Escape');
  await expect(mobileMenu).toBeHidden();
  await expect(menuButton).toBeFocused();
});
