import React, { useMemo } from 'react';
import { Link, router, icons } from '../../lib/deps.jsx';
import AnimatedNumber from '../AnimatedNumber.jsx';
import { useShell } from './ShellContext.jsx';
import { clampPercent, colors, panels, roleLabel, rowsOf, rupiah, tone } from './appSupport.jsx';
import { columnTitle, isVisibleTableColumn, PanelCard, renderCell, StatePanel } from './AppDialogs.jsx';

export function Header({ title, subtitle }) {
  const { panel } = useShell();
  const home = `/dashboard/${panel}`;
  const role = roleLabel(panel);
  return (
    <header className="page-heading border-b border-neutral-200 px-0.5 pb-4 pt-1">
      <nav aria-label="Jejak halaman" className="page-crumbs flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] font-semibold uppercase text-primary-700">
        <span className="h-1.5 w-1.5 bg-primary-600" aria-hidden="true" />
        <Link href={home} className="crumb-link">Pesantrenku</Link>
        <span className="text-neutral-300" aria-hidden="true">/</span>
        <span>{role}</span>
      </nav>
      <h1 className="mt-2 break-words text-2xl font-semibold leading-tight text-neutral-900 sm:text-[1.75rem]">{title}</h1>
      <p className="mt-1.5 max-w-4xl break-words text-sm leading-6 text-neutral-600">{subtitle}</p>
    </header>
  );
}

export function StatCard({ item }) {
  const Icon = icons[item.icon] || icons.Activity;
  const visualTones = ['success', 'info', 'warning', 'purple'];
  const visualTone = item.tone || visualTones[String(item.label || '').length % visualTones.length];
  return <article className={`stat-card stat-tone-${visualTone} min-w-0 border border-neutral-200 bg-white p-4`}><div className="flex items-start justify-between gap-3"><div className="min-w-0"><p className="break-words text-xs font-medium leading-5 text-neutral-500">{item.label}</p><p className="mt-1.5 break-words text-2xl font-semibold leading-tight text-neutral-900"><AnimatedNumber value={item.value} /></p></div><div className={`stat-icon grid h-10 w-10 shrink-0 place-items-center rounded-md ${tone[visualTone] || tone.success}`}><Icon className="h-[1.125rem] w-[1.125rem]" /></div></div><p className="mt-3 break-words border-t border-neutral-100 pt-2.5 text-xs font-medium leading-5 text-neutral-600">{item.trend}</p></article>;
}

export function ChartCard({ title, children }) {
  return <PanelCard title={title}>{children}</PanelCard>;
}

export function ChartLoading() {
  return (
    <div className="pk-chart-skeleton" role="status" aria-live="polite">
      <span className="sr-only">Memuat visualisasi data grafik</span>
      <div className="pk-chart-skeleton-bars" aria-hidden="true">
        {[46, 62, 38, 74, 58, 86, 68].map((height) => <i key={height} className={`pk-skeleton pk-bar-${height}`} />)}
      </div>
      <div className="pk-skeleton pk-chart-skeleton-axis" aria-hidden="true" />
    </div>
  );
}

export function DataPreview({ title, rows, columns, shared, href }) {
  return <PanelCard title={title} action="Lihat Semua" actionHref={href}><Table rows={rows} columns={columns} shared={shared} compact /></PanelCard>;
}

export function Table({ rows = [], empty, compact = false, columns: preferredColumns, shared = {}, meta, loading = false, selectable = false, selectedIds = [], onToggleRow, onToggleAll, onView, onEdit, onDelete, customActions = [] }) {
  const normalized = Array.isArray(rows) ? rows : Object.values(rows || {});
  const fieldMap = useMemo(() => Object.fromEntries((meta?.fields || []).map((field) => [field.name, field])), [meta]);
  const columns = useMemo(() => {
    const candidates = preferredColumns || Object.keys(normalized[0] || {}).filter(isVisibleTableColumn).slice(0, compact ? 5 : 7);
    return candidates.filter(isVisibleTableColumn);
  }, [rows, preferredColumns, compact]);
  const hasActions = onView || onEdit || onDelete || customActions.length > 0;
  const visibleRows = compact ? normalized.slice(0, 5) : normalized;
  const selectableIds = visibleRows.map((row) => row.id).filter(Boolean);
  const allSelected = selectableIds.length > 0 && selectableIds.every((id) => selectedIds.includes(id));
  if (loading) return <TableSkeleton columns={Math.max(3, columns.length || 4)} />;
  if (!normalized.length) return <StatePanel icon={empty?.icon} title={empty?.title || 'Belum ada data'} description={empty?.description || 'Data yang tersedia akan ditampilkan di tabel ini.'} action={empty?.action} />;
  return (
    <div className="min-w-0">
      <div className="mb-2 flex items-center gap-2 text-[11px] font-medium text-neutral-500 md:hidden" aria-hidden="true"><icons.MoveHorizontal className="h-3.5 w-3.5" /> Geser untuk melihat kolom lainnya</div>
      <div className="data-table-wrap relative min-w-0 overflow-x-auto border border-neutral-200 [scrollbar-width:thin]" role="region" aria-label="Tabel data; geser dengan panah keyboard bila kolom terpotong" tabIndex={0}>
      <table className="data-table w-full min-w-[640px] table-auto text-left text-sm">
        <thead><tr className="border-b border-neutral-200 bg-neutral-50 text-[11px] font-semibold uppercase text-neutral-500">{selectable && <th className="w-10 px-3 py-2.5"><input type="checkbox" checked={allSelected} onChange={(event) => onToggleAll?.(selectableIds, event.target.checked)} className="h-4 w-4 rounded accent-primary-600" aria-label="Pilih semua baris halaman ini" /></th>}{columns.map((c) => <th key={c} className="whitespace-nowrap px-3 py-2.5 font-semibold">{columnTitle(c)}</th>)}{hasActions && <th className="px-3 py-2.5 text-right font-semibold">Aksi</th>}</tr></thead>
        <tbody>{visibleRows.map((row, i) => {
          const visibleCustomActions = customActions.filter((action) => !action.show || action.show(row));
          return (
            <tr key={row.id || i} className="border-b border-neutral-100 transition-colors last:border-b-0 hover:bg-primary-50/35">
              {selectable && <td className="px-3 py-2.5"><input type="checkbox" checked={selectedIds.includes(row.id)} onChange={() => onToggleRow?.(row.id)} className="h-4 w-4 rounded accent-primary-600" aria-label={`Pilih baris ${row.id}`} /></td>}
              <>{columns.map((c) => <td key={c} className="max-w-72 px-3 py-2.5 align-top text-neutral-700">{renderCell(c, row[c], row, shared, fieldMap[c])}</td>)}</>
              {hasActions && <td className="table-actions sticky right-0 bg-white/95 px-3 py-2 shadow-[-10px_0_18px_-18px_rgba(15,23,42,0.8)] backdrop-blur-sm"><div className="flex flex-nowrap justify-end gap-1">{visibleCustomActions.map((action) => {
                const Icon = icons[action.icon] || icons.CircleCheck;
                return <button key={action.key} type="button" title={action.label} aria-label={action.label} onClick={() => action.onClick(row)} className={`grid min-h-9 min-w-9 place-items-center rounded-lg border transition focus:outline-none focus:ring-2 ${action.tone === 'danger' ? 'border-red-200 text-red-600 hover:border-red-500 hover:bg-red-50 focus:ring-red-200' : 'border-neutral-200 bg-white hover:border-primary-300 hover:bg-primary-50 hover:text-primary-700 focus:ring-primary-200'}`}><Icon className="h-4 w-4" /></button>;
              })}{onView && <button type="button" title="Lihat" aria-label="Lihat detail" onClick={() => onView(row)} className="grid min-h-9 min-w-9 place-items-center rounded-lg border border-neutral-200 bg-white transition hover:border-primary-300 hover:bg-primary-50 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-200"><icons.Eye className="h-4 w-4" /></button>}{onEdit && <button type="button" title="Edit" aria-label="Edit data" onClick={() => onEdit(row)} className="grid min-h-9 min-w-9 place-items-center rounded-lg border border-neutral-200 bg-white transition hover:border-primary-300 hover:bg-primary-50 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-200"><icons.Edit2 className="h-4 w-4" /></button>}{onDelete && <button type="button" title="Hapus" aria-label="Hapus data" onClick={() => onDelete(row)} className="grid min-h-9 min-w-9 place-items-center rounded-lg border border-neutral-200 bg-white transition hover:border-red-400 hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-200"><icons.Trash2 className="h-4 w-4" /></button>}</div></td>}
            </tr>
          );
        })}</tbody>
      </table>
      </div>
    </div>
  );
}

export function ActivityFeed({ items = [], emptyTitle }) {
  const normalized = rowsOf(items);
  if (!normalized.length) return <StatePanel title={emptyTitle} description="Informasi terbaru akan tampil di panel ini." compact />;
  return <div>{normalized.map((item, index) => <ActivityRow key={item.id || item.title || index} item={item} />)}</div>;
}

export function ActivityRow({ item }) {
  return <div className="activity-row flex min-w-0 gap-3 border-b border-neutral-100 py-3 last:border-b-0"><div className="grid h-8 w-8 shrink-0 place-items-center rounded-md bg-primary-50 text-primary-700"><icons.CircleCheck className="h-4 w-4" /></div><div className="min-w-0 flex-1"><div className="break-words text-sm font-semibold leading-5 text-neutral-800">{item.title}</div><div className="mt-0.5 break-words text-xs leading-5 text-neutral-500">{item.description}</div></div><div className="max-w-24 shrink-0 text-right text-[11px] font-medium leading-4 text-neutral-400">{item.time}</div></div>;
}

export function LegendList({ items }) {
  return items.length ? <div className="mt-1 space-y-1">{items.map((item, i) => <div key={item.name} className="flex items-start justify-between gap-3 rounded-md px-2 py-1.5 text-sm hover:bg-neutral-50"><span className="flex min-w-0 items-start gap-2"><ColorMarker color={colors[i % colors.length]} /><span className="break-words leading-5">{item.name}</span></span><span className="shrink-0 font-semibold text-neutral-700">{typeof item.value === 'number' && item.value > 100000 ? rupiah(item.value) : item.value}</span></div>)}</div> : <StatePanel title="Belum ada distribusi" description="Data visualisasi belum tersedia." compact />;
}

export function ProgressList({ items }) {
  return items.length ? <div className="space-y-4">{items.map((item, i) => {
    const percentage = clampPercent(item.value);
    return <div key={item.name}><div className="mb-1.5 flex items-start justify-between gap-3 text-sm"><span className="min-w-0 break-words font-medium leading-5 text-neutral-700">{item.name}</span><span className="shrink-0 font-semibold text-neutral-600">{percentage}%</span></div><progress className={`app-progress app-progress-tone-${i % colors.length}`} max="100" value={percentage} aria-label={`${item.name}: ${percentage}%`}>{percentage}%</progress></div>;
  })}</div> : <StatePanel title="Belum ada ringkasan" description="Metrik akan muncul setelah data tersedia." compact />;
}

export function ColorMarker({ color }) {
  return <svg className="mt-1.5 h-2.5 w-2.5 shrink-0" viewBox="0 0 10 10" width="10" height="10" aria-hidden="true"><rect width="10" height="10" rx="2" fill={color} /></svg>;
}

export function QuickActions({ items, panel }) {
  return (
    <div className="grid grid-cols-2 gap-3">
      {items.slice(0, 6).map((item) => {
        const href = quickActionHref(panel, item);
        return (
          <button key={item} type="button" onClick={() => href && router.visit(href)} className="group min-h-16 rounded-md border border-neutral-200 bg-neutral-50 p-3 text-left text-sm font-semibold leading-5 text-neutral-700 transition hover:border-primary-300 hover:bg-primary-50 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40">
            <span className="block break-words">{item}</span>
          </button>
        );
      })}
    </div>
  );
}

export function quickActionHref(panel, item) {
  const normalized = item.toLowerCase();
  const create = normalized.includes('tambah') || normalized.includes('input');
  const suffix = create ? '?action=create' : '';
  const routes = {
    owner: {
      'tambah user': `/owner/module/user-dan-role${suffix}`,
      'modul aktif': '/owner/module/modul-aktif',
      'paket': '/owner/module/paket-dan-subscription',
      'audit': '/owner/module/audit-tenant',
      'pengaturan': '/owner/module/pengaturan',
    },
    keuangan: {
      'tambah tagihan': `/keuangan/module/tagihan${suffix}`,
      'tambah pembayaran': `/keuangan/module/pembayaran${suffix}`,
      'pemasukan': '/keuangan/module/laporan-pemasukan',
      'tunggakan': '/keuangan/module/laporan-tunggakan',
      'pengaturan': '/keuangan/module/pengaturan-umum',
    },
    akademik: {
      'tambah kelas': `/akademik/module/kelas${suffix}`,
      'input nilai': `/akademik/module/penilaian${suffix}`,
      'jadwal': '/akademik/module/jadwal-pelajaran',
      'presensi': '/akademik/module/presensi',
      'laporan nilai': '/akademik/module/laporan-nilai',
      'pengaturan': '/akademik/module/pengaturan',
    },
    tu: {
      'tambah santri': `/tu/module/santri${suffix}`,
      'surat': '/tu/module/surat-menyurat',
      'mutasi': '/tu/module/mutasi-santri',
      'kamar': '/tu/module/kamar',
      'pengumuman': '/tu/module/pengumuman',
      'pengaturan': '/tu/module/pengaturan',
    },
  };

  const match = Object.entries(routes[panel] || {}).find(([key]) => normalized.includes(key));
  return match?.[1] || panels[panel]?.menu?.find(([label]) => normalized.includes(label.toLowerCase()))?.[2] || `/dashboard/${panel}`;
}

export function activityHref(panel) {
  return {
    'super-admin': '/super-admin/module/aktivitas-sistem',
    owner: '/owner/module/audit-tenant',
    keuangan: '/keuangan/module/tagihan',
    akademik: '/akademik/module/pengumuman',
    tu: '/tu/module/pengumuman',
  }[panel] || `/dashboard/${panel}`;
}

export function formatCell(value, key = '') {
  if (value === null || value === undefined || value === '') return '-';
  if (/(?:_at|_date|date|expires)$/i.test(key) || /^\d{4}-\d{2}-\d{2}[T\s]/.test(String(value))) {
    const parsed = new Date(value);
    if (!Number.isNaN(parsed.getTime())) {
      return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(parsed);
    }
  }
  return String(value);
}

function TableSkeleton({ columns = 4, rows = 5 }) {
  return (
    <div className="pk-table-skeleton" role="status" aria-live="polite">
      <span className="sr-only">Memuat data, daftar terbaru sedang diambil dari server</span>
      <div className="pk-table-skeleton-head" aria-hidden="true">
        {Array.from({ length: columns }, (_, index) => <i key={index} className="pk-skeleton" />)}
      </div>
      {Array.from({ length: rows }, (_, row) => (
        <div key={row} className="pk-table-skeleton-row" aria-hidden="true">
          {Array.from({ length: columns }, (_, index) => <i key={index} className={`pk-skeleton pk-w-${(row * 3 + index * 5) % 4}`} />)}
        </div>
      ))}
    </div>
  );
}
