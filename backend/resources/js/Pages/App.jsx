import React, { Suspense, useCallback, useEffect, useMemo, useState } from 'react';
import { Link, icons } from '../lib/deps.jsx';
import { moduleMeta, panels, roleLabel, rowsOf, slug } from '../components/app/appSupport.jsx';
import { PanelCard, useDialogLifecycle } from '../components/app/AppDialogs.jsx';
import { CrudModule } from '../components/app/CrudModule.jsx';
import { QrTool } from '../components/app/QrTool.jsx';
import { DesktopSidebar, MobileSidebar } from '../components/app/Sidebar.jsx';
import { ShellContext } from '../components/app/ShellContext.jsx';
import CommandPalette from '../components/app/CommandPalette.jsx';
import ShortcutsHelp from '../components/app/ShortcutsHelp.jsx';
import ToastHost from '../components/app/ToastHost.jsx';
import NavigationProgress from '../components/app/NavigationProgress.jsx';
import ErrorBoundary from '../components/app/ErrorBoundary.jsx';
import { useTheme } from '../lib/theme.js';
import { SystemModule } from '../components/app/SystemModule.jsx';
import { Topbar } from '../components/app/Topbar.jsx';
import { ActivityFeed, ChartCard, ChartLoading, DataPreview, Header, LegendList, ProgressList, QuickActions, StatCard, Table, activityHref } from '../components/app/Widgets.jsx';
import { DashboardDonutVisualization, DashboardLineVisualization } from '../components/app/lazyCharts.jsx';
import { dashboardPreview, roleActionMeta, roleModuleMeta, rolePrimaryRows, roleSummaryIcon, roleSummaryTone, tabbedModuleMeta } from '../components/app/moduleMeta.jsx';

const SIDEBAR_KEY = 'pk-sidebar-collapsed';

const reloadPage = () => window.location.reload();

function readCollapsed() {
  try {
    return window.localStorage.getItem(SIDEBAR_KEY) === '1';
  } catch {
    return false;
  }
}

function isTypingTarget(target) {
  if (!(target instanceof HTMLElement)) return false;
  return target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName);
}

export default function App({ panel = 'super-admin', screen = 'dashboard', module, dashboard, shared, auth, app }) {
  const config = panels[panel] || panels['super-admin'];
  const [collapsed, setCollapsed] = useState(readCollapsed);
  const [mobileNavOpen, setMobileNavOpen] = useState(false);
  const [paletteOpen, setPaletteOpen] = useState(false);
  const [helpOpen, setHelpOpen] = useState(false);
  const mobileNavRef = useDialogLifecycle(() => setMobileNavOpen(false), mobileNavOpen);
  const { toggle: toggleTheme } = useTheme();

  const toggleCollapsed = useCallback(() => {
    setCollapsed((current) => {
      const next = !current;
      try {
        window.localStorage.setItem(SIDEBAR_KEY, next ? '1' : '0');
      } catch {
        // Preferensi sidebar bersifat opsional.
      }
      return next;
    });
  }, []);

  const openPalette = useCallback(() => {
    setHelpOpen(false);
    setPaletteOpen(true);
  }, []);

  useEffect(() => {
    const onKeyDown = (event) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        setPaletteOpen((open) => !open);
        return;
      }
      if (event.ctrlKey || event.metaKey || event.altKey || event.defaultPrevented || isTypingTarget(event.target)) return;
      if (document.querySelector('[role="dialog"][aria-modal="true"]')) return;
      if (event.key === '/') {
        event.preventDefault();
        openPalette();
      } else if (event.key === '?') {
        event.preventDefault();
        setHelpOpen(true);
      } else if (event.key === '[') {
        toggleCollapsed();
      } else if (event.key.toLowerCase() === 't') {
        toggleTheme();
      }
    };
    document.addEventListener('keydown', onKeyDown);
    return () => document.removeEventListener('keydown', onKeyDown);
  }, [openPalette, toggleCollapsed, toggleTheme]);

  const shell = useMemo(() => ({ panel, auth, openPalette }), [panel, auth, openPalette]);

  return (
    <ShellContext.Provider value={shell}>
      <div className="app-shell min-h-screen overflow-x-hidden text-neutral-900 selection:bg-primary-100 selection:text-primary-900">
        <a href="#konten-utama" className="pk-skip-link">Lewati ke konten utama</a>
        <NavigationProgress />
        <DesktopSidebar panel={panel} config={config} screen={screen} module={module} auth={auth} collapsed={collapsed} onToggle={toggleCollapsed} />
        {mobileNavOpen && <MobileSidebar dialogRef={mobileNavRef} panel={panel} config={config} screen={screen} module={module} auth={auth} onClose={() => setMobileNavOpen(false)} />}

        <main id="konten-utama" tabIndex={-1} className={`workspace-main ${collapsed ? 'lg:pl-20' : 'lg:pl-72'} relative min-w-0 transition-[padding] duration-300 focus:outline-none`}>
          <Topbar panel={panel} auth={auth} shared={shared} app={app} onMenuOpen={() => setMobileNavOpen(true)} />
          <div key={`${screen}:${module || ''}`} className="workspace-content relative mx-auto max-w-[1800px] p-3.5 sm:p-5 lg:p-6">
            <ErrorBoundary resetKey={`${screen}:${module || ''}`} title="Halaman ini gagal ditampilkan">
              {screen === 'dashboard' ? <Dashboard dashboard={dashboard} shared={shared} panel={panel} /> : <ModulePage panel={panel} module={module} shared={shared} dashboard={dashboard} />}
            </ErrorBoundary>
          </div>
        </main>

        <CommandPalette open={paletteOpen} onClose={() => setPaletteOpen(false)} panel={panel} auth={auth} />
        <ShortcutsHelp open={helpOpen} onClose={() => setHelpOpen(false)} />
        <ToastHost />
      </div>
    </ShellContext.Provider>
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
          <ErrorBoundary compact title="Grafik gagal dimuat" onRetry={reloadPage}><Suspense fallback={<ChartLoading />}><DashboardLineVisualization data={dashboard.lineChart || []} /></Suspense></ErrorBoundary>
        </ChartCard>
        <ChartCard title={panel === 'akademik' ? 'Distribusi Kelas' : panel === 'keuangan' ? 'Pemasukan per Kategori' : 'Pengguna berdasarkan Role'}>
          <ErrorBoundary compact title="Grafik gagal dimuat" onRetry={reloadPage}><Suspense fallback={<ChartLoading />}><DashboardDonutVisualization data={dashboard.donut || []} /></Suspense></ErrorBoundary>
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
