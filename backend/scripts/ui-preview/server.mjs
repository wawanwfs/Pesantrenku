// Server preview UI tanpa PHP/DB: menyajikan hasil `npm run build` dengan props Inertia tiruan.
// Dipakai di lingkungan yang tidak memiliki PHP 8.4 (mis. sesi cloud). Bukan untuk produksi.
//
//   npm run build && node scripts/ui-preview/server.mjs [port]
//
// Rute: /  (landing), /login, /dashboard/{panel}, /{panel}/module/{module}.
import { createServer } from 'node:http';
import { readFile, stat } from 'node:fs/promises';
import { extname, join, normalize, resolve } from 'node:path';
import { dashboardFor, notifications, panelRole, sharedData, sharedProps, studentRows } from './fixtures.mjs';

const root = resolve(import.meta.dirname, '../..');
const publicDir = join(root, 'public');
const port = Number(process.argv[2] || process.env.PORT || 4173);
const types = {
  '.js': 'text/javascript', '.css': 'text/css', '.json': 'application/json', '.svg': 'image/svg+xml',
  '.png': 'image/png', '.ico': 'image/x-icon', '.woff2': 'font/woff2', '.txt': 'text/plain',
};

// Manifest dibaca ulang tiap permintaan HTML agar `npm run build` ulang langsung terlihat.
async function loadManifest() {
  return JSON.parse(await readFile(join(publicDir, 'build/manifest.json'), 'utf8'));
}
const authenticated = true;

function page(component, props, url) {
  return { component, props, url, version: 'preview' };
}

function resolvePage(url, authenticated) {
  const { pathname } = new URL(url, 'http://preview.local');
  if (pathname === '/') return page('Landing', sharedProps('super-admin', { authenticated: false }), url);
  if (pathname === '/login') return page('Login', sharedProps('super-admin', { authenticated: false }), url);
  let match = pathname.match(/^\/dashboard\/([a-z-]+)$/);
  if (match && panelRole[match[1]]) {
    return page('App', {
      ...sharedProps(match[1]), screen: 'dashboard', panel: match[1],
      dashboard: dashboardFor(match[1]), shared: sharedData(match[1]),
    }, url);
  }
  match = pathname.match(/^\/([a-z-]+)\/module\/([a-z-]+)$/);
  if (match && panelRole[match[1]]) {
    return page('App', {
      ...sharedProps(match[1]), screen: 'module', panel: match[1], module: match[2],
      dashboard: dashboardFor(match[1]), shared: sharedData(match[1]),
    }, url);
  }
  return undefined;
}

function html(pageObject, manifest) {
  const entry = manifest['resources/js/app.jsx'];
  const cssEntry = manifest['resources/css/app.css'];
  const json = JSON.stringify(pageObject).replace(/</g, '\\u003c');
  const css = [cssEntry.file, ...(cssEntry.css || [])].filter((file) => file.endsWith('.css'));
  const imports = [entry.file];
  return `<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="preview"><title>Pesantrenku</title>
<script src="/theme-init.js"></script>
${[...new Set([...css, ...(entry.css || [])])].map((file) => `<link rel="stylesheet" href="/build/${file}">`).join('\n')}
${imports.map((file) => `<script type="module" src="/build/${file}"></script>`).join('\n')}
</head><body class="font-sans antialiased"><script data-page="app" type="application/json">${json}</script><div id="app"></div></body></html>`;
}

async function serveFile(res, filePath) {
  try {
    const info = await stat(filePath);
    if (!info.isFile()) throw new Error('not file');
    res.writeHead(200, { 'Content-Type': types[extname(filePath)] || 'application/octet-stream' });
    res.end(await readFile(filePath));
    return true;
  } catch {
    return false;
  }
}

const server = createServer(async (req, res) => {
  const url = new URL(req.url, 'http://preview.local');
  const csp = "default-src 'self'; base-uri 'self'; object-src 'none'; connect-src 'self'; img-src 'self' data: blob:; style-src 'self' https://fonts.bunny.net; script-src 'self'; font-src 'self' data: https://fonts.bunny.net; form-action 'self'; frame-ancestors 'self'";
  res.setHeader('Content-Security-Policy', csp);

  if (url.pathname.startsWith('/build/') || ['/theme-init.js', '/favicon.ico'].includes(url.pathname)) {
    const target = normalize(join(publicDir, url.pathname));
    if (target.startsWith(publicDir) && await serveFile(res, target)) return;
    res.writeHead(404).end();
    return;
  }
  if (url.pathname === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' }).end('{"status":"ok"}');
    return;
  }
  if (url.pathname === '/web-actions/notifications') {
    res.writeHead(200, { 'Content-Type': 'application/json' }).end(JSON.stringify({ data: notifications, unread: notifications.length }));
    return;
  }
  if (url.pathname.startsWith('/web-api/')) {
    const rows = studentRows(8);
    res.writeHead(200, { 'Content-Type': 'application/json' }).end(JSON.stringify({
      data: rows, meta: { current_page: 1, last_page: 1, total: rows.length, per_page: 20 },
    }));
    return;
  }
  if (req.method === 'POST' && ['/login', '/logout', '/tenant/switch'].includes(url.pathname)) {
    res.writeHead(409, { 'X-Inertia-Location': url.pathname === '/login' ? '/dashboard/super-admin' : '/login' }).end();
    return;
  }

  const resolved = resolvePage(req.url, authenticated);
  if (resolved === undefined) {
    res.writeHead(404, { 'Content-Type': 'text/plain' }).end('Preview: halaman tidak dikenal');
    return;
  }
  if (resolved === null) {
    res.writeHead(302, { Location: '/dashboard/super-admin' }).end();
    return;
  }
  if (req.headers['x-inertia']) {
    res.writeHead(200, { 'Content-Type': 'application/json', 'X-Inertia': 'true', Vary: 'X-Inertia' }).end(JSON.stringify(resolved));
    return;
  }
  res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' }).end(html(resolved, await loadManifest()));
});

server.listen(port, '127.0.0.1', () => console.log(`UI preview: http://127.0.0.1:${port}`));
