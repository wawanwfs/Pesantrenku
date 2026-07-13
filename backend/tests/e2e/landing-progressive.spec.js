import { expect, test } from '@playwright/test';

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
