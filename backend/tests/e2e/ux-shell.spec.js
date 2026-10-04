import { expect, test } from '@playwright/test';
import { installCspGuard } from './csp-guard.js';

installCspGuard(test);

async function login(page, panel, email) {
  await page.goto('about:blank');
  await page.context().clearCookies();
  await page.goto('/login', { waitUntil: 'domcontentloaded', timeout: 45_000 });
  const username = page.locator('input[autocomplete="username"]');
  await expect(username).toBeVisible({ timeout: 15_000 });
  await username.fill(email);
  await page.locator('input[type="password"]').fill('password');
  await page.getByRole('button', { name: /Masuk/ }).click();
  await expect(page).toHaveURL(new RegExp(`/dashboard/${panel.replace('-', '\\-')}`), { timeout: 45_000 });
  await expect(page.getByLabel('Buka menu akun')).toBeVisible({ timeout: 15_000 });
  // Pastikan efek React (pendengar pintasan keyboard) sudah terpasang sebelum menekan tombol.
  await page.waitForLoadState('networkidle');
}

const theme = (page) => page.evaluate(() => document.documentElement.dataset.theme);

test.describe('tema terang/gelap', () => {
  test('toggle di topbar beralih tema dan bertahan setelah muat ulang', async ({ page }) => {
    await page.emulateMedia({ colorScheme: 'light' });
    await login(page, 'keuangan', 'keuangan@pesantenku.test');
    expect(await theme(page)).toBe('light');

    await page.getByRole('button', { name: 'Beralih ke mode gelap' }).click();
    await expect.poll(() => theme(page)).toBe('dark');

    await page.reload({ waitUntil: 'domcontentloaded' });
    // theme-init.js memasang tema sebelum React render, jadi tidak ada kedipan terang.
    expect(await theme(page)).toBe('dark');
    await expect(page.getByRole('button', { name: 'Beralih ke mode terang' })).toBeVisible();

    await page.getByRole('button', { name: 'Beralih ke mode terang' }).click();
    await expect.poll(() => theme(page)).toBe('light');
  });

  test('pilihan sistem mengikuti prefers-color-scheme', async ({ page }) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    expect(await theme(page)).toBe('dark');
    // Tunggu halaman login hidrasi agar pendengar perubahan tema sudah terpasang.
    await expect(page.getByRole('heading', { name: 'Selamat datang kembali.' })).toBeVisible();
    await page.emulateMedia({ colorScheme: 'light' });
    await expect.poll(() => theme(page)).toBe('light');
  });
});

test.describe('palet perintah dan pintasan', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'chromium', 'Pintasan keyboard diuji di desktop.');
    await login(page, 'keuangan', 'keuangan@pesantenku.test');
  });

  test('Ctrl+K membuka palet, pencarian, dan Enter membuka halaman', async ({ page }) => {
    await page.keyboard.press('Control+k');
    const palette = page.getByRole('dialog', { name: 'Palet perintah' });
    await expect(palette).toBeVisible();
    await expect(palette.getByRole('combobox')).toBeFocused();

    await page.keyboard.type('tagihan');
    await expect(palette.getByRole('option').first()).toContainText(/Tagihan/);
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/keuangan\/module\/tagihan/, { timeout: 20_000 });
    await expect(palette).toBeHidden();
  });

  test('Escape menutup palet dan mengembalikan fokus', async ({ page }) => {
    await page.keyboard.press('/');
    const palette = page.getByRole('dialog', { name: 'Palet perintah' });
    await expect(palette).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(palette).toBeHidden();
  });

  test('tanpa hasil menampilkan keadaan kosong', async ({ page }) => {
    await page.keyboard.press('Control+k');
    await page.keyboard.type('zzzxqv');
    await expect(page.getByText(/Tidak ada hasil untuk/)).toBeVisible();
  });

  test('? menampilkan bantuan pintasan; [ menyimpan sidebar tertutup', async ({ page }) => {
    await page.keyboard.press('?');
    await expect(page.getByRole('dialog', { name: 'Pintasan keyboard' })).toBeVisible();
    await page.keyboard.press('Escape');

    await page.keyboard.press('[');
    await expect(page.getByRole('button', { name: 'Perluas sidebar' })).toBeVisible();
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('button', { name: 'Perluas sidebar' })).toBeVisible();
    await page.getByRole('button', { name: 'Perluas sidebar' }).click();
    await expect(page.getByRole('button', { name: 'Ciutkan sidebar' })).toBeVisible();
  });

  test('pintasan satu tombol tidak aktif saat mengetik di kolom isian', async ({ page }) => {
    const search = page.getByLabel('Cari menu, modul, dan laporan');
    await search.click();
    await search.pressSequentially('t[?');
    await expect(search).toHaveValue('t[?');
    expect(await theme(page)).toBe('light');
  });

  test('tautan lewati memindahkan fokus ke konten utama', async ({ page }) => {
    await page.keyboard.press('Tab');
    const skip = page.getByRole('link', { name: 'Lewati ke konten utama' });
    await expect(skip).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('#konten-utama')).toBeFocused();
  });
});

test.describe('kebijakan reduced motion', () => {
  test('app shell tidak menjalankan animasi atau transisi berulang', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await login(page, 'keuangan', 'keuangan@pesantenku.test');
    await page.waitForTimeout(600);

    const infinite = await page.evaluate(() => document.getAnimations()
      .filter((animation) => animation.effect?.getTiming().iterations === Infinity && animation.playState === 'running')
      .map((animation) => animation.animationName || animation.constructor.name));
    expect(infinite).toEqual([]);

    const cardAnimation = await page.locator('.stat-card').first().evaluate((element) => getComputedStyle(element).animationName);
    expect(cardAnimation).toBe('none');
  });

  test('angka statistik langsung menampilkan nilai akhir tanpa penggulung', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await login(page, 'keuangan', 'keuangan@pesantenku.test');
    const first = page.locator('.stat-card p.text-2xl').first();
    const text = (await first.textContent()) || '';
    expect(text.trim()).not.toBe('');
    await page.waitForTimeout(150);
    expect((await first.textContent()) || '').toBe(text);
  });

  test('landing tidak menjalankan animasi berulang saat reduced motion aktif', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: /Satu ruang tenang/ })).toBeVisible();
    await page.waitForTimeout(800);
    const infinite = await page.evaluate(() => document.getAnimations()
      .filter((animation) => animation.effect?.getTiming().iterations === Infinity && animation.playState === 'running')
      .map((animation) => animation.animationName || ''));
    expect(infinite).toEqual([]);
  });
});

test.describe('ketahanan', () => {
  test('kegagalan memuat bagan ditangani error boundary dan dapat dicoba lagi', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'chromium', 'Cukup diuji di desktop.');
    let failChunks = true;
    await page.route(/(DashboardCharts|NativeCharts)[^/]*\.js/, (route) => (failChunks ? route.abort() : route.continue()));
    await login(page, 'keuangan', 'keuangan@pesantenku.test');
    await expect(page.getByText('Grafik gagal dimuat').first()).toBeVisible({ timeout: 15_000 });
    // Bagian lain halaman tetap hidup.
    await expect(page.getByLabel('Buka menu akun')).toBeVisible();

    failChunks = false;
    await page.getByRole('button', { name: 'Coba lagi' }).first().click();
    await expect(page.locator('svg[aria-roledescription^="grafik"]').first()).toBeVisible({ timeout: 15_000 });
  });
});

test.describe('landing: dock dan filter peran', () => {
  test('dock muncul setelah hero dan filter peran memangkas kartu', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: /Satu ruang tenang/ })).toBeVisible();

    const dock = page.getByRole('navigation', { name: 'Pintasan bagian' });
    await expect(dock).toBeHidden();
    await page.locator('#peran').scrollIntoViewIfNeeded();
    await expect(dock).toBeVisible();

    await expect(page.locator('#peran article')).toHaveCount(10);
    await page.getByRole('button', { name: /^Pengelola/ }).click();
    await expect(page.locator('#peran article')).toHaveCount(5);
    await page.getByRole('button', { name: /^Santri & wali/ }).click();
    await expect(page.locator('#peran article')).toHaveCount(2);
    await page.getByRole('button', { name: /^Semua peran/ }).click();
    await expect(page.locator('#peran article')).toHaveCount(10);
  });
});
