import React, { lazy, Suspense, useEffect, useMemo, useRef, useState } from 'react';
import { QRCodeSVG } from 'qrcode.react';
import { Link, router, icons } from '../lib/deps.jsx';
import {
  apiJson, apiUpload, clampPercent, colors, defaultFormData, downloadAuthenticatedFile, exportResource,
  exportRowsToCsv, fieldsFor, formDataFromRow, moduleMeta, normalizePayload, optionsFor, panels,
  moduleSubtitle, parseField, parseJsonValue, pickRows, relationSelectTypes, requestStepUpMfa, roleLabel, rowsOf, rupiah, slug,
  statusOptions, titleFromSlug, tone,
} from '../components/app/appSupport.jsx';
import {
  Alert, AuditLogModal, Badge, BulkModal, columnTitle, CrudModal, CustomRoleModal,
  displayCellValue, DownloadManagerModal, FieldInput, ImportModal, inferredFieldForColumn,
  isVisibleTableColumn, JobStatusStrip, PanelCard, PaginationControls, PdfTemplateModal,
  PermissionMatrixModal, QueueMonitorModal, relationFallbackLabel, relationLabelFor, renderCell,
  RestoreApprovalModal, RestoreBackupModal, StatePanel, statusLabel, TimelineList, useDialogLifecycle, WorkflowModal,
} from '../components/app/AppDialogs.jsx';

const DashboardLineVisualization = lazy(() => import('../components/DashboardCharts.jsx').then((module) => ({ default: module.DashboardLineVisualization })));
const DashboardDonutVisualization = lazy(() => import('../components/DashboardCharts.jsx').then((module) => ({ default: module.DashboardDonutVisualization })));
const AnalyticsPanel = lazy(() => import('../components/AnalyticsPanel.jsx'));

function formatCell(value, key = '') {
  if (value === null || value === undefined || value === '') return '-';
  if (/(?:_at|_date|date|expires)$/i.test(key) || /^\d{4}-\d{2}-\d{2}[T\s]/.test(String(value))) {
    const parsed = new Date(value);
    if (!Number.isNaN(parsed.getTime())) {
      return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(parsed);
    }
  }
  return String(value);
}


export default function App({ panel = 'super-admin', screen = 'dashboard', module, dashboard, shared, auth, app }) {
  const config = panels[panel] || panels['super-admin'];
  const [collapsed, setCollapsed] = useState(false);
  const [mobileNavOpen, setMobileNavOpen] = useState(false);
  const mobileNavRef = useDialogLifecycle(() => setMobileNavOpen(false), mobileNavOpen);

  return (
    <div className="app-shell min-h-screen overflow-x-hidden text-neutral-900 selection:bg-primary-100 selection:text-primary-900">
      <aside className={`app-sidebar ${collapsed ? 'w-20' : 'w-72'} fixed inset-y-0 left-0 z-30 hidden border-r transition-[width] duration-300 lg:block ${config.light ? 'app-sidebar-light border-neutral-200 bg-white text-neutral-900' : 'app-sidebar-dark border-white/10 text-white'}`}>
        <div className="relative flex h-full flex-col p-3.5">
          <div className="flex items-center gap-3 px-2 py-2">
            <Logo />
            {!collapsed && <div className="min-w-0"><div className="font-semibold">Pesantrenku</div><div className={`mt-0.5 text-[11px] font-medium uppercase ${config.light ? 'text-neutral-500' : 'text-white/60'}`}>{config.role}</div></div>}
          </div>
          <div className={`mt-7 px-3 text-[10px] font-semibold uppercase tracking-[0.16em] ${collapsed ? 'sr-only' : config.light ? 'text-neutral-400' : 'text-white/45'}`}>Navigasi</div>
          <nav aria-label="Navigasi utama" className="mt-3 flex-1 space-y-1 overflow-y-auto pr-1">
            {config.menu.map(([label, icon, href]) => <SideItem key={label} label={label} icon={icon} href={label === 'Dashboard' ? `/dashboard/${panel}` : href} active={(screen === 'dashboard' && label === 'Dashboard') || href?.endsWith(`/module/${module}`) || module === slug(label)} light={config.light} collapsed={collapsed} />)}
          </nav>
          <button
            type="button"
            onClick={() => setCollapsed(!collapsed)}
            aria-label={collapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'}
            aria-expanded={!collapsed}
            title={collapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'}
            className={`mt-3 inline-flex min-h-10 items-center justify-center rounded-lg px-3 py-2 text-xs font-semibold transition focus:outline-none focus:ring-2 focus:ring-primary-500/50 ${config.light ? 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200' : 'bg-white/10 text-white/90 hover:bg-white/15'}`}
          >
            {collapsed ? <icons.ChevronRight className="h-4 w-4" /> : <span className="inline-flex items-center gap-2"><icons.ChevronLeft className="h-4 w-4" /> Ciutkan</span>}
          </button>
        </div>
      </aside>
      {mobileNavOpen && (
        <div ref={mobileNavRef} className="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true" aria-label="Navigasi utama">
          <button type="button" className="absolute inset-0 bg-neutral-950/45 backdrop-blur-[2px]" onClick={() => setMobileNavOpen(false)} aria-label="Tutup menu" tabIndex={-1} />
          <aside className={`app-sidebar relative flex h-full w-80 max-w-[88vw] flex-col border-r p-4 shadow-2xl ${config.light ? 'app-sidebar-light border-neutral-200 bg-white text-neutral-900' : 'app-sidebar-dark border-white/10 text-white'}`}>
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <Logo />
                <div><div className="font-semibold">Pesantrenku</div><div className={`mt-0.5 text-[11px] font-medium uppercase ${config.light ? 'text-neutral-500' : 'text-white/60'}`}>{config.role}</div></div>
              </div>
              <button type="button" onClick={() => setMobileNavOpen(false)} className={`rounded-lg p-2 ${config.light ? 'hover:bg-neutral-100' : 'hover:bg-white/10'}`} aria-label="Tutup menu" data-dialog-initial-focus><icons.X className="h-5 w-5" /></button>
            </div>
            <div className={`mt-7 px-3 text-[10px] font-semibold uppercase tracking-[0.16em] ${config.light ? 'text-neutral-400' : 'text-white/45'}`}>Navigasi</div>
            <nav aria-label="Navigasi utama" className="mt-3 flex-1 space-y-1 overflow-y-auto">
              {config.menu.map(([label, icon, href]) => <SideItem key={label} label={label} icon={icon} href={label === 'Dashboard' ? `/dashboard/${panel}` : href} active={(screen === 'dashboard' && label === 'Dashboard') || href?.endsWith(`/module/${module}`) || module === slug(label)} light={config.light} onClick={() => setMobileNavOpen(false)} />)}
            </nav>
          </aside>
        </div>
      )}

      <main className={`workspace-main ${collapsed ? 'lg:pl-20' : 'lg:pl-72'} relative min-w-0 transition-[padding] duration-300`}>
        <Topbar panel={panel} auth={auth} shared={shared} app={app} onMenuOpen={() => setMobileNavOpen(true)} />
        <div className="workspace-content relative mx-auto max-w-[1800px] p-3.5 sm:p-5 lg:p-6">
          {screen === 'dashboard' ? <Dashboard dashboard={dashboard} shared={shared} panel={panel} /> : <ModulePage panel={panel} module={module} shared={shared} dashboard={dashboard} />}
        </div>
      </main>
    </div>
  );
}

function Dashboard({ dashboard, shared, panel }) {
  if (dashboard?.profile) {
    return <RoleDashboard dashboard={dashboard} shared={shared} panel={panel} />;
  }

  const preview = dashboardPreview(panel, shared);

  return (
    <div className="space-y-5">
      <Header title={dashboard.title} subtitle={dashboard.subtitle} />
      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
        {dashboard.stats?.map((item) => <StatCard key={item.label} item={item} />)}
      </section>
      <section className="grid items-start gap-5 xl:grid-cols-[1.35fr_0.65fr]">
        <ChartCard title={panel === 'akademik' ? 'Statistik Siswa' : panel === 'keuangan' ? 'Grafik Pemasukan & Pengeluaran' : 'Grafik Pengguna & Transaksi'}>
          <Suspense fallback={<ChartLoading />}><DashboardLineVisualization data={dashboard.lineChart || []} /></Suspense>
        </ChartCard>
        <ChartCard title={panel === 'akademik' ? 'Distribusi Kelas' : panel === 'keuangan' ? 'Pemasukan per Kategori' : 'Pengguna berdasarkan Role'}>
          <Suspense fallback={<ChartLoading />}><DashboardDonutVisualization data={dashboard.donut || []} /></Suspense>
          <LegendList items={dashboard.donut || []} />
        </ChartCard>
        <div className="min-w-0 xl:col-span-2">
          <PanelCard title={panel === 'keuangan' ? 'Pengingat & Notifikasi' : 'Aktivitas Sistem Terbaru'} action="Lihat Semua" actionHref={activityHref(panel)}>
            <ActivityFeed items={dashboard.activities || []} emptyTitle="Belum ada aktivitas" />
          </PanelCard>
        </div>
      </section>
      <section className="grid items-start gap-5 xl:grid-cols-3">
        <DataPreview title={preview[0].title} rows={preview[0].rows} columns={preview[0].columns} shared={shared} href={preview[0].href} />
        <DataPreview title={preview[1].title} rows={preview[1].rows} columns={preview[1].columns} shared={shared} href={preview[1].href} />
        <PanelCard title={panel === 'super-admin' ? 'Penggunaan Modul (Top 10)' : 'Aksi Cepat'}>
          {panel === 'super-admin' ? <ProgressList items={dashboard.moduleUsage || []} /> : <QuickActions items={dashboard.quickActions || ['Tambah Data', 'Import CSV', 'Export CSV', 'Cetak PDF', 'Pengaturan']} panel={panel} />}
        </PanelCard>
      </section>
      <section className="grid items-start gap-5 xl:grid-cols-2">
        <DataPreview title={preview[2].title} rows={preview[2].rows} columns={preview[2].columns} shared={shared} href={preview[2].href} />
        <PanelCard title="Notifikasi Penting">
          <ActivityFeed items={rowsOf(shared.notifications).map((n) => ({ ...n, description: n.body, time: 'Baru saja' }))} emptyTitle="Belum ada notifikasi" />
        </PanelCard>
      </section>
    </div>
  );
}

function RoleDashboard({ dashboard, shared, panel }) {
  const profile = dashboard.profile || {};
  const primary = rolePrimaryRows(panel, dashboard, shared);

  return (
    <div className="space-y-5">
      <section className="profile-strip surface-panel overflow-hidden border-l-4 border-l-primary-600 p-4 sm:p-5">
        <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div className="flex min-w-0 items-center gap-3.5">
            <div className="grid h-12 w-12 shrink-0 place-items-center rounded-lg bg-primary-50 text-base font-semibold text-primary-700 ring-1 ring-primary-100">{(profile.name || roleLabel(panel)).split(' ').map((part) => part[0]).join('').slice(0, 2)}</div>
            <div className="min-w-0">
              <div className="text-[11px] font-semibold uppercase text-primary-700">Pesantrenku / {roleLabel(panel)}</div>
              <h1 className="mt-1 break-words text-xl font-semibold text-neutral-900 sm:text-2xl">{profile.name || roleLabel(panel)}</h1>
              <p className="mt-0.5 break-words text-sm leading-5 text-neutral-500">{profile.subtitle || 'Ringkasan aktivitas pesantren'}</p>
            </div>
          </div>
          <Link href={panel === 'owner' ? '/owner/module/profil-pesantren' : `/${panel}/module/profil`} className="ui-button ui-button-secondary"><icons.UserCog className="h-4 w-4" /> Profil</Link>
        </div>
      </section>

      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {(dashboard.summary || []).map((item) => <StatCard key={item.label} item={{ label: item.label, value: item.value, trend: 'Data aktif', icon: roleSummaryIcon(item.label), tone: roleSummaryTone(item.label) }} />)}
      </section>

      <section className="grid items-start gap-5 xl:grid-cols-[1.2fr_0.8fr]">
        <PanelCard title={primary.title} action="Lihat Semua" actionHref={primary.href}>
          <Table rows={primary.rows} shared={shared} compact />
        </PanelCard>
        <PanelCard title="Pengumuman">
          <ActivityFeed items={rowsOf(dashboard.announcements).slice(0, 5).map((item) => ({ ...item, description: item.body || item.category || 'Pengumuman pesantren', time: item.published_at || 'Terbaru' }))} emptyTitle="Belum ada pengumuman" />
        </PanelCard>
      </section>

      <section className="grid items-start gap-5 xl:grid-cols-3">
        <PanelCard title="Jadwal">
          <Table rows={dashboard.schedules} shared={shared} compact />
        </PanelCard>
        <PanelCard title={panel === 'klinik' ? 'Riwayat Kesehatan' : 'Tagihan'}>
          <Table rows={panel === 'klinik' ? dashboard.medical : dashboard.invoices} shared={shared} compact />
        </PanelCard>
        <PanelCard title="Notifikasi Penting">
          <ActivityFeed items={rowsOf(shared.notifications).slice(0, 4).map((n) => ({ ...n, description: n.body, time: 'Baru saja' }))} emptyTitle="Belum ada notifikasi" />
        </PanelCard>
      </section>
    </div>
  );
}

function ModulePage({ panel, module, shared, dashboard }) {
  if (dashboard?.profile) {
    const actionMeta = roleActionMeta(panel, module, dashboard, shared);
    if (actionMeta) return <CrudModule meta={actionMeta} shared={shared} />;

    return <RoleModule panel={panel} module={module} dashboard={dashboard} shared={shared} />;
  }

  const tabbedMeta = tabbedModuleMeta(panel, module, shared);
  if (tabbedMeta) {
    return <TabbedCrudModule meta={tabbedMeta} shared={shared} />;
  }

  const meta = moduleMeta(module, shared, panel);

  if (meta.kind === 'system') {
    return <SystemModule meta={meta} shared={shared} />;
  }

  return <CrudModule meta={meta} shared={shared} />;
}

function TabbedCrudModule({ meta, shared }) {
  const [active, setActive] = useState(meta.tabs[0]?.key);
  const tab = meta.tabs.find((item) => item.key === active) || meta.tabs[0];
  const selectTabFromKeyboard = (event, index) => {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    const lastIndex = meta.tabs.length - 1;
    const nextIndex = event.key === 'Home'
      ? 0
      : event.key === 'End'
        ? lastIndex
        : (index + (event.key === 'ArrowRight' ? 1 : -1) + meta.tabs.length) % meta.tabs.length;
    setActive(meta.tabs[nextIndex].key);
    window.requestAnimationFrame(() => event.currentTarget.parentElement?.querySelectorAll('[role="tab"]')[nextIndex]?.focus());
  };

  return (
    <div className="space-y-5">
      <Header title={meta.title} subtitle={meta.subtitle} />
      <div className="tab-bar surface-panel flex gap-1 overflow-x-auto p-1.5" role="tablist" aria-label={meta.title}>
        {meta.tabs.map((item, index) => (
          <button
            key={item.key}
            id={`${meta.resource || slug(meta.title)}-tab-${item.key}`}
            type="button"
            onClick={() => setActive(item.key)}
            onKeyDown={(event) => selectTabFromKeyboard(event, index)}
            role="tab"
            aria-selected={item.key === tab.key}
            aria-controls={`${meta.resource || slug(meta.title)}-panel-${item.key}`}
            tabIndex={item.key === tab.key ? 0 : -1}
            className={`shrink-0 rounded-md px-3.5 py-2 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-primary-500/40 ${item.key === tab.key ? 'bg-primary-600 text-white' : 'text-neutral-600 hover:bg-primary-50 hover:text-primary-700'}`}
          >
            {item.label}
          </button>
        ))}
      </div>
      <div id={`${meta.resource || slug(meta.title)}-panel-${tab.key}`} role="tabpanel" aria-labelledby={`${meta.resource || slug(meta.title)}-tab-${tab.key}`}>
        <CrudModule meta={{ ...tab.meta, title: tab.label, subtitle: tab.subtitle || tab.meta.subtitle, showHeader: false }} shared={shared} />
      </div>
    </div>
  );
}

function RoleModule({ panel, module, dashboard, shared }) {
  const meta = roleModuleMeta(panel, module, dashboard, shared);

  return (
    <div className="space-y-5">
      <Header title={meta.title} subtitle={meta.subtitle} />
      {meta.profile && (
        <section className="surface-panel border-l-4 border-l-sky-500 p-4 sm:p-5">
          <div className="text-sm font-semibold">{dashboard.profile?.name}</div>
          <div className="mt-1 text-sm text-neutral-500">{dashboard.profile?.subtitle}</div>
        </section>
      )}
      {meta.cards?.length > 0 && (
        <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          {meta.cards.map((item) => <StatCard key={item.label} item={item} />)}
        </section>
      )}
      {meta.actionType === 'qr' ? (
        <QrTool panel={panel} rows={meta.rows} shared={shared} />
      ) : (
        <PanelCard title={meta.tableTitle || meta.title}>
          <Table rows={meta.rows} columns={meta.columns} shared={shared} />
        </PanelCard>
      )}
      {meta.secondaryRows && (
        <section className="grid gap-5 xl:grid-cols-2">
          <PanelCard title={meta.secondaryTitle || 'Data Terkait'}><Table rows={meta.secondaryRows} shared={shared} compact /></PanelCard>
          <PanelCard title="Pengumuman"><ActivityFeed items={rowsOf(dashboard.announcements).slice(0, 4).map((item) => ({ ...item, description: item.body || item.category, time: item.published_at || 'Terbaru' }))} emptyTitle="Belum ada pengumuman" /></PanelCard>
        </section>
      )}
    </div>
  );
}

function QrTool({ panel, rows, shared }) {
  const initialQr = rowsOf(rows)[0] || {};
  const initialCode = initialQr.code || initialQr.kode || '';
  const [code, setCode] = useState(typeof initialCode === 'string' && initialCode.length === 64 ? initialCode : '');
  const [expiresAt, setExpiresAt] = useState(initialQr.expires_at || '');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const QrIcon = icons.QrCode || icons.Box;
  const purpose = panel === 'santri' ? 'attendance' : 'identity';
  const canScan = ['guru', 'musyrif', 'klinik', 'tu', 'akademik', 'keuangan', 'owner'].includes(panel);

  const generate = async () => {
    setLoading(true);
    setMessage('');
    setError('');
    try {
      const json = await apiJson('/web-actions/qr/generate', { method: 'POST', body: JSON.stringify({ purpose }) });
      if (typeof json.code !== 'string' || json.code.length !== 64) {
        throw new Error('Server mengembalikan kode QR yang tidak valid.');
      }
      setCode(json.code);
      setExpiresAt(json.expires_at || '');
      setMessage('QR berhasil dibuat dan dicatat di audit log.');
      router.reload({ only: ['shared'], preserveScroll: true });
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };



  return (
    <section className={`grid gap-5 ${canScan ? 'xl:grid-cols-[0.8fr_1.2fr]' : 'max-w-xl'}`}>
      <PanelCard title={purpose === 'attendance' ? 'QR Presensi Aktif' : 'QR Identitas Aktif'}>
        {(message || error) && <div className="mb-4"><Alert tone={error ? 'error' : 'success'}>{error || message}</Alert></div>}
        <p className="mb-4 text-sm leading-6 text-neutral-600">
          QR berlaku singkat dan sekali pakai. Jangan membagikannya melalui tangkapan layar.
        </p>
        <div className="grid aspect-square max-w-xs place-items-center rounded-2xl border border-neutral-200 bg-white p-6">
          {code ? <QRCodeSVG value={code} size={240} level="M" marginSize={2} title={purpose === 'attendance' ? 'QR presensi Pesantrenku' : 'QR identitas Pesantrenku'} /> : <div className="text-center text-sm text-neutral-500">Generate kode untuk menampilkan QR yang dapat dipindai.</div>}
        </div>
        {expiresAt && <div className="mt-2 text-xs text-neutral-500">Berlaku sampai {formatCell(expiresAt, 'expires_at', shared)}</div>}
        <button type="button" onClick={generate} disabled={loading} className="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">
          {loading ? <icons.Loader2 className="h-4 w-4 animate-spin" /> : <QrIcon className="h-4 w-4" />}
          {code ? 'Perbarui QR' : 'Buat QR'}
        </button>
      </PanelCard>
      {canScan && <PanelCard title="Pemindai Terverifikasi">
        <div className="rounded-xl border border-primary-100 bg-primary-50 p-4 text-sm leading-6 text-primary-900">
          Pemindaian presensi hanya tersedia melalui aplikasi mobile pada perangkat terdaftar dan jaringan kampus. Kode yang ditempel atau dikirim dari browser tidak dapat mencatat kehadiran.
        </div>
        <div className="mt-5">
          <Table rows={rows} shared={shared} compact />
        </div>
      </PanelCard>}
    </section>
  );
}

function CrudModule({ meta, shared }) {
  const [rows, setRows] = useState(rowsOf(meta.rows));
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('Semua Status');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [classFilter, setClassFilter] = useState('');
  const [studentFilter, setStudentFilter] = useState('');
  const [formData, setFormData] = useState(() => defaultFormData(meta, shared));
  const [editing, setEditing] = useState(null);
  const [modalMode, setModalMode] = useState(null);
  const [timeline, setTimeline] = useState([]);
  const [timelineLoading, setTimelineLoading] = useState(false);
  const [analytics, setAnalytics] = useState(null);
  const [workflowDraft, setWorkflowDraft] = useState(null);
  const [workflowNote, setWorkflowNote] = useState('');
  const [importOpen, setImportOpen] = useState(false);
  const [bulkOpen, setBulkOpen] = useState(false);
  const [permissionOpen, setPermissionOpen] = useState(false);
  const [customRoleOpen, setCustomRoleOpen] = useState(false);
  const [pdfTemplateOpen, setPdfTemplateOpen] = useState(false);
  const [downloadManagerOpen, setDownloadManagerOpen] = useState(false);
  const [queueMonitorOpen, setQueueMonitorOpen] = useState(false);
  const [restoreDraft, setRestoreDraft] = useState(null);
  const [restoreApprovalOpen, setRestoreApprovalOpen] = useState(false);
  const [jobs, setJobs] = useState([]);
  const [selectedIds, setSelectedIds] = useState([]);
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: rowsOf(meta.rows).length, per_page: 20 });
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);
  const [formErrors, setFormErrors] = useState({});
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const requestSequence = useRef(0);
  const filterFields = meta.fields || [];
  const supportsStatusFilter = filterFields.some((field) => field.name === 'status');
  const supportsDateFilter = filterFields.some((field) => ['date', 'datetime-local'].includes(field.type));
  const supportsClassFilter = filterFields.some((field) => field.name === 'class_id');
  const supportsStudentFilter = filterFields.some((field) => field.name === 'student_id');
  const workflowResource = ['permits', 'payments', 'medical-queues'].includes(meta.resource);
  const bulkProtectedResource = ['users', 'pesantrens', 'memberships', 'roles', 'plans', 'subscriptions', 'notifications', 'audit-logs', 'backups', 'permits', 'payments', 'medical-queues'].includes(meta.resource);
  const canCreate = meta.canCreate !== false && Boolean(meta.resource);
  const canEdit = meta.canEdit !== false && Boolean(meta.resource);
  const canDelete = meta.canDelete !== false && Boolean(meta.resource) && !workflowResource;
  const canExport = meta.canExport !== false && Boolean(meta.resource);
  const canImport = canCreate;
  const canBulk = (canEdit || canDelete) && Boolean(meta.resource) && !bulkProtectedResource;
  const canView = meta.canView !== false;
  const canEditPermissions = ['super-admin', 'owner'].includes(meta.panel) && ['users', 'memberships'].includes(meta.resource);
  const canMonitorQueue = meta.panel === 'super-admin' && ['backups', 'system-settings'].includes(meta.resource);
  const canEditPdfTemplates = meta.resource === 'system-settings' && canEdit;
  const ImportIcon = icons.Upload || icons.Download;
  const BulkIcon = icons.ListChecks || icons.CheckSquare || icons.CircleCheck;

  useEffect(() => {
    setRows(rowsOf(meta.rows));
    setSearch('');
    setStatus('Semua Status');
    setDateFrom('');
    setDateTo('');
    setClassFilter('');
    setStudentFilter('');
    setEditing(null);
    setModalMode(null);
    setTimeline([]);
    setAnalytics(null);
    setWorkflowDraft(null);
    setWorkflowNote('');
    setImportOpen(false);
    setBulkOpen(false);
    setRestoreApprovalOpen(false);
    setPermissionOpen(false);
    setCustomRoleOpen(false);
    setPdfTemplateOpen(false);
    setDownloadManagerOpen(false);
    setQueueMonitorOpen(false);
    setRestoreDraft(null);
    setJobs([]);
    setSelectedIds([]);
    setPagination({ current_page: 1, last_page: 1, total: rowsOf(meta.rows).length, per_page: 20 });
    setPage(1);
    setFormErrors({});
    setFormData(defaultFormData(meta, shared));
    setMessage('');
    setError('');
  }, [meta.resource, meta.title]);

  useEffect(() => {
    const closeToolbarMenus = (event) => {
      if (event.type === 'mousedown' && event.target.closest?.('.crud-toolbar')) return;
      if (event.type === 'keydown' && event.key !== 'Escape') return;
      document.querySelectorAll('.crud-toolbar details[open]').forEach((detail) => detail.removeAttribute('open'));
    };
    document.addEventListener('mousedown', closeToolbarMenus);
    document.addEventListener('keydown', closeToolbarMenus);
    return () => {
      document.removeEventListener('mousedown', closeToolbarMenus);
      document.removeEventListener('keydown', closeToolbarMenus);
    };
  }, []);

  useEffect(() => {
    setPage(1);
  }, [search, status, dateFrom, dateTo, classFilter, studentFilter, perPage, meta.resource]);

  useEffect(() => {
    if (!meta.resource) return;
    const timer = window.setTimeout(() => loadRows(search), 300);
    return () => window.clearTimeout(timer);
  }, [search, status, dateFrom, dateTo, classFilter, studentFilter, page, perPage, meta.resource]);

  const buildParams = (query = search) => {
    const params = new URLSearchParams({ per_page: String(perPage), page: String(page) });
    if (query) params.set('search', query);
    if (status !== 'Semua Status') params.set('status', status);
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo) params.set('date_to', dateTo);
    if (classFilter) params.set('class_id', classFilter);
    if (studentFilter) params.set('student_id', studentFilter);
    return params;
  };

  const loadRows = async (query = search) => {
    if (!meta.resource) return;
    const sequence = ++requestSequence.current;
    setLoading(true);
    setError('');
    const params = buildParams(query);
    try {
      const [json, report] = await Promise.all([
        apiJson(`/web-api/${meta.resource}?${params.toString()}`),
        apiJson(`/web-api/${meta.resource}/analytics?${params.toString()}`).catch(() => null),
      ]);
      if (sequence !== requestSequence.current) return;
      setRows(rowsOf(json.data));
      setSelectedIds([]);
      setPagination({
        current_page: json.current_page || 1,
        last_page: json.last_page || 1,
        total: json.total ?? rowsOf(json.data).length,
        per_page: json.per_page || perPage,
      });
      setAnalytics(report);
    } catch (err) {
      if (sequence !== requestSequence.current) return;
      setError(err.message);
    } finally {
      if (sequence === requestSequence.current) setLoading(false);
    }
  };

  const backupProcessing = meta.resource === 'backups' && rows.some((row) => ['queued', 'processing', 'restoring'].includes(row.status));

  useEffect(() => {
    if (!backupProcessing) return undefined;
    const timer = window.setInterval(() => loadRows(search), 5000);
    return () => window.clearInterval(timer);
  }, [backupProcessing, meta.resource, search, status, dateFrom, dateTo, classFilter, studentFilter, page, perPage]);

  const filteredRows = rows;

  const submit = async (event) => {
    event.preventDefault();
    if (!meta.resource) return;
    setSaving(true);
    setError('');
    setMessage('');
    setFormErrors({});
    try {
      const writableFields = editing && workflowResource ? meta.fields.filter((field) => field.name !== 'status') : meta.fields;
      const payload = normalizePayload(formData, writableFields);
      if (meta.resource === 'memberships') payload.mfa_code = requestStepUpMfa('mengubah membership');
      const endpoint = editing ? `/web-api/${meta.resource}/${editing.id}` : `/web-api/${meta.resource}`;
      await apiJson(endpoint, {
        method: editing ? 'PATCH' : 'POST',
        body: JSON.stringify(payload),
      });
      setMessage(editing ? 'Data berhasil diperbarui.' : 'Data berhasil ditambahkan.');
      setEditing(null);
      setModalMode(null);
      setFormData(defaultFormData(meta, shared));
      await loadRows();
      router.reload({ only: ['shared', 'dashboard'], preserveScroll: true });
    } catch (err) {
      setFormErrors(err.errors || {});
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  const createRow = () => {
    setEditing(null);
    setFormData(defaultFormData(meta, shared));
    setModalMode('create');
    setMessage('');
    setError('');
    setFormErrors({});
  };

  useEffect(() => {
    if (!meta.resource || !canCreate) return;
    const params = new URLSearchParams(window.location.search);
    if (params.get('action') !== 'create') return;
    window.history.replaceState(window.history.state, '', window.location.pathname);
    createRow();
  }, [meta.resource, meta.title, canCreate]);

  const viewRow = (row) => {
    setEditing(row);
    setFormData(formDataFromRow(row, meta));
    setModalMode('view');
    loadTimeline(row);
    setMessage('');
    setError('');
  };

  const editRow = (row) => {
    setEditing(row);
    setFormData(formDataFromRow(row, meta));
    setModalMode('edit');
    setTimeline([]);
    setMessage('');
    setError('');
    setFormErrors({});
  };

  const loadTimeline = async (row) => {
    if (!meta.resource || !row?.id) return;
    setTimeline([]);
    setTimelineLoading(true);
    try {
      const json = await apiJson(`/web-api/${meta.resource}/${row.id}/timeline`);
      setTimeline(rowsOf(json.data));
    } catch {
      setTimeline([]);
    } finally {
      setTimelineLoading(false);
    }
  };

  const deleteRow = async (row) => {
    if (!meta.resource || !window.confirm(`Hapus data ${row.name || row.title || row.invoice_number || row.number || row.id}?`)) return;
    setLoading(true);
    setError('');
    setMessage('');
    try {
      const body = meta.resource === 'memberships' ? JSON.stringify({ mfa_code: requestStepUpMfa('menghapus membership') }) : undefined;
      await apiJson(`/web-api/${meta.resource}/${row.id}`, { method: 'DELETE', ...(body ? { body } : {}) });
      setMessage('Data berhasil dihapus.');
      await loadRows();
      router.reload({ only: ['shared', 'dashboard'], preserveScroll: true });
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const openWorkflow = (row, action) => {
    setWorkflowDraft({ row, action });
    setWorkflowNote('');
    setMessage('');
    setError('');
  };

  const submitWorkflow = async (event) => {
    event.preventDefault();
    if (!workflowDraft) return;
    const { row, action } = workflowDraft;
    if (action.requiresNote && !workflowNote.trim()) {
      setError('Catatan wajib diisi untuk aksi ini.');
      return;
    }

    setLoading(true);
    setError('');
    setMessage('');
    try {
      await apiJson(`/web-actions/workflow/${meta.resource}/${row.id}/${action.transition}`, {
        method: 'POST',
        body: JSON.stringify({ note: workflowNote.trim() || action.label }),
      });
      setMessage(action.success || 'Status berhasil diperbarui.');
      setWorkflowDraft(null);
      setWorkflowNote('');
      await loadRows();
      router.reload({ only: ['shared', 'dashboard'], preserveScroll: true });
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const cancelEdit = () => {
    setEditing(null);
    setModalMode(null);
    setFormData(defaultFormData(meta, shared));
    setMessage('');
  };

  const toggleSelected = (rowId) => {
    setSelectedIds((current) => current.includes(rowId) ? current.filter((id) => id !== rowId) : [...current, rowId]);
  };

  const setSelectedForPage = (ids, checked) => {
    setSelectedIds((current) => {
      const withoutPage = current.filter((id) => !ids.includes(id));
      return checked ? [...withoutPage, ...ids] : withoutPage;
    });
  };

  const submitBulk = async ({ action, status: nextStatus }) => {
    if (!selectedIds.length) return;
    setLoading(true);
    setError('');
    setMessage('');
    try {
      const json = await apiJson(`/web-api/${meta.resource}/bulk`, {
        method: 'POST',
        body: JSON.stringify({ action, status: nextStatus, ids: selectedIds }),
      });
      setMessage(json.message || 'Aksi massal berhasil diproses.');
      setBulkOpen(false);
      await loadRows();
      router.reload({ only: ['shared', 'dashboard'], preserveScroll: true });
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const loadJobs = async () => {
    try {
      const json = await apiJson('/web-actions/jobs');
      setJobs(rowsOf(json.data));
    } catch {
      setJobs([]);
    }
  };

  const submitImport = async ({ file, mapping, queue }) => {
    if (!file || !meta.resource) return;
    setLoading(true);
    setError('');
    setMessage('');
    try {
      const json = await apiUpload(`/web-api/${meta.resource}/import`, file, { mapping: JSON.stringify(mapping || {}), queue: queue ? '1' : '0' });
      if (json.queued) {
        setMessage(`Import masuk antrian. Job: ${json.job?.uuid || '-'}`);
        setImportOpen(false);
        await loadJobs();
        return;
      }
      const skipped = json.skipped ? ` ${json.skipped} baris dilewati.` : '';
      const errorText = rowsOf(json.errors).length ? ` Validasi pertama: ${rowsOf(json.errors)[0].messages?.join(', ')}` : '';
      setMessage(`${json.message || 'Import selesai.'}${skipped}${errorText}`);
      setImportOpen(false);
      await loadRows();
      router.reload({ only: ['shared', 'dashboard'], preserveScroll: true });
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const queueExport = async (format = 'excel') => {
    if (!meta.resource) return;
    setLoading(true);
    setError('');
    setMessage('');
    try {
      const json = await apiJson(`/web-api/${meta.resource}/export/queue`, {
        method: 'POST',
        body: JSON.stringify({ format, filters: Object.fromEntries(buildParams()) }),
      });
      setMessage(`Export masuk antrian. Job: ${json.job?.uuid || '-'}`);
      await loadJobs();
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const runBackup = async () => {
    setLoading(true);
    setError('');
    setMessage('');
    try {
      await apiJson('/web-actions/backup/run', { method: 'POST', body: JSON.stringify({}) });
      setMessage('Backup masuk antrean. Status akan diperbarui otomatis.');
      await loadRows();
      router.reload({ only: ['shared', 'dashboard'], preserveScroll: true });
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const downloadBackup = async (row) => {
    await downloadAuthenticatedFile(`/web-actions/backup/${row.id}/download`, row.name || `backup-${row.id}.json`);
  };

  const restoreBackup = async (event) => {
    event.preventDefault();
    if (!restoreDraft) return;
    setLoading(true);
    setError('');
    try {
      const json = await apiJson(`/web-actions/backup/${restoreDraft.id}/restore`, {
        method: 'POST',
        body: JSON.stringify({ confirmation: restoreDraft.confirmation, mfa_code: restoreDraft.mfa_code, reason: restoreDraft.reason }),
      });
      setMessage(`${json.message} Request #${json.request?.id || '-'}.`);
      setRestoreDraft(null);
      await loadRows();
      router.reload({ only: ['shared', 'dashboard'], preserveScroll: true });
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const resourceActions = [
    ...workflowActionsFor(meta).map((action) => ({ ...action, onClick: (row) => openWorkflow(row, action) })),
    ...(meta.resource === 'backups' ? [
      { key: 'download-backup', label: 'Download Backup', icon: 'Download', show: (row) => ['success', 'restored', 'restore_failed'].includes(row.status), onClick: downloadBackup },
      { key: 'restore-backup', label: 'Restore Backup', icon: 'RefreshCcw', tone: 'danger', show: (row) => ['success', 'restored', 'restore_failed'].includes(row.status), onClick: (row) => setRestoreDraft({ ...row, confirmation: '' }) },
    ] : []),
  ];

  return (
    <div className="space-y-5">
      {meta.showHeader !== false && <Header title={meta.title} subtitle={meta.subtitle} />}
      {(message || error) && <Alert tone={error ? 'error' : 'success'}>{error || message}</Alert>}
      <PanelCard>
        <div className="crud-command-bar mb-4 flex flex-col gap-4 border-b border-neutral-200 pb-4 2xl:flex-row 2xl:items-start 2xl:justify-between">
          <div className="filter-grid grid min-w-0 flex-1 gap-2.5 sm:grid-cols-2 xl:grid-cols-3">
            <div className="relative w-full"><icons.Search className="absolute left-3 top-3 h-4 w-4 text-neutral-400" /><input value={search} onChange={(event) => setSearch(event.target.value)} className="w-full rounded-xl border border-neutral-200 bg-white py-2.5 pl-10 pr-3 text-sm shadow-sm outline-none transition placeholder:text-neutral-400 focus:border-primary-500 focus:ring-4 focus:ring-primary-100/70" placeholder={`Cari ${meta.title.toLowerCase()}...`} aria-label={`Cari ${meta.title.toLowerCase()}`} /></div>
            {supportsStatusFilter && <label className="grid gap-1 text-xs font-medium text-neutral-600"><span>Status</span><select value={status} onChange={(event) => setStatus(event.target.value)} className="min-h-11 rounded-xl border border-neutral-200 bg-white px-3 text-sm text-neutral-700 shadow-sm outline-none transition focus:border-primary-500 focus:ring-4 focus:ring-primary-100/70"><option>Semua Status</option>{statusOptions.map((item) => <option key={item} value={item}>{statusLabel(item)}</option>)}</select></label>}
            {meta.enableFilters !== false && supportsDateFilter && <label className="grid gap-1 text-xs font-medium text-neutral-600"><span>Dari tanggal</span><input type="date" value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} className="min-h-11 rounded-xl border border-neutral-200 bg-white px-3 text-sm text-neutral-700 shadow-sm outline-none transition focus:border-primary-500 focus:ring-4 focus:ring-primary-100/70" /></label>}
            {meta.enableFilters !== false && supportsDateFilter && <label className="grid gap-1 text-xs font-medium text-neutral-600"><span>Sampai tanggal</span><input type="date" value={dateTo} onChange={(event) => setDateTo(event.target.value)} className="min-h-11 rounded-xl border border-neutral-200 bg-white px-3 text-sm text-neutral-700 shadow-sm outline-none transition focus:border-primary-500 focus:ring-4 focus:ring-primary-100/70" /></label>}
            {meta.enableFilters !== false && supportsClassFilter && (
              <label className="grid gap-1 text-xs font-medium text-neutral-600"><span>Kelas</span><select value={classFilter} onChange={(event) => setClassFilter(event.target.value)} className="min-h-11 rounded-xl border border-neutral-200 bg-white px-3 text-sm text-neutral-700 shadow-sm outline-none transition focus:border-primary-500 focus:ring-4 focus:ring-primary-100/70">
                <option value="">Semua Kelas</option>
                {rowsOf(shared.classes).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
              </select></label>
            )}
            {meta.enableFilters !== false && supportsStudentFilter && (
              <label className="grid gap-1 text-xs font-medium text-neutral-600"><span>Santri</span><select value={studentFilter} onChange={(event) => setStudentFilter(event.target.value)} className="min-h-11 rounded-xl border border-neutral-200 bg-white px-3 text-sm text-neutral-700 shadow-sm outline-none transition focus:border-primary-500 focus:ring-4 focus:ring-primary-100/70">
                <option value="">Semua Santri</option>
                {rowsOf(shared.students).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
              </select></label>
            )}
          </div>
          <div className="crud-toolbar relative flex flex-wrap items-start gap-2 2xl:max-w-[48rem] 2xl:justify-end" aria-label="Aksi data" onToggle={(event) => { if (event.target.open) event.currentTarget.querySelectorAll('details[open]').forEach((detail) => { if (detail !== event.target) detail.removeAttribute('open'); }); }}>
            {meta.resource === 'backups' && <button onClick={runBackup} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3 py-2 text-sm font-medium shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40"><icons.Database className="h-4 w-4" /> Jalankan Backup</button>}
            {meta.resource === 'backups' && meta.panel === 'super-admin' && <button onClick={() => setRestoreApprovalOpen(true)} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3 py-2 text-sm font-medium shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40"><icons.Shield className="h-4 w-4" /> Persetujuan Restore</button>}
            {canImport && <button onClick={() => setImportOpen(true)} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3 py-2 text-sm font-medium shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40"><ImportIcon className="h-4 w-4" /> Import Excel</button>}
            {canBulk && selectedIds.length > 0 && <button onClick={() => setBulkOpen(true)} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800 shadow-sm transition hover:border-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-200"><BulkIcon className="h-4 w-4" /> Aksi Massal ({selectedIds.length})</button>}
            {canEditPdfTemplates && <button onClick={() => setPdfTemplateOpen(true)} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3 py-2 text-sm font-medium shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40"><icons.FileText className="h-4 w-4" /> Template PDF</button>}
            <button onClick={() => loadRows()} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3 py-2 text-sm font-medium shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40"><icons.RefreshCcw className="h-4 w-4" /> Refresh</button>
            {canExport && <details className="relative"><summary className="inline-flex cursor-pointer list-none items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3 py-2 text-sm font-medium shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40"><icons.Download className="h-4 w-4" /> Ekspor</summary><div className="absolute right-0 top-12 z-20 grid min-w-52 gap-1 rounded-xl border border-neutral-200 bg-white p-2 shadow-xl"><button onClick={() => exportResource(meta.resource, meta.title, 'csv', buildParams())} className="rounded-lg px-3 py-2 text-left text-sm hover:bg-neutral-50">Unduh CSV</button><button onClick={() => exportResource(meta.resource, meta.title, 'excel', buildParams())} className="rounded-lg px-3 py-2 text-left text-sm hover:bg-neutral-50">Unduh Excel</button><button onClick={() => exportResource(meta.resource, meta.title, 'pdf', buildParams())} className="rounded-lg px-3 py-2 text-left text-sm hover:bg-neutral-50">Unduh PDF</button><button onClick={() => queueExport('excel')} className="rounded-lg px-3 py-2 text-left text-sm hover:bg-neutral-50">Ekspor di latar belakang</button></div></details>}
            {(canEditPermissions || canMonitorQueue || canExport || canImport) && <details className="relative"><summary className="inline-flex cursor-pointer list-none items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3 py-2 text-sm font-medium shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40"><icons.MoreHorizontal className="h-4 w-4" /> Lainnya</summary><div className="absolute right-0 top-12 z-20 grid min-w-56 gap-1 rounded-xl border border-neutral-200 bg-white p-2 shadow-xl"><button onClick={() => { setDownloadManagerOpen(true); loadJobs(); }} className="rounded-lg px-3 py-2 text-left text-sm hover:bg-neutral-50">Hasil unduhan</button>{canEditPermissions && <button onClick={() => setPermissionOpen(true)} className="rounded-lg px-3 py-2 text-left text-sm hover:bg-neutral-50">Matriks izin</button>}{canEditPermissions && <button onClick={() => setCustomRoleOpen(true)} className="rounded-lg px-3 py-2 text-left text-sm hover:bg-neutral-50">Role khusus</button>}{canMonitorQueue && <button onClick={() => setQueueMonitorOpen(true)} className="rounded-lg px-3 py-2 text-left text-sm hover:bg-neutral-50">Monitor antrean</button>}</div></details>}
            {canCreate && <button onClick={createRow} className="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-primary-700 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"><icons.Plus className="h-4 w-4" /> {meta.createLabel || 'Tambah Data'}</button>}
          </div>
        </div>
        <Table rows={filteredRows} columns={meta.fields.map((field) => field.name)} meta={meta} shared={shared} loading={loading} selectable={canBulk} selectedIds={selectedIds} onToggleRow={toggleSelected} onToggleAll={setSelectedForPage} onView={canView ? viewRow : null} onEdit={canEdit ? editRow : null} onDelete={canDelete ? deleteRow : null} customActions={resourceActions} />
        <PaginationControls pagination={pagination} page={page} setPage={setPage} perPage={perPage} setPerPage={setPerPage} />
      </PanelCard>
      {jobs.length > 0 && <JobStatusStrip jobs={jobs} onRefresh={loadJobs} />}
      {analytics && <Suspense fallback={<ChartLoading />}><AnalyticsPanel analytics={analytics} title={meta.title} /></Suspense>}
      {modalMode && <CrudModal meta={meta} shared={shared} formData={formData} setFormData={setFormData} editing={editing} mode={modalMode} saving={saving} formErrors={formErrors} onSubmit={submit} onClose={cancelEdit} timeline={timeline} timelineLoading={timelineLoading} />}
      {workflowDraft && <WorkflowModal draft={workflowDraft} note={workflowNote} setNote={setWorkflowNote} saving={loading} onSubmit={submitWorkflow} onClose={() => setWorkflowDraft(null)} />}
      {importOpen && <ImportModal title={meta.title} resource={meta.resource} loading={loading} onSubmit={submitImport} onClose={() => setImportOpen(false)} />}
      {bulkOpen && <BulkModal count={selectedIds.length} canDelete={canDelete} loading={loading} onSubmit={submitBulk} onClose={() => setBulkOpen(false)} />}
      {permissionOpen && <PermissionMatrixModal onClose={() => setPermissionOpen(false)} />}
      {customRoleOpen && <CustomRoleModal onClose={() => setCustomRoleOpen(false)} />}
      {pdfTemplateOpen && <PdfTemplateModal shared={shared} onClose={() => setPdfTemplateOpen(false)} onSaved={() => { setPdfTemplateOpen(false); loadRows(); router.reload({ only: ['shared'], preserveScroll: true }); }} />}
      {downloadManagerOpen && <DownloadManagerModal jobs={jobs} onRefresh={loadJobs} onClose={() => setDownloadManagerOpen(false)} />}
      {queueMonitorOpen && <QueueMonitorModal onClose={() => setQueueMonitorOpen(false)} />}
      {restoreDraft && <RestoreBackupModal draft={restoreDraft} setDraft={setRestoreDraft} loading={loading} onSubmit={restoreBackup} onClose={() => setRestoreDraft(null)} />}
      {restoreApprovalOpen && <RestoreApprovalModal onClose={() => setRestoreApprovalOpen(false)} />}
    </div>
  );
}

function SystemModule({ meta, shared }) {
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [message, setMessage] = useState('');
  const [detail, setDetail] = useState(null);
  const [health, setHealth] = useState(null);
  const rows = useMemo(() => {
    const term = search.toLowerCase();
    return rowsOf(meta.rows).filter((row) => {
      const matchesSearch = !term || Object.values(row).some((value) => String(value ?? '').toLowerCase().includes(term));
      const rowStatus = String(row.status || row.level || '').toLowerCase();
      return matchesSearch && (!status || rowStatus.includes(status));
    });
  }, [meta.rows, search, status]);

  useEffect(() => {
    fetch('/health', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then((response) => response.ok ? response.json() : Promise.reject(new Error('Health endpoint gagal')))
      .then(setHealth)
      .catch(() => setHealth({ status: 'error', database: 'error', storage: 'error', queue_table: 'missing' }));
  }, []);

  const exportRows = () => {
    exportRowsToCsv(rows, meta.title);
    setMessage('Export CSV berhasil disiapkan.');
  };

  return (
    <div className="space-y-5">
      <Header title={meta.title} subtitle={meta.subtitle} />
      {message && <Alert tone="success">{message}</Alert>}
      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {meta.stats.map((item) => <StatCard key={item.label} item={item} />)}
      </section>
      <PanelCard title={meta.tableTitle}>
        <div className="system-toolbar mb-4 flex flex-col gap-3 border-b border-neutral-200 pb-4 md:flex-row md:items-center md:justify-between">
          <div className="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row">
            <div className="relative max-w-xl flex-1"><icons.Search className="absolute left-3 top-3 h-4 w-4 text-neutral-400" /><input aria-label={`Cari ${meta.title}`} value={search} onChange={(event) => setSearch(event.target.value)} className="w-full rounded-xl border border-neutral-200 py-2.5 pl-10 pr-3 text-sm outline-none focus:border-primary-600" placeholder={meta.searchPlaceholder} /></div>
            <select aria-label={`Filter status ${meta.title}`} value={status} onChange={(event) => setStatus(event.target.value)} className="rounded-xl border border-neutral-200 px-3 text-sm"><option value="">Semua Status</option><option value="berhasil">Berhasil</option><option value="peringatan">Peringatan</option><option value="diblokir">Diblokir</option><option value="error">Error</option></select>
          </div>
          <div className="flex flex-wrap gap-2">
            <button onClick={exportRows} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 px-4 py-2 text-sm"><icons.Download className="h-4 w-4" /> Export CSV</button>
            <button onClick={() => router.reload({ only: ['shared', 'dashboard'], preserveScroll: true })} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 px-4 py-2 text-sm"><icons.RefreshCcw className="h-4 w-4" /> Refresh</button>
          </div>
        </div>
        <Table rows={rows} columns={meta.columns} shared={shared} onView={meta.audit ? setDetail : null} />
      </PanelCard>
      {detail && <AuditLogModal row={detail} shared={shared} onClose={() => setDetail(null)} />}
      <section className="grid gap-5 xl:grid-cols-3">
        <PanelCard title="Login Monitoring">
          <Table rows={shared.loginMonitoring} columns={['waktu', 'pengguna', 'role', 'ip', 'status']} shared={shared} compact />
        </PanelCard>
        <PanelCard title="Error Log Terbaru">
          <Table rows={shared.errorLogs} columns={['waktu', 'level', 'modul', 'pesan', 'status']} shared={shared} compact />
        </PanelCard>
        <PanelCard title="Kesehatan Sistem">
          {health ? <ProgressList items={[
            { name: 'Web Server', value: health.status === 'ok' ? 100 : 0 },
            { name: 'Database', value: health.database === 'ok' ? 100 : 0 },
            { name: 'Queue Table', value: health.queue_table === 'ok' ? 100 : 0 },
            { name: 'Storage', value: health.storage === 'ok' ? 100 : 0 },
          ]} /> : <div className="text-sm text-neutral-500">Memeriksa kesehatan layanan...</div>}
        </PanelCard>
      </section>
    </div>
  );
}

function Topbar({ panel, auth, shared, app, onMenuOpen }) {
  const memberships = Array.isArray(auth?.memberships) ? auth.memberships : Object.values(auth?.memberships || {});
  const activeTenantId = auth?.active_pesantren?.id || memberships[0]?.id || '';
  const [selectedTenantId, setSelectedTenantId] = useState(String(activeTenantId));
  const [tenantSwitching, setTenantSwitching] = useState(false);
  const [tenantError, setTenantError] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [searchOpen, setSearchOpen] = useState(false);
  const [notificationsOpen, setNotificationsOpen] = useState(false);
  const [notificationState, setNotificationState] = useState({ data: rowsOf(shared?.notifications), unread: rowsOf(shared?.notifications).length });
  const [notificationAction, setNotificationAction] = useState({ loading: false, error: '' });
  const [accountOpen, setAccountOpen] = useState(false);
  const academicYear = app?.academic_year || 'Tahun ajaran belum diatur';
  const topbarRef = useRef(null);
  const menuItems = useMemo(() => (panels[panel]?.menu || []).map(([label, icon, href]) => ({ label, icon, href: label === 'Dashboard' ? `/dashboard/${panel}` : href })), [panel]);
  const searchResults = useMemo(() => {
    const term = searchTerm.trim().toLowerCase();
    if (!term) return [];
    return menuItems.filter((item) => item.label.toLowerCase().includes(term)).slice(0, 6);
  }, [menuItems, searchTerm]);
  const notifications = rowsOf(notificationState.data).slice(0, 5);
  const unreadCount = notificationState.unread ?? notifications.filter((item) => !item.read_at).length;
  const userName = auth?.user?.name || roleLabel(panel);
  const userEmail = auth?.user?.email || '';
  const initials = userName.split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase();
  const roles = rowsOf(auth?.roles).map((role) => titleFromSlug(String(role))).join(', ') || roleLabel(panel);
  const settingsHref = menuItems.find((item) => item.label.toLowerCase().includes('pengaturan'))?.href || `/dashboard/${panel}`;
  const notificationConfig = app?.notifications || {};

  useEffect(() => {
    const closeFloatingMenus = (event) => {
      if (event.key === 'Escape') {
        setSearchOpen(false);
        setNotificationsOpen(false);
        setAccountOpen(false);
      }
      if (event.type === 'mousedown' && topbarRef.current && !topbarRef.current.contains(event.target)) {
        setSearchOpen(false);
        setNotificationsOpen(false);
        setAccountOpen(false);
      }
    };

    document.addEventListener('mousedown', closeFloatingMenus);
    document.addEventListener('keydown', closeFloatingMenus);

    return () => {
      document.removeEventListener('mousedown', closeFloatingMenus);
      document.removeEventListener('keydown', closeFloatingMenus);
    };
  }, []);

  useEffect(() => {
    setNotificationState({ data: rowsOf(shared?.notifications), unread: rowsOf(shared?.notifications).length });
  }, [shared?.notifications]);

  useEffect(() => {
    setSelectedTenantId(String(activeTenantId));
    setTenantSwitching(false);
    setTenantError('');
  }, [activeTenantId]);

  useEffect(() => {
    let mounted = true;
    let source = null;
    let unsubscribeRealtime = () => {};
    const poll = async () => {
      try {
        const json = await apiJson('/web-actions/notifications', { method: 'GET' });
        if (mounted) setNotificationState({ data: rowsOf(json.data), unread: json.unread ?? 0 });
      } catch {
        // Header polling is best-effort; page actions still surface request errors inline.
      }
    };
    poll();
    if (notificationConfig.sse_enabled && 'EventSource' in window) {
      source = new EventSource('/web-actions/notifications/stream');
      source.addEventListener('notifications', (event) => {
        try {
          const json = JSON.parse(event.data || '{}');
          if (mounted) setNotificationState({ data: rowsOf(json.data), unread: json.unread ?? 0 });
        } catch {
          // Bad stream payload should not disable the polling fallback.
        }
      });
    }
    if (notificationConfig.websocket_enabled) {
      import('../lib/realtime.js')
        .then(({ subscribeToNotifications }) => subscribeToNotifications({
          userId: auth?.user?.id,
          tenantId: activeTenantId,
          onChange: poll,
          connection: notificationConfig.websocket,
        }))
        .then((unsubscribe) => { unsubscribeRealtime = unsubscribe; })
        .catch(() => {});
    }
    const pollingSeconds = Math.max(5, Number(notificationConfig.polling_seconds) || 20);
    const timer = window.setInterval(poll, pollingSeconds * 1000);
    return () => {
      mounted = false;
      source?.close();
      unsubscribeRealtime();
      window.clearInterval(timer);
    };
  }, [activeTenantId, auth?.user?.id, notificationConfig.polling_seconds, notificationConfig.sse_enabled, notificationConfig.websocket, notificationConfig.websocket_enabled]);

  const openMenu = (href) => {
    if (!href) return;
    setSearchOpen(false);
    setSearchTerm('');
    router.visit(href);
  };

  const submitSearch = (event) => {
    event.preventDefault();
    openMenu(searchResults[0]?.href);
  };

  const logout = () => {
    setAccountOpen(false);
    router.post('/logout');
  };

  const switchTenant = (value) => {
    if (!value || value === String(activeTenantId) || tenantSwitching) return;
    setSelectedTenantId(value);
    setTenantError('');
    router.post('/tenant/switch', { pesantren_id: Number(value) }, {
      preserveScroll: true,
      onStart: () => setTenantSwitching(true),
      onError: () => {
        setSelectedTenantId(String(activeTenantId));
        setTenantError('Pesantren aktif gagal diganti. Silakan coba lagi.');
      },
      onFinish: () => setTenantSwitching(false),
    });
  };

  const markNotificationsRead = async () => {
    if (!notifications.length || notificationAction.loading) return;
    setNotificationAction({ loading: true, error: '' });
    try {
      await apiJson('/web-actions/notifications/read', {
        method: 'POST',
        body: JSON.stringify({ ids: notifications.map((item) => item.id).filter(Boolean) }),
      });
      const fresh = await apiJson('/web-actions/notifications', { method: 'GET' });
      setNotificationState({ data: rowsOf(fresh.data), unread: fresh.unread ?? 0 });
      setNotificationsOpen(false);
    } catch (error) {
      setNotificationAction({ loading: false, error: error.message || 'Notifikasi gagal diperbarui.' });
      return;
    }
    setNotificationAction({ loading: false, error: '' });
  };

  return (
    <header ref={topbarRef} className="app-topbar sticky top-0 z-10 border-b border-neutral-200 bg-white/95 px-3.5 py-2.5 backdrop-blur-lg sm:px-5 lg:px-6">
      <div className="flex items-center gap-2.5 sm:gap-4">
        <button type="button" onClick={onMenuOpen} className="icon-button lg:hidden" aria-label="Buka menu"><icons.Menu className="h-5 w-5" /></button>
        <form onSubmit={submitSearch} className="relative flex-1">
          <icons.Search className="absolute left-3 top-3 h-4 w-4 text-neutral-400" />
          <input
            aria-label="Cari menu, modul, dan laporan"
            value={searchTerm}
            onFocus={() => setSearchOpen(true)}
            onChange={(event) => {
              setSearchTerm(event.target.value);
              setSearchOpen(true);
            }}
            className="topbar-search w-full max-w-xl rounded-lg border border-neutral-200 bg-neutral-50 py-2.5 pl-10 pr-10 text-sm outline-none transition placeholder:text-neutral-400 focus:border-primary-500 focus:bg-white focus:ring-3 focus:ring-primary-100/70"
            placeholder="Cari menu, modul, laporan..."
          />
          {searchTerm && <button type="button" onClick={() => setSearchTerm('')} className="absolute right-3 top-2.5 rounded-lg p-1 text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40" aria-label="Bersihkan pencarian"><icons.X className="h-4 w-4" /></button>}
          {searchOpen && searchTerm && (
            <div className="floating-menu absolute left-0 top-12 z-30 w-full max-w-xl overflow-hidden border border-neutral-200 bg-white p-1.5 shadow-xl">
              {searchResults.length > 0 ? searchResults.map((item) => {
                const Icon = icons[item.icon] || icons.Search;
                return (
                  <button key={item.href} type="button" onClick={() => openMenu(item.href)} className="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-left text-sm transition hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-500/40">
                    <span className="grid h-8 w-8 place-items-center rounded-lg bg-primary-50 text-primary-700"><Icon className="h-4 w-4" /></span>
                    <span className="font-medium">{item.label}</span>
                  </button>
                );
              }) : <div className="px-4 py-3 text-sm text-neutral-500">Menu tidak ditemukan.</div>}
            </div>
          )}
        </form>
        {memberships.length > 0 && (
          <div className="relative hidden xl:block">
            <select
              aria-label="Pilih pesantren aktif"
              aria-invalid={Boolean(tenantError)}
              value={selectedTenantId}
              disabled={tenantSwitching}
              onChange={(event) => switchTenant(event.target.value)}
              className="max-w-64 rounded-lg border border-neutral-200 bg-white px-3 py-2.5 text-sm text-neutral-700 outline-none transition focus:border-primary-500 focus:ring-3 focus:ring-primary-100/70 disabled:cursor-wait disabled:bg-neutral-100"
            >
              {memberships.map((item) => <option key={`${item.id}-${item.role || item.role_label}`} value={item.id}>{item.name || item.pesantren} {item.role_label ? `- ${item.role_label}` : ''}</option>)}
            </select>
            {tenantSwitching && <icons.Loader2 className="pointer-events-none absolute right-8 top-3 h-4 w-4 animate-spin text-primary-700 motion-reduce:animate-none" aria-hidden="true" />}
          </div>
        )}
        <div className="hidden max-w-56 rounded-md border border-primary-100 bg-primary-50 px-3 py-2 text-xs font-medium text-primary-800 lg:block">{academicYear}</div>
        <div className="relative">
          <button type="button" onClick={() => { setNotificationsOpen((open) => !open); setAccountOpen(false); }} className="icon-button relative" aria-label="Buka notifikasi" aria-expanded={notificationsOpen}>
            <icons.Bell className="h-5 w-5" />
            {unreadCount > 0 && <span className="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-red-600 px-1 text-[10px] font-semibold text-white">{unreadCount}</span>}
          </button>
          {notificationsOpen && (
            <div className="floating-menu absolute right-0 top-12 z-30 w-[min(22rem,calc(100vw-1rem))] overflow-hidden border border-neutral-200 bg-white p-1.5 shadow-xl">
              <div className="flex items-center justify-between border-b border-neutral-100 px-3 py-3 text-sm">
                <span className="font-semibold">Notifikasi</span>
                {notifications.length > 0 && <button type="button" disabled={notificationAction.loading} onClick={markNotificationsRead} className="rounded-lg px-2 py-1 text-xs font-semibold text-primary-700 transition hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-500/40 disabled:cursor-wait disabled:opacity-50">{notificationAction.loading ? 'Memperbarui...' : 'Tandai terbaca'}</button>}
              </div>
              {notificationAction.error && <div role="alert" className="mx-2 mt-2 rounded-md bg-red-50 px-3 py-2 text-xs font-medium text-red-700">{notificationAction.error}</div>}
              <div className="max-h-80 overflow-y-auto">
                {notifications.length > 0 ? notifications.map((item) => (
                  <div key={item.id || item.title} className="mx-1 border-b border-neutral-100 px-3 py-3 text-sm last:border-0">
                    <div className="font-medium">{item.title}</div>
                    <div className="mt-1 text-xs text-neutral-500">{item.body || item.description || 'Notifikasi sistem'}</div>
                  </div>
                )) : <div className="px-4 py-6 text-center text-sm text-neutral-500">Belum ada notifikasi.</div>}
              </div>
            </div>
          )}
        </div>
        <div className="relative">
          <button type="button" onClick={() => { setAccountOpen((open) => !open); setNotificationsOpen(false); }} className="flex items-center gap-3 rounded-lg px-1.5 py-1 text-left transition hover:bg-neutral-100 focus:outline-none focus:ring-2 focus:ring-primary-500/40" aria-label="Buka menu akun" aria-expanded={accountOpen}>
            <div className="grid h-9 w-9 place-items-center rounded-lg bg-sky-50 text-sm font-semibold text-sky-700 ring-1 ring-sky-100">{initials || 'U'}</div>
            <div className="hidden text-sm md:block"><div className="font-semibold">{userName}</div><div className="text-xs text-neutral-500">{roleLabel(panel)}</div></div>
          </button>
          {accountOpen && (
            <div className="floating-menu absolute right-0 top-12 z-30 w-[min(22rem,calc(100vw-1rem))] overflow-hidden border border-neutral-200 bg-white p-1.5 shadow-xl">
              <div className="border-b border-neutral-100 px-3 py-3">
                <div className="font-semibold">{userName}</div>
                {userEmail && <div className="mt-1 text-xs text-neutral-500">{userEmail}</div>}
                <div className="mt-2 text-xs text-neutral-500">{roles}</div>
              </div>
              {auth?.active_pesantren && (
                <div className="mx-1 border-b border-neutral-100 px-3 py-3 text-sm">
                  <div className="text-xs text-neutral-500">Pesantren Aktif</div>
                  <div className="mt-1 font-medium">{auth.active_pesantren.name}</div>
                </div>
              )}
              {memberships.length > 1 && (
                <div className="mx-1 border-b border-neutral-100 px-3 py-3 xl:hidden">
                  <label htmlFor="account-tenant-switch" className="mb-1.5 block text-xs font-medium text-neutral-500">Ganti pesantren aktif</label>
                  <select
                    id="account-tenant-switch"
                    value={selectedTenantId}
                    disabled={tenantSwitching}
                    aria-invalid={Boolean(tenantError)}
                    onChange={(event) => switchTenant(event.target.value)}
                    className="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 disabled:cursor-wait disabled:bg-neutral-100"
                  >
                    {memberships.map((item) => <option key={`${item.id}-${item.role || item.role_label}`} value={item.id}>{item.name || item.pesantren} {item.role_label ? `- ${item.role_label}` : ''}</option>)}
                  </select>
                </div>
              )}
              {tenantError && <div className="mx-2 my-2 rounded-md bg-red-50 px-3 py-2 text-xs font-medium text-red-700">{tenantError}</div>}
              <Link href={settingsHref} className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition hover:bg-neutral-50" onClick={() => setAccountOpen(false)}><icons.Settings className="h-4 w-4" /> Pengaturan</Link>
              <button type="button" onClick={logout} className="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm text-red-600 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-200"><icons.LogOut className="h-4 w-4" /> Logout</button>
            </div>
          )}
        </div>
      </div>
      {tenantError && (
        <div role="alert" className="fixed right-4 top-16 z-50 flex max-w-sm items-start gap-3 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700 shadow-lg">
          <span>{tenantError}</span>
          <button type="button" onClick={() => setTenantError('')} className="-mr-1 rounded p-1 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-300" aria-label="Tutup pesan gagal ganti pesantren"><icons.X className="h-4 w-4" /></button>
        </div>
      )}
    </header>
  );
}

function Header({ title, subtitle }) {
  return <header className="page-heading border-b border-neutral-200 px-0.5 pb-4 pt-1"><div className="flex items-center gap-2 text-[11px] font-semibold uppercase text-primary-700"><span className="h-1.5 w-1.5 bg-primary-600" /> Pesantrenku <span className="text-neutral-300">/</span> Ruang kerja</div><h1 className="mt-2 break-words text-2xl font-semibold leading-tight text-neutral-900 sm:text-[1.75rem]">{title}</h1><p className="mt-1.5 max-w-4xl break-words text-sm leading-6 text-neutral-600">{subtitle}</p></header>;
}

function StatCard({ item }) {
  const Icon = icons[item.icon] || icons.Activity;
  const visualTones = ['success', 'info', 'warning', 'purple'];
  const visualTone = item.tone || visualTones[String(item.label || '').length % visualTones.length];
  return <article className={`stat-card stat-tone-${visualTone} min-w-0 border border-neutral-200 bg-white p-4`}><div className="flex items-start justify-between gap-3"><div className="min-w-0"><p className="break-words text-xs font-medium leading-5 text-neutral-500">{item.label}</p><p className="mt-1.5 break-words text-2xl font-semibold leading-tight text-neutral-900">{item.value}</p></div><div className={`stat-icon grid h-10 w-10 shrink-0 place-items-center rounded-md ${tone[visualTone] || tone.success}`}><Icon className="h-[1.125rem] w-[1.125rem]" /></div></div><p className="mt-3 break-words border-t border-neutral-100 pt-2.5 text-xs font-medium leading-5 text-neutral-600">{item.trend}</p></article>;
}

function ChartCard({ title, children }) {
  return <PanelCard title={title}>{children}</PanelCard>;
}

function ChartLoading() {
  return <StatePanel icon="Loader2" title="Memuat visualisasi" description="Data grafik sedang disiapkan." loading />;
}

function SideItem({ label, icon, href, active, light, collapsed, onClick }) {
  const Icon = icons[icon] || icons.CircleCheck;
  return <Link href={href} onClick={onClick} aria-label={collapsed ? label : undefined} title={collapsed ? label : undefined} aria-current={active ? 'page' : undefined} className={`side-item group relative flex min-h-10 items-center gap-3 rounded-md px-3 py-2 text-[13px] font-medium leading-5 transition focus:outline-none focus:ring-2 focus:ring-primary-500/50 ${active ? light ? 'bg-primary-50 text-primary-800' : 'bg-white/15 text-white' : light ? 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900' : 'text-white/75 hover:bg-white/10 hover:text-white'}`}><span className={`absolute inset-y-2 left-0 w-0.5 transition ${active ? light ? 'bg-primary-600' : 'bg-primary-400' : 'bg-transparent'}`} /><Icon className="h-4 w-4 shrink-0" />{!collapsed && <span className="min-w-0 break-words">{label}</span>}</Link>;
}

function DataPreview({ title, rows, columns, shared, href }) {
  return <PanelCard title={title} action="Lihat Semua" actionHref={href}><Table rows={rows} columns={columns} shared={shared} compact /></PanelCard>;
}

function rolePrimaryRows(panel, dashboard, shared) {
  const map = {
    santri: { title: 'Tagihan Terbaru', rows: dashboard.invoices, href: '/santri/module/billing' },
    'wali-murid': { title: 'Data Anak', rows: dashboard.students, href: '/wali-murid/module/anak-saya' },
    guru: { title: 'Jadwal Mengajar', rows: dashboard.schedules, href: '/guru/module/jadwal-mengajar' },
    musyrif: { title: 'Santri Binaan', rows: dashboard.students, href: '/musyrif/module/santri-binaan' },
    klinik: { title: 'Antrian Klinik', rows: shared.medicalQueues, href: '/klinik/module/antrian' },
  };

  return map[panel] || { title: 'Data Terbaru', rows: dashboard.students, href: `/dashboard/${panel}` };
}

function roleSummaryIcon(label) {
  const lower = label.toLowerCase();
  if (lower.includes('nilai')) return 'BarChart3';
  if (lower.includes('hafalan')) return 'BookOpen';
  if (lower.includes('pelanggaran')) return 'TriangleAlert';
  if (lower.includes('tagihan')) return 'Wallet';
  return 'CircleCheck';
}

function roleSummaryTone(label) {
  const lower = label.toLowerCase();
  if (lower.includes('pelanggaran')) return 'warning';
  if (lower.includes('tagihan')) return 'info';
  if (lower.includes('hafalan')) return 'purple';
  return 'success';
}

function tabbedModuleMeta(panel, module, shared) {
  const tabbed = {
    owner: {
      'paket-dan-subscription': {
        title: 'Paket & Subscription',
        subtitle: 'Pantau paket SaaS dan subscription tenant aktif.',
        tabs: [
          tabMeta('subscriptions', 'Subscription', shared.subscriptions, panel, { canCreate: false, canEdit: false, canDelete: false, showInsights: false }),
          tabMeta('plans', 'Paket', shared.plans, panel, { canCreate: false, canEdit: false, canDelete: false, showInsights: false }),
        ],
      },
    },
    akademik: {
      'data-master': {
        title: 'Data Master',
        subtitle: 'Kelola data master akademik dalam tab resource yang terpisah.',
        tabs: [
          tabMeta('programs', 'Program', shared.programs, panel),
          tabMeta('academic-years', 'Tahun Ajaran', shared.academicYears, panel),
        ],
      },
      guru: {
        title: 'Guru',
        subtitle: 'Kelola data guru dan penugasan kelas-mapel secara eksplisit.',
        tabs: [
          tabMeta('teachers', 'Guru', shared.teachers, panel),
          tabMeta('teacher-assignments', 'Penugasan Guru', shared.teacherAssignments, panel),
        ],
      },
    },
    tu: {
      'guru-dan-staf': {
        title: 'Guru & Staf',
        subtitle: 'Kelola data guru dan staf dengan resource yang terpisah.',
        tabs: [
          tabMeta('teachers', 'Guru', shared.teachers, panel),
          tabMeta('staff', 'Staf', shared.staff, panel),
        ],
      },
      'data-master': {
        title: 'Data Master',
        subtitle: 'Kelola program, kelas, dan kamar tanpa mencampur resource.',
        tabs: [
          tabMeta('programs', 'Program', shared.programs, panel),
          tabMeta('classes', 'Kelas', shared.classes, panel),
          tabMeta('rooms', 'Kamar', shared.rooms, panel),
        ],
      },
      kamar: {
        title: 'Kamar',
        subtitle: 'Kelola kamar dan penugasan musyrif binaan.',
        tabs: [
          tabMeta('rooms', 'Kamar', shared.rooms, panel),
          tabMeta('musyrif-room-assignments', 'Penugasan Musyrif', shared.musyrifRoomAssignments, panel),
        ],
      },
    },
  };

  return tabbed[panel]?.[module] || null;
}

function tabMeta(resource, label, rows, panel, overrides = {}) {
  const normalizedRows = rowsOf(rows);
  return {
    key: resource,
    label,
    meta: {
      kind: 'crud',
      title: label,
      subtitle: moduleSubtitle(panel, label),
      rows: normalizedRows,
      resource,
      panel,
      fields: fieldsFor(resource, normalizedRows),
      ...overrides,
    },
  };
}

function roleActionMeta(panel, module, dashboard, shared) {
  const resource = roleActionResource(panel, module);
  if (!resource) return null;

  const readMeta = roleModuleMeta(panel, module, dashboard, shared);
  const action = roleActionConfig(panel, module);
  const rows = action.rows ? action.rows(shared, dashboard) : readMeta.rows;

  return {
    kind: 'crud',
    title: readMeta.title,
    subtitle: action.subtitle || `Kelola ${readMeta.title.toLowerCase()} sesuai akses ${roleLabel(panel).toLowerCase()} pada pesantren aktif.`,
    tableTitle: readMeta.title,
    rows,
    resource,
    panel,
    fields: action.fields ? action.fields.map(parseField) : fieldsFor(resource, rows),
    canCreate: action.canCreate !== false,
    canEdit: action.canEdit !== false,
    canDelete: action.canDelete !== false,
    canView: action.canView !== false,
    canExport: action.canExport !== false,
    createLabel: action.createLabel,
    showInsights: false,
  };
}

function roleActionResource(panel, module) {
  const maps = {
    santri: {
      perizinan: 'permits',
    },
    'wali-murid': {
      perizinan: 'permits',
      pembayaran: 'payments',
    },
    guru: {
      'presensi-kelas': 'attendances',
      nilai: 'grades',
      tugas: 'assignments',
      materi: 'materials',
      'catatan-siswa': 'daily-notes',
    },
    musyrif: {
      'absensi-asrama': 'dorm-attendances',
      perizinan: 'permits',
      pelanggaran: 'violations',
      'catatan-harian': 'daily-notes',
      'mutasi-kamar': 'room-mutations',
    },
    klinik: {
      antrian: 'medical-queues',
      'rekam-medis': 'medical-records',
      pemeriksaan: 'medical-examinations',
      obat: 'medicines',
      'surat-sakit': 'sick-letters',
      rujukan: 'referrals',
      'jadwal-dokter': 'doctors',
      'stok-obat': 'medicine-stocks',
    },
  };

  return maps[panel]?.[module] || null;
}

function roleActionConfig(panel, module) {
  const commonReadWrite = { canCreate: true, canEdit: true, canDelete: true };
  const configs = {
    santri: {
      perizinan: { ...commonReadWrite, canDelete: false, createLabel: 'Ajukan Izin', subtitle: 'Ajukan dan pantau status perizinan santri pada pesantren aktif.' },
    },
    'wali-murid': {
      perizinan: { ...commonReadWrite, canDelete: false, createLabel: 'Ajukan Izin Anak', subtitle: 'Ajukan izin untuk anak dan pantau proses persetujuannya.' },
      pembayaran: { ...commonReadWrite, canDelete: false, createLabel: 'Konfirmasi Pembayaran', subtitle: 'Catat konfirmasi pembayaran tagihan anak.' },
    },
    guru: {
      'presensi-kelas': { ...commonReadWrite, createLabel: 'Input Presensi', subtitle: 'Input dan koreksi presensi kelas sesuai jadwal mengajar.' },
      nilai: { ...commonReadWrite, createLabel: 'Input Nilai', subtitle: 'Input dan kelola nilai santri untuk mata pelajaran yang diampu.' },
      tugas: { ...commonReadWrite, createLabel: 'Buat Tugas', subtitle: 'Buat tugas kelas dan pantau status publikasinya.' },
      materi: { ...commonReadWrite, createLabel: 'Tambah Materi', subtitle: 'Unggah atau catat materi belajar untuk kelas.' },
      'catatan-siswa': { ...commonReadWrite, createLabel: 'Tambah Catatan', subtitle: 'Catat perkembangan siswa untuk wali kelas dan wali santri.' },
    },
    musyrif: {
      'absensi-asrama': { ...commonReadWrite, createLabel: 'Input Absensi', subtitle: 'Input absensi kegiatan asrama dan kamar binaan.' },
      perizinan: { ...commonReadWrite, createLabel: 'Catat Perizinan', subtitle: 'Catat dan proses perizinan santri binaan.' },
      pelanggaran: { ...commonReadWrite, createLabel: 'Catat Pelanggaran', subtitle: 'Catat pelanggaran dan poin pembinaan santri.' },
      'catatan-harian': { ...commonReadWrite, createLabel: 'Tambah Catatan', subtitle: 'Catat aktivitas harian dan pembinaan santri.' },
      'mutasi-kamar': { ...commonReadWrite, createLabel: 'Tambah Mutasi', subtitle: 'Kelola perpindahan kamar santri.' },
    },
    klinik: {
      antrian: { ...commonReadWrite, createLabel: 'Tambah Antrian', subtitle: 'Kelola antrian pemeriksaan klinik pesantren.' },
      'rekam-medis': { ...commonReadWrite, createLabel: 'Tambah Rekam Medis', subtitle: 'Catat keluhan, diagnosis, dan riwayat berobat santri.' },
      pemeriksaan: { ...commonReadWrite, createLabel: 'Input Pemeriksaan', subtitle: 'Catat hasil pemeriksaan klinis dari rekam medis.' },
      obat: { ...commonReadWrite, createLabel: 'Tambah Obat', subtitle: 'Kelola data master obat klinik.' },
      'surat-sakit': { ...commonReadWrite, createLabel: 'Buat Surat Sakit', subtitle: 'Buat surat sakit berdasarkan pemeriksaan santri.' },
      rujukan: { ...commonReadWrite, createLabel: 'Buat Rujukan', subtitle: 'Catat rujukan santri ke fasilitas kesehatan.' },
      'jadwal-dokter': { ...commonReadWrite, createLabel: 'Tambah Jadwal', subtitle: 'Kelola dokter dan jadwal pelayanan klinik.' },
      'stok-obat': { ...commonReadWrite, createLabel: 'Update Stok', subtitle: 'Catat mutasi stok obat masuk dan keluar.' },
    },
  };

  return configs[panel]?.[module] || {};
}

function workflowActionsFor(meta) {
  const panel = meta.panel;
  const resource = meta.resource;
  const isPending = (row) => ['pending', 'waiting', 'paid'].includes(String(row.status || '').toLowerCase());
  const isQueueOpen = (row) => ['pending', 'waiting', 'in_progress'].includes(String(row.status || '').toLowerCase());

  if (resource === 'permits' && ['musyrif', 'tu', 'owner'].includes(panel)) {
    return [
      { key: 'approve', label: 'Setujui', transition: 'approve', icon: 'CheckCircle', success: 'Perizinan disetujui.', show: isPending },
      { key: 'reject', label: 'Tolak', transition: 'reject', icon: 'XCircle', tone: 'danger', requiresNote: true, success: 'Perizinan ditolak.', show: isPending },
    ];
  }

  if (resource === 'permits' && ['santri', 'wali-murid'].includes(panel)) {
    return [
      { key: 'cancel', label: 'Batalkan', transition: 'cancel', icon: 'XCircle', tone: 'danger', requiresNote: true, success: 'Perizinan dibatalkan.', show: (row) => ['pending', 'approved'].includes(String(row.status || '').toLowerCase()) },
    ];
  }

  if (resource === 'payments' && ['keuangan', 'owner'].includes(panel)) {
    return [
      { key: 'verify', label: 'Verifikasi', transition: 'verify', icon: 'CheckCircle', success: 'Pembayaran berhasil diverifikasi.', show: isPending },
      { key: 'reject', label: 'Tolak', transition: 'reject', icon: 'XCircle', tone: 'danger', requiresNote: true, success: 'Pembayaran ditolak.', show: isPending },
    ];
  }

  if (resource === 'medical-queues' && panel === 'klinik') {
    return [
      { key: 'start', label: 'Mulai Pemeriksaan', transition: 'start', icon: 'PlayCircle', success: 'Antrian masuk proses pemeriksaan.', show: (row) => ['pending', 'waiting'].includes(String(row.status || '').toLowerCase()) },
      { key: 'finish', label: 'Selesai', transition: 'finish', icon: 'CheckCircle', success: 'Antrian diselesaikan.', show: isQueueOpen },
      { key: 'cancel', label: 'Batalkan', transition: 'cancel', icon: 'XCircle', tone: 'danger', requiresNote: true, success: 'Antrian dibatalkan.', show: isQueueOpen },
    ];
  }

  return [];
}

function roleModuleMeta(panel, module, dashboard, shared) {
  const title = titleFromSlug(module);
  const common = {
    profil: profileRows(dashboard),
    pengaturan: profileRows(dashboard),
    notifikasi: rowsOf(shared.notifications),
    pengumuman: rowsOf(dashboard.announcements),
  };
  const academicRows = rowsOf(shared.grades);
  const invoiceRows = rowsOf(dashboard.invoices || shared.invoices);
  const medicalRows = rowsOf(dashboard.medical || shared.medicalRecords);

  const maps = {
    santri: {
      ...common,
      akademik: academicRows,
      raport: rowsOf(shared.reportCards).length ? shared.reportCards : academicRows,
      tahfidz: rowsOf(shared.memorizationRecords),
      presensi: rowsOf(shared.attendances),
      perizinan: rowsOf(shared.permits),
      pelanggaran: rowsOf(dashboard.violations || shared.violations),
      kesehatan: medicalRows,
      billing: invoiceRows,
      'qr-scanner': [{ kode: 'PESANTENKU-SANTRI', status: 'Aktif', keterangan: 'QR santri siap digunakan' }],
      jadwal: rowsOf(dashboard.schedules || shared.schedules),
    },
    'wali-murid': {
      ...common,
      'anak-saya': rowsOf(dashboard.students),
      akademik: academicRows,
      raport: rowsOf(shared.reportCards).length ? shared.reportCards : academicRows,
      presensi: rowsOf(shared.attendances),
      perizinan: rowsOf(shared.permits),
      pembayaran: rowsOf(shared.payments),
      tagihan: invoiceRows,
      'riwayat-pembayaran': rowsOf(shared.payments),
      pelanggaran: rowsOf(dashboard.violations || shared.violations),
      kesehatan: medicalRows,
      'jadwal-anak': rowsOf(dashboard.schedules || shared.schedules),
    },
    guru: {
      ...common,
      'jadwal-mengajar': rowsOf(dashboard.schedules || shared.schedules),
      'kelas-saya': rowsOf(shared.classes),
      'presensi-kelas': rowsOf(shared.attendances),
      nilai: academicRows,
      tugas: rowsOf(shared.assignments),
      materi: rowsOf(shared.materials),
      raport: rowsOf(shared.reportCards).length ? shared.reportCards : academicRows,
      'catatan-siswa': rowsOf(shared.dailyNotes),
    },
    musyrif: {
      ...common,
      'santri-binaan': rowsOf(dashboard.students || shared.students),
      kamar: rowsOf(shared.rooms),
      'absensi-asrama': rowsOf(shared.dormAttendances),
      perizinan: rowsOf(shared.permits),
      pelanggaran: rowsOf(shared.violations),
      'catatan-harian': rowsOf(shared.dailyNotes),
      'mutasi-kamar': rowsOf(shared.roomMutations),
      'jadwal-piket': rowsOf(shared.schedules),
      laporan: rowsOf(shared.students),
    },
    klinik: {
      ...common,
      antrian: rowsOf(shared.medicalQueues),
      'rekam-medis': rowsOf(shared.medicalRecords),
      pemeriksaan: rowsOf(shared.medicalExaminations).length ? shared.medicalExaminations : shared.medicalRecords,
      obat: rowsOf(shared.medicines),
      'riwayat-berobat': rowsOf(shared.medicalRecords),
      'surat-sakit': rowsOf(shared.sickLetters),
      rujukan: rowsOf(shared.referrals),
      'jadwal-dokter': rowsOf(shared.doctors),
      'stok-obat': rowsOf(shared.medicineStocks).length ? shared.medicineStocks : shared.medicines,
      'laporan-kesehatan': rowsOf(shared.medicalRecords),
    },
  };

  const rows = maps[panel]?.[module] || [];

  return {
    title,
    subtitle: `Informasi ${title.toLowerCase()} untuk ${roleLabel(panel).toLowerCase()}.`,
    tableTitle: title,
    rows,
    profile: module === 'profil' || module === 'pengaturan',
    cards: module === 'profil' || module === 'pengaturan' ? (dashboard.summary || []).map((item) => ({ label: item.label, value: item.value, trend: 'Data akun aktif', icon: roleSummaryIcon(item.label), tone: roleSummaryTone(item.label) })) : [],
    secondaryRows: ['akademik', 'raport', 'kesehatan', 'laporan', 'laporan-kesehatan'].includes(module) ? rowsOf(dashboard.announcements) : null,
    secondaryTitle: 'Pengumuman Terkait',
    actionType: module === 'qr-scanner' ? 'qr' : null,
  };
}

function profileRows(dashboard) {
  return [
    { informasi: 'Nama', nilai: dashboard.profile?.name || '-' },
    { informasi: 'Keterangan', nilai: dashboard.profile?.subtitle || '-' },
    ...(dashboard.summary || []).map((item) => ({ informasi: item.label, nilai: item.value })),
  ];
}

function dashboardPreview(panel, shared) {
  if (panel === 'super-admin') {
    return [
      { title: 'Pesantren Terbaru', rows: shared.pesantrens, columns: ['name', 'city', 'status'], href: '/super-admin/module/pesantrens' },
      { title: 'Owner & User Platform', rows: shared.memberships, columns: ['pengguna', 'role', 'status'], href: '/super-admin/module/owner' },
      { title: 'Subscription Aktif', rows: shared.subscriptions, columns: ['pesantren_name', 'plan_name', 'status'], href: '/super-admin/module/subscriptions' },
    ];
  }

  if (panel === 'owner') {
    return [
      { title: 'User & Role Tenant', rows: shared.memberships, columns: ['pengguna', 'role', 'status'], href: '/owner/module/user-dan-role' },
      { title: 'Modul Tenant', rows: shared.modules, columns: ['nama_modul', 'role_akses', 'status'], href: '/owner/module/modul-aktif' },
      { title: 'Paket & Subscription', rows: shared.subscriptions, columns: ['plan_name', 'status', 'ends_at'], href: '/owner/module/paket-dan-subscription' },
    ];
  }

  if (panel === 'keuangan') {
    return [
      { title: 'Tagihan Terbaru', rows: shared.invoices, href: '/keuangan/module/tagihan' },
      { title: 'Pembayaran Terbaru', rows: shared.payments, href: '/keuangan/module/pembayaran' },
      { title: 'Tunggakan Terbesar', rows: shared.arrears, href: '/keuangan/module/tunggakan' },
    ];
  }

  if (panel === 'akademik') {
    return [
      { title: '5 Nilai Rata-rata Tertinggi', rows: shared.grades, href: '/akademik/module/penilaian' },
      { title: 'Jadwal Pelajaran', rows: shared.schedules, href: '/akademik/module/jadwal-pelajaran' },
      { title: 'Kelas Aktif', rows: shared.classes, href: '/akademik/module/kelas' },
    ];
  }

  return [
    { title: 'Data Santri', rows: shared.students, href: '/tu/module/santri' },
    { title: 'Surat Menyurat', rows: shared.letters, href: '/tu/module/surat-menyurat' },
    { title: 'Kamar & Hunian', rows: shared.rooms, href: '/tu/module/kamar' },
  ];
}

function Table({ rows = [], compact = false, columns: preferredColumns, shared = {}, meta, loading = false, selectable = false, selectedIds = [], onToggleRow, onToggleAll, onView, onEdit, onDelete, customActions = [] }) {
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
  if (loading) return <StatePanel icon="Loader2" title="Memuat data" description="Daftar terbaru sedang diambil dari server." loading />;
  if (!normalized.length) return <StatePanel title="Belum ada data" description="Data yang tersedia akan ditampilkan di tabel ini." />;
  return (
    <div className="min-w-0">
      <div className="mb-2 flex items-center gap-2 text-[11px] font-medium text-neutral-500 md:hidden" aria-hidden="true"><icons.MoveHorizontal className="h-3.5 w-3.5" /> Geser untuk melihat kolom lainnya</div>
      <div className="data-table-wrap relative min-w-0 overflow-x-auto border border-neutral-200 [scrollbar-width:thin]">
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

function ActivityFeed({ items = [], emptyTitle }) {
  const normalized = rowsOf(items);
  if (!normalized.length) return <StatePanel title={emptyTitle} description="Informasi terbaru akan tampil di panel ini." compact />;
  return <div>{normalized.map((item, index) => <ActivityRow key={item.id || item.title || index} item={item} />)}</div>;
}

function ActivityRow({ item }) {
  return <div className="activity-row flex min-w-0 gap-3 border-b border-neutral-100 py-3 last:border-b-0"><div className="grid h-8 w-8 shrink-0 place-items-center rounded-md bg-primary-50 text-primary-700"><icons.CircleCheck className="h-4 w-4" /></div><div className="min-w-0 flex-1"><div className="break-words text-sm font-semibold leading-5 text-neutral-800">{item.title}</div><div className="mt-0.5 break-words text-xs leading-5 text-neutral-500">{item.description}</div></div><div className="max-w-24 shrink-0 text-right text-[11px] font-medium leading-4 text-neutral-400">{item.time}</div></div>;
}

function LegendList({ items }) {
  return items.length ? <div className="mt-1 space-y-1">{items.map((item, i) => <div key={item.name} className="flex items-start justify-between gap-3 rounded-md px-2 py-1.5 text-sm hover:bg-neutral-50"><span className="flex min-w-0 items-start gap-2"><ColorMarker color={colors[i % colors.length]} /><span className="break-words leading-5">{item.name}</span></span><span className="shrink-0 font-semibold text-neutral-700">{typeof item.value === 'number' && item.value > 100000 ? rupiah(item.value) : item.value}</span></div>)}</div> : <StatePanel title="Belum ada distribusi" description="Data visualisasi belum tersedia." compact />;
}

function ProgressList({ items }) {
  return items.length ? <div className="space-y-4">{items.map((item, i) => {
    const percentage = clampPercent(item.value);
    return <div key={item.name}><div className="mb-1.5 flex items-start justify-between gap-3 text-sm"><span className="min-w-0 break-words font-medium leading-5 text-neutral-700">{item.name}</span><span className="shrink-0 font-semibold text-neutral-600">{percentage}%</span></div><progress className={`app-progress app-progress-tone-${i % colors.length}`} max="100" value={percentage} aria-label={`${item.name}: ${percentage}%`}>{percentage}%</progress></div>;
  })}</div> : <StatePanel title="Belum ada ringkasan" description="Metrik akan muncul setelah data tersedia." compact />;
}

function ColorMarker({ color }) {
  return <svg className="mt-1.5 h-2.5 w-2.5 shrink-0" viewBox="0 0 10 10" width="10" height="10" aria-hidden="true"><rect width="10" height="10" rx="2" fill={color} /></svg>;
}

function QuickActions({ items, panel }) {
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

function quickActionHref(panel, item) {
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

function activityHref(panel) {
  return {
    'super-admin': '/super-admin/module/aktivitas-sistem',
    owner: '/owner/module/audit-tenant',
    keuangan: '/keuangan/module/tagihan',
    akademik: '/akademik/module/pengumuman',
    tu: '/tu/module/pengumuman',
  }[panel] || `/dashboard/${panel}`;
}

function Logo() {
  return <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-current/15 bg-white/10 shadow-sm"><svg viewBox="0 0 48 48" className="h-8 w-8" fill="none"><path d="M24 5 43 41H5L24 5Z" stroke="currentColor" strokeWidth="2" /><path d="M24 15v18M18 32c4-2 8-2 12 0M16 36h16" stroke="currentColor" strokeWidth="2" strokeLinecap="round" /></svg></div>;
}
