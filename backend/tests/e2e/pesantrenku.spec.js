import { expect, test } from '@playwright/test';
import crypto from 'node:crypto';

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

function navigate(page, url) {
  return page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45_000 });
}

async function login(page, panel, email) {
  await page.context().clearCookies();
  await navigate(page, '/login');
  await page.locator('input[autocomplete="username"]').fill(email);
  await page.locator('input[type="password"]').fill('password');
  await Promise.all([
    page.waitForURL(new RegExp(`/dashboard/${panel.replace('-', '\\-')}`), { waitUntil: 'domcontentloaded', timeout: 45_000 }),
    page.getByRole('button', { name: 'Masuk' }).click(),
  ]);
  await page.waitForLoadState('domcontentloaded');
}

async function apiJson(page, path, options = {}) {
  return page.evaluate(async ({ path, options }) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const response = await fetch(path, {
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
      },
      ...options,
    });
    const text = await response.text();
    return { ok: response.ok, status: response.status, body: text ? JSON.parse(text) : {} };
  }, { path, options });
}

function totp(secret) {
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  const bits = [...secret].map((character) => alphabet.indexOf(character).toString(2).padStart(5, '0')).join('');
  const bytes = Buffer.from((bits.match(/.{8}/g) || []).map((byte) => parseInt(byte, 2)));
  const counter = Math.floor(Date.now() / 30000);
  const counterBuffer = Buffer.alloc(8);
  counterBuffer.writeBigUInt64BE(BigInt(counter));
  const digest = crypto.createHmac('sha1', bytes).update(counterBuffer).digest();
  const offset = digest[digest.length - 1] & 0x0f;
  const value = (digest.readUInt32BE(offset) & 0x7fffffff) % 1000000;
  return String(value).padStart(6, '0');
}

for (const [panel, email] of roles) {
  test(`${panel} menus, header, notification, and login guard work`, async ({ page }, testInfo) => {
    test.skip(!testInfo.project.metadata.roles?.includes(panel), `Role ${panel} dijalankan oleh shard lain.`);
    await login(page, panel, email);
    await page.getByLabel('Buka menu akun').click();
    await expect(page.getByText('Logout', { exact: true })).toBeVisible();
    await page.keyboard.press('Escape');
    await page.getByLabel('Buka notifikasi').click();
    await expect(page.locator('header').getByText('Notifikasi', { exact: true })).toBeVisible();

    await navigate(page, '/login');
    await expect(page).toHaveURL(new RegExp(`/dashboard/${panel.replace('-', '\\-')}`));
    await navigate(page, `/dashboard/${panel}`);
    await page.locator(`a[href^="/${panel}/module/"]`).first().waitFor({ state: 'attached', timeout: 10_000 });

    const links = await page.locator(`a[href^="/${panel}/module/"]`).evaluateAll((items) => {
      const seen = new Set();
      return items
        .map((item) => item.getAttribute('href'))
        .filter((href) => href && !seen.has(href) && seen.add(href));
    });
    expect(links.length).toBeGreaterThan(0);

    for (const href of links) {
      const response = await navigate(page, href);
      const body = await page.locator('body').innerText();
      expect(response?.status() || 200).toBeLessThan(400);
      expect(body).not.toMatch(/404|Not Found|Halaman tidak ditemukan/i);
      const createButton = page.getByRole('button', { name: /Tambah|Ajukan|Konfirmasi|Input|Catat|Buat|Update Stok|Tambah Jadwal/i }).first();
      if (await createButton.isVisible({ timeout: 500 }).catch(() => false)) {
        await createButton.click();
        await expect(page.locator('[role="dialog"]')).toBeVisible();
        await page.getByLabel('Tutup modal').click();
      }
    }
  });
}

test('owner can open and save permission matrix and PDF template editor', async ({ page }, testInfo) => {
  test.skip(!testInfo.project.metadata.workflows, 'Workflow suite dijalankan pada shard utama tiap viewport.');
  await login(page, 'owner', 'owner@pesantenku.test');
  await navigate(page, '/owner/module/user-dan-role');
  await page.getByRole('button', { name: 'Permission' }).click();
  await expect(page.getByRole('heading', { name: 'Permission Matrix' })).toBeVisible();
  await Promise.all([
    page.waitForResponse((response) => response.url().includes('/web-api/permissions/matrix') && response.request().method() !== 'GET' && response.status() === 200),
    page.getByRole('button', { name: 'Simpan Permission' }).click(),
  ]);
  await expect(page.getByText('Permission berhasil diperbarui.')).toBeVisible({ timeout: 20_000 });
  await page.getByLabel('Tutup modal').click();

  await navigate(page, '/owner/module/pengaturan');
  await page.getByRole('button', { name: 'Template PDF' }).click();
  await expect(page.getByRole('heading', { name: 'Template PDF Tenant' })).toBeVisible();
  const [templateResponse] = await Promise.all([
    page.waitForResponse((response) => response.url().includes('/web-api/system-settings') && ['POST', 'PATCH'].includes(response.request().method())),
    page.getByRole('button', { name: 'Simpan Template' }).click(),
  ]);
  expect(templateResponse.ok()).toBeTruthy();
  await expect(page.getByText(/Request gagal|Data tidak valid|Role tidak memiliki akses/i)).toHaveCount(0);
  const close = page.getByLabel('Tutup modal').first();
  if (await close.isVisible({ timeout: 500 }).catch(() => false)) {
    await close.click();
  }
});

test('tu import preview mapping works', async ({ page }, testInfo) => {
  test.skip(!testInfo.project.metadata.workflows, 'Workflow suite dijalankan pada shard utama tiap viewport.');
  await login(page, 'tu', 'tu@pesantenku.test');
  await navigate(page, '/tu/module/santri');
  await page.getByRole('button', { name: 'Import Excel' }).click();
  await page.locator('input[type="file"]').setInputFiles({
    name: 'students.csv',
    mimeType: 'text/csv',
    buffer: Buffer.from('nis,name,gender,status\nE2E-001,Santri E2E,Laki-laki,active\n'),
  });
  await page.getByRole('button', { name: 'Preview' }).click();
  await expect(page.getByText('Mapping Kolom')).toBeVisible();
  await expect(page.getByText('Valid')).toBeVisible();
});

test('workflow endpoints and queued export are operational', async ({ page }, testInfo) => {
  test.skip(!testInfo.project.metadata.workflows, 'Workflow suite dijalankan pada shard utama tiap viewport.');
  await login(page, 'musyrif', 'musyrif@pesantenku.test');
  await navigate(page, '/musyrif/module/perizinan');
  const reject = page.locator('button[title="Tolak"]').first();
  if (await reject.isVisible({ timeout: 2000 }).catch(() => false)) {
    await reject.click();
    await page.locator('textarea').fill('Catatan E2E wajib.');
    await page.getByRole('button', { name: 'Simpan Status' }).click();
    await expect(page.getByText(/Status berhasil|ditolak/i)).toBeVisible();
  }

  await login(page, 'keuangan', 'keuangan@pesantenku.test');
  const queued = await apiJson(page, '/web-api/payments/export/queue', {
    method: 'POST',
    body: JSON.stringify({ format: 'excel' }),
  });
  expect(queued.status).toBe(202);
  expect(queued.body.job.uuid).toBeTruthy();

  const status = await apiJson(page, `/web-actions/jobs/${queued.body.job.uuid}`);
  expect(status.ok).toBeTruthy();
  expect(status.body.status).toMatch(/queued|processing|completed/);
});

test('owner scaling tools custom role queue monitor download manager and restore are usable', async ({ page }, testInfo) => {
  test.skip(!testInfo.project.metadata.workflows, 'Workflow suite dijalankan pada shard utama tiap viewport.');
  await login(page, 'owner', 'owner@pesantenku.test');
  await navigate(page, '/owner/module/user-dan-role');

  await page.getByRole('button', { name: 'Custom Role' }).click();
  await expect(page.getByRole('heading', { name: 'Custom Role Tenant' })).toBeVisible();
  await page.getByLabel('Tutup modal').click();

  await page.getByRole('button', { name: 'Queue Monitor' }).click();
  await expect(page.getByRole('heading', { name: 'Queue Monitor' })).toBeVisible();
  await page.getByLabel('Tutup modal').click();

  const [exportResponse] = await Promise.all([
    page.waitForResponse((response) => response.url().includes('/export/queue') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Export Job' }).click(),
  ]);
  expect(exportResponse.status()).toBe(202);
  await page.getByRole('button', { name: 'Download Manager' }).click();
  await expect(page.getByRole('heading', { name: 'Download Manager' })).toBeVisible();
  await expect(page.getByRole('dialog').getByText('export memberships', { exact: true }).first()).toBeVisible();
  await page.getByLabel('Tutup modal').click();

  await navigate(page, '/owner/module/backup-dan-restore');
  const [backupResponse] = await Promise.all([
    page.waitForResponse((response) => response.url().includes('/backup/run') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Jalankan Backup' }).click(),
  ]);
  expect(backupResponse.status()).toBe(200);
  const backupId = (await backupResponse.json()).backup_id;
  const backupList = await apiJson(page, '/web-api/backups?per_page=20');
  const backup = backupList.body.data.find((row) => row.id === backupId);
  expect(backup?.name).toBeTruthy();
  const backupRow = page.getByRole('row').filter({ hasText: backup.name });
  await expect(backupRow).toBeVisible();
  const restore = backupRow.locator('button[title="Restore Backup"]');
  await expect(restore).toBeVisible();
  await restore.click();
  await expect(page.getByRole('heading', { name: 'Ajukan Restore Backup' })).toBeVisible();
  await page.getByRole('dialog').getByPlaceholder('Alasan restore dan referensi insiden').fill('Audit E2E meminta pemulihan snapshot tenant terenkripsi.');
  await page.getByRole('dialog').getByPlaceholder('Kode MFA 6 digit').fill(totp('JBSWY3DPEHPK3PXP'));
  await page.getByRole('dialog').getByPlaceholder('RESTORE', { exact: true }).fill('RESTORE');
  const [restoreResponse] = await Promise.all([
    page.waitForResponse((response) => /\/backup\/\d+\/restore$/.test(new URL(response.url()).pathname) && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Ajukan Pemulihan' }).click(),
  ]);
  expect(restoreResponse.status()).toBe(202);

  await login(page, 'super-admin', 'superadmin@pesantenku.test');
  await navigate(page, '/super-admin/module/backups');
  await page.getByRole('button', { name: 'Persetujuan Restore' }).click();
  await expect(page.getByRole('heading', { name: 'Persetujuan Restore' })).toBeVisible();
  const approvalDialog = page.getByRole('dialog');
  await approvalDialog.getByPlaceholder('Kode MFA super admin').fill(totp('KRUGS4ZANFZSAYJA'));
  const [approvalResponse] = await Promise.all([
    page.waitForResponse((response) => response.url().includes('/approve') && response.request().method() === 'POST'),
    approvalDialog.getByRole('button', { name: 'Setujui' }).first().click(),
  ]);
  expect(approvalResponse.status()).toBe(200);
  const [executeResponse] = await Promise.all([
    page.waitForResponse((response) => response.url().includes('/execute') && response.request().method() === 'POST'),
    approvalDialog.getByRole('button', { name: 'Eksekusi' }).first().click(),
  ]);
  expect(executeResponse.status()).toBe(200);
});

test('observability realtime and responsive runtime are healthy', async ({ page }, testInfo) => {
  test.skip(!testInfo.project.metadata.workflows, 'Workflow suite dijalankan pada shard utama tiap viewport.');
  await login(page, 'owner', 'owner@pesantenku.test');
  const response = await navigate(page, '/owner/module/user-dan-role');
  expect(response?.headers()['x-correlation-id']).toBeTruthy();

  await expect.poll(() => page.evaluate(() => window.__pesantenkuRealtime?.state), { timeout: 15_000 }).toBe('connected');
  await expect.poll(() => page.evaluate(() => performance.getEntriesByType('resource').some((entry) => entry.name.includes('AnalyticsPanel-')))).toBeTruthy();

  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow).toBeLessThanOrEqual(1);

  const metrics = await page.evaluate(async () => {
    const response = await fetch('/metrics', { credentials: 'same-origin' });
    return { ok: response.ok, text: await response.text() };
  });
  expect(metrics.ok).toBeTruthy();
  expect(metrics.text).toContain('pesantenku_http_requests_total');
});
