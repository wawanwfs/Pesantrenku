import React, { useEffect, useMemo, useRef, useState } from 'react';
import { router, icons } from '../../lib/deps.jsx';
import { panels } from './appSupport.jsx';
import { useDialogLifecycle } from './AppDialogs.jsx';
import { useTheme } from '../../lib/theme.js';
import { useShell } from './ShellContext.jsx';

const RECENT_KEY = 'pk-palette-recent';
const MAX_RECENT = 5;

function readRecent() {
  try {
    const parsed = JSON.parse(window.localStorage.getItem(RECENT_KEY) || '[]');
    return Array.isArray(parsed) ? parsed.filter((item) => typeof item === 'string').slice(0, MAX_RECENT) : [];
  } catch {
    return [];
  }
}

function rememberRecent(id) {
  try {
    const next = [id, ...readRecent().filter((item) => item !== id)].slice(0, MAX_RECENT);
    window.localStorage.setItem(RECENT_KEY, JSON.stringify(next));
  } catch {
    // Penyimpanan opsional; palet tetap bekerja tanpa riwayat.
  }
}

/** Skor kecocokan sederhana: awal kata lebih tinggi daripada substring; semua token wajib cocok. */
function scoreItem(item, tokens) {
  const label = item.label.toLowerCase();
  const haystack = `${label} ${item.keywords || ''}`.toLowerCase();
  let score = 0;
  for (const token of tokens) {
    const at = haystack.indexOf(token);
    if (at < 0) return -1;
    score += label.startsWith(token) ? 6 : label.includes(` ${token}`) ? 4 : at < label.length ? 2 : 1;
  }
  return score;
}

function Highlight({ text, tokens }) {
  if (!tokens.length) return text;
  const lower = text.toLowerCase();
  const marks = new Array(text.length).fill(false);
  tokens.forEach((token) => {
    let from = lower.indexOf(token);
    while (from >= 0) {
      for (let i = from; i < from + token.length; i += 1) marks[i] = true;
      from = lower.indexOf(token, from + token.length);
    }
  });
  const parts = [];
  let i = 0;
  while (i < text.length) {
    let j = i;
    while (j < text.length && marks[j] === marks[i]) j += 1;
    parts.push(marks[i] ? <mark key={i} className="pk-palette-mark">{text.slice(i, j)}</mark> : text.slice(i, j));
    i = j;
  }
  return parts;
}

export default function CommandPalette({ open, onClose, panel, auth }) {
  const [query, setQuery] = useState('');
  const [active, setActive] = useState(0);
  const [recent, setRecent] = useState([]);
  const inputRef = useRef(null);
  const listRef = useRef(null);
  const dialogRef = useDialogLifecycle(onClose, open);
  const { resolved, toggle } = useTheme();
  const { openGuide } = useShell();
  const memberships = Array.isArray(auth?.memberships) ? auth.memberships : Object.values(auth?.memberships || {});
  const activeTenantId = auth?.active_pesantren?.id;

  const items = useMemo(() => {
    const pages = (panels[panel]?.menu || []).map(([label, icon, href]) => ({
      id: `page:${label === 'Dashboard' ? `/dashboard/${panel}` : href}`,
      group: 'Halaman',
      label,
      icon,
      hint: label === 'Dashboard' ? 'Ringkasan' : 'Buka halaman',
      run: () => router.visit(label === 'Dashboard' ? `/dashboard/${panel}` : href),
    }));
    const actions = [
      {
        id: 'action:theme', group: 'Tindakan', label: resolved === 'dark' ? 'Beralih ke mode terang' : 'Beralih ke mode gelap',
        icon: resolved === 'dark' ? 'Sun' : 'Moon', keywords: 'tema tampilan gelap terang dark light', hint: 'T', run: toggle, keepOpen: false,
      },
      { id: 'action:guide', group: 'Tindakan', label: 'Tampilkan panduan singkat', icon: 'Inbox', keywords: 'bantuan tutorial panduan onboarding', hint: 'Pengenalan', run: openGuide },
      { id: 'action:logout', group: 'Tindakan', label: 'Keluar dari akun', icon: 'LogOut', keywords: 'logout keluar sign out', hint: 'Akhiri sesi', run: () => router.post('/logout') },
    ];
    const tenants = memberships
      .filter((item) => String(item.id) !== String(activeTenantId))
      .map((item) => ({
        id: `tenant:${item.id}`, group: 'Pesantren', label: `Pindah ke ${item.name || item.pesantren}`, icon: 'School',
        keywords: 'pesantren tenant ganti', hint: item.role_label || 'Ganti pesantren aktif',
        run: () => router.post('/tenant/switch', { pesantren_id: Number(item.id) }, { preserveScroll: true }),
      }));
    return [...pages, ...tenants, ...actions];
  }, [panel, memberships.length, activeTenantId, resolved, toggle, openGuide]);

  const tokens = useMemo(() => query.trim().toLowerCase().split(/\s+/).filter(Boolean), [query]);

  const results = useMemo(() => {
    if (!tokens.length) {
      const recents = recent.map((id) => items.find((item) => item.id === id)).filter(Boolean).map((item) => ({ ...item, group: 'Terakhir dibuka' }));
      const rest = items.filter((item) => !recents.some((r) => r.id === item.id));
      return [...recents, ...rest];
    }
    return items
      .map((item) => ({ item, score: scoreItem(item, tokens) }))
      .filter(({ score }) => score >= 0)
      .sort((a, b) => b.score - a.score)
      .map(({ item }) => item);
  }, [items, tokens, recent]);

  useEffect(() => {
    setQuery('');
    setActive(0);
    if (open) setRecent(readRecent());
  }, [open]);

  useEffect(() => setActive(0), [query]);

  useEffect(() => {
    listRef.current?.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest' });
  }, [active, results]);

  if (!open) return null;

  const choose = (item) => {
    if (!item) return;
    if (item.id.startsWith('page:')) rememberRecent(item.id);
    onClose();
    // Beri kesempatan dialog menutup dan fokus kembali sebelum navigasi.
    window.setTimeout(() => item.run(), 0);
  };

  const onKeyDown = (event) => {
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      setActive((index) => (results.length ? (index + 1) % results.length : 0));
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      setActive((index) => (results.length ? (index - 1 + results.length) % results.length : 0));
    } else if (event.key === 'Home') {
      event.preventDefault();
      setActive(0);
    } else if (event.key === 'End') {
      event.preventDefault();
      setActive(Math.max(0, results.length - 1));
    } else if (event.key === 'Enter') {
      event.preventDefault();
      choose(results[active]);
    }
  };

  let lastGroup = '';

  return (
    <div className="pk-palette" role="presentation">
      <button type="button" className="pk-palette-backdrop" onClick={onClose} aria-label="Tutup palet perintah" tabIndex={-1} />
      <div ref={dialogRef} className="pk-palette-panel" role="dialog" aria-modal="true" aria-label="Palet perintah">
        <div className="pk-palette-search">
          <icons.Search className="h-4 w-4 shrink-0" aria-hidden="true" />
          <input
            ref={inputRef}
            autoFocus
            data-dialog-initial-focus
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            onKeyDown={onKeyDown}
            role="combobox"
            aria-expanded="true"
            aria-controls="pk-palette-list"
            aria-activedescendant={results[active] ? `pk-palette-option-${active}` : undefined}
            aria-autocomplete="list"
            autoComplete="off"
            spellCheck="false"
            placeholder="Cari halaman, tindakan, atau pesantren..."
          />
          <kbd className="pk-kbd">Esc</kbd>
        </div>
        <div id="pk-palette-list" ref={listRef} className="pk-palette-list" role="listbox" aria-label="Hasil pencarian">
          {results.length === 0 && (
            <div className="pk-palette-empty" role="status">
              <icons.Search className="h-5 w-5" aria-hidden="true" />
              <p>Tidak ada hasil untuk &ldquo;{query}&rdquo;</p>
              <span>Coba kata kunci lain, mis. nama menu atau &ldquo;tema&rdquo;.</span>
            </div>
          )}
          {results.map((item, index) => {
            const Icon = icons[item.icon] || icons.CircleCheck;
            const showGroup = item.group !== lastGroup;
            lastGroup = item.group;
            return (
              <React.Fragment key={item.id}>
                {showGroup && <div className="pk-palette-group" role="presentation">{item.group}</div>}
                <div
                  id={`pk-palette-option-${index}`}
                  role="option"
                  aria-selected={index === active}
                  className={`pk-palette-item ${index === active ? 'is-active' : ''}`}
                  onMouseMove={() => active !== index && setActive(index)}
                  onClick={() => choose(item)}
                >
                  <span className="pk-palette-icon"><Icon className="h-4 w-4" aria-hidden="true" /></span>
                  <span className="min-w-0 flex-1 truncate"><Highlight text={item.label} tokens={tokens} /></span>
                  <span className="pk-palette-hint">{item.hint}</span>
                </div>
              </React.Fragment>
            );
          })}
        </div>
        <div className="pk-palette-foot" aria-hidden="true">
          <span><kbd className="pk-kbd">↑</kbd><kbd className="pk-kbd">↓</kbd> pilih</span>
          <span><kbd className="pk-kbd">Enter</kbd> buka</span>
          <span className="ml-auto"><kbd className="pk-kbd">?</kbd> pintasan</span>
        </div>
      </div>
    </div>
  );
}
