import React, { lazy, Suspense, useEffect, useMemo, useRef, useState } from 'react';
import { Link, router, icons } from '../lib/deps.jsx';

const DashboardLineVisualization = lazy(() => import('../components/DashboardCharts.jsx').then((module) => ({ default: module.DashboardLineVisualization })));
const DashboardDonutVisualization = lazy(() => import('../components/DashboardCharts.jsx').then((module) => ({ default: module.DashboardDonutVisualization })));
const AnalyticsPanel = lazy(() => import('../components/AnalyticsPanel.jsx'));
const pendingIdempotencyKeys = new Map();

const panels = {
  'super-admin': {
    role: 'Super Admin',
    dark: true,
    menu: [
      ['Dashboard', 'LayoutDashboard', '/dashboard/super-admin'],
      ['Pesantren', 'School', '/super-admin/module/pesantrens'],
      ['Owner', 'Crown', '/super-admin/module/owner'],
      ['Subscription', 'BadgeCheck', '/super-admin/module/subscriptions'],
      ['Paket', 'CreditCard', '/super-admin/module/plans'],
      ['User & Role', 'Users', '/super-admin/module/users'],
      ['Modul Platform', 'Menu', '/super-admin/module/modules'],
      ['Integrasi & API', 'Cable', '/super-admin/module/integrasi-api'],
      ['Aktivitas Sistem', 'Activity', '/super-admin/module/aktivitas-sistem'],
      ['Log Audit', 'FileText', '/super-admin/module/audit-logs'],
      ['Login Monitoring', 'Shield', '/super-admin/module/login-monitoring'],
      ['Error Log', 'TriangleAlert', '/super-admin/module/error-logs'],
      ['Laporan Penggunaan', 'BarChart3', '/super-admin/module/laporan-penggunaan'],
      ['Laporan Pesantren', 'FileText', '/super-admin/module/laporan-pesantren'],
      ['Laporan Keuangan', 'Wallet', '/super-admin/module/laporan-keuangan'],
      ['Backup & Restore', 'Database', '/super-admin/module/backups'],
      ['System Update', 'RefreshCcw', '/super-admin/module/system-update'],
      ['Cache Management', 'HardDrive', '/super-admin/module/cache-management'],
      ['Pengaturan Sistem', 'Settings', '/super-admin/module/settings'],
    ],
  },
  owner: {
    role: 'Owner Pesantren',
    menu: ['Dashboard', 'Profil Pesantren', 'User & Role', 'Modul Aktif', 'Paket & Subscription', 'Audit Tenant', 'Backup & Restore', 'Pengaturan'].map((name) => [name, iconFor(name), `/owner/module/${slug(name)}`]),
  },
  keuangan: {
    role: 'Keuangan',
    menu: ['Dashboard', 'Santri', 'Wali Santri', 'Program & Unit', 'Komponen Biaya', 'Rekening Bank', 'Tahun Ajaran', 'Tagihan', 'Pembayaran', 'Riwayat Pembayaran', 'Tunggakan', 'Diskon & Keringanan', 'Pembayaran Massal', 'Laporan Pemasukan', 'Laporan Pengeluaran', 'Laporan Arus Kas', 'Laporan Tunggakan', 'Laporan Rekap Bulanan', 'Laporan per Santri', 'Pengaturan Umum', 'Pengguna & Akses'].map((name) => [name, iconFor(name), `/keuangan/module/${slug(name)}`]),
  },
  akademik: {
    role: 'Akademik',
    menu: ['Dashboard', 'Data Master', 'Kurikulum', 'Kelas', 'Mata Pelajaran', 'Guru', 'Jadwal Pelajaran', 'Presensi', 'Penilaian', 'Nilai Siswa', 'Rapor', 'Kalender Akademik', 'Kelulusan', 'Laporan Akademik', 'Laporan Nilai', 'Pengaturan'].map((name) => [name, iconFor(name), `/akademik/module/${slug(name)}`]),
  },
  tu: {
    role: 'Tata Usaha',
    light: true,
    menu: ['Dashboard', 'Santri', 'Guru & Staf', 'Kelas', 'Kamar', 'Data Master', 'Surat Menyurat', 'Mutasi Santri', 'Alumni', 'Pengumuman', 'Pengaturan'].map((name) => [name, iconFor(name), `/tu/module/${slug(name)}`]),
  },
  santri: {
    role: 'Santri',
    light: true,
    menu: ['Dashboard', 'Profil', 'Akademik', 'Raport', 'Tahfidz', 'Presensi', 'Perizinan', 'Pelanggaran', 'Kesehatan', 'Billing', 'Pengumuman', 'QR Scanner', 'Jadwal', 'Notifikasi', 'Pengaturan'].map((name) => [name, iconFor(name), `/santri/module/${slug(name)}`]),
  },
  'wali-murid': {
    role: 'Wali Murid',
    light: true,
    menu: ['Dashboard', 'Anak Saya', 'Akademik', 'Raport', 'Presensi', 'Perizinan', 'Pembayaran', 'Tagihan', 'Riwayat Pembayaran', 'Pelanggaran', 'Kesehatan', 'Jadwal Anak', 'Notifikasi', 'Profil'].map((name) => [name, iconFor(name), `/wali-murid/module/${slug(name)}`]),
  },
  guru: {
    role: 'Guru',
    light: true,
    menu: ['Dashboard', 'Jadwal Mengajar', 'Kelas Saya', 'Presensi Kelas', 'Nilai', 'Tugas', 'Materi', 'Raport', 'Catatan Siswa', 'Pengumuman', 'Profil', 'Pengaturan'].map((name) => [name, iconFor(name), `/guru/module/${slug(name)}`]),
  },
  musyrif: {
    role: 'Musyrif',
    light: true,
    menu: ['Dashboard', 'Santri Binaan', 'Kamar', 'Absensi Asrama', 'Perizinan', 'Pelanggaran', 'Catatan Harian', 'Mutasi Kamar', 'Jadwal Piket', 'Pengumuman', 'Laporan', 'Pengaturan'].map((name) => [name, iconFor(name), `/musyrif/module/${slug(name)}`]),
  },
  klinik: {
    role: 'Klinik',
    light: true,
    menu: ['Dashboard', 'Antrian', 'Rekam Medis', 'Pemeriksaan', 'Obat', 'Riwayat Berobat', 'Surat Sakit', 'Rujukan', 'Jadwal Dokter', 'Stok Obat', 'Pengumuman', 'Laporan Kesehatan', 'Profil', 'Pengaturan'].map((name) => [name, iconFor(name), `/klinik/module/${slug(name)}`]),
  },
};

const colors = ['#127A4F', '#3B82F6', '#8B5CF6', '#F59E0B', '#EF4444', '#22C55E'];
const tone = {
  success: 'bg-green-50 text-green-700',
  info: 'bg-blue-50 text-blue-700',
  warning: 'bg-amber-50 text-amber-700',
  error: 'bg-red-50 text-red-700',
  purple: 'bg-purple-50 text-purple-700',
};

const resourceFields = {
  pesantrens: ['name', 'city', 'phone', 'email', 'status', 'address'],
  users: ['name', 'email', 'username', 'phone', 'status', 'address', 'password'],
  plans: ['name', 'code', ['monthly_price', 'number'], ['max_students', 'number'], ['max_users', 'number'], 'status'],
  subscriptions: [['pesantren_id', 'select:pesantrens'], ['plan_id', 'select:plans'], 'status', ['starts_at', 'date'], ['ends_at', 'date'], ['amount', 'number']],
  'tenant-modules': [['pesantren_id', 'select:pesantrens'], 'module', ['is_enabled', 'boolean']],
  memberships: [['pesantren_id', 'select:pesantrens'], ['user_id', 'select:users'], ['role_id', 'select:roles'], 'status'],
  'academic-years': ['name', ['start_date', 'date'], ['end_date', 'date'], ['is_active', 'boolean']],
  programs: ['name', 'code', 'level', ['description', 'textarea']],
  classes: ['name', 'level', 'grade', 'homeroom_teacher', ['student_count', 'number'], 'status'],
  subjects: ['name', 'code', 'level', ['kkm', 'number'], 'teacher_name'],
  'teacher-assignments': [['teacher_id', 'select:teachers'], ['class_id', 'select:classes'], ['subject_id', 'select:subjects'], ['assigned_at', 'date'], ['ended_at', 'date'], 'status', ['note', 'textarea']],
  guardians: ['name', 'phone', 'email', ['address', 'textarea']],
  teachers: ['name', 'nip', 'subject', 'phone', 'status'],
  staff: ['name', 'nip', 'role', 'phone', 'status'],
  students: ['nis', 'name', ['gender', 'select:gender'], 'birth_place', ['birth_date', 'date'], 'room', 'status'],
  rooms: ['name', 'block', ['capacity', 'number'], ['occupied', 'number'], 'type', 'status'],
  'musyrif-room-assignments': [['user_id', 'select:users'], ['room_id', 'select:rooms'], ['assigned_at', 'date'], ['ended_at', 'date'], 'status', ['note', 'textarea']],
  attendances: [['student_id', 'select:students'], ['class_id', 'select:classes'], ['date', 'date'], 'session', 'status', ['note', 'textarea']],
  'dorm-attendances': [['student_id', 'select:students'], ['room_id', 'select:rooms'], ['date', 'date'], 'activity', 'status'],
  grades: [['student_id', 'select:students'], ['class_id', 'select:classes'], ['subject_id', 'select:subjects'], 'semester', ['assignment_score', 'number'], ['midterm_score', 'number'], ['final_score', 'number'], ['attitude_score', 'number'], ['average_score', 'number'], 'grade'],
  'report-cards': [['student_id', 'select:students'], 'semester', ['average_score', 'number'], 'status'],
  'memorization-records': [['student_id', 'select:students'], 'juz', 'surah', 'ayat_range', ['progress_percent', 'number'], ['recorded_at', 'date']],
  schedules: [['class_id', 'select:classes'], ['subject_id', 'select:subjects'], 'day', 'time', 'teacher_name', 'room_name', 'type'],
  assignments: [['class_id', 'select:classes'], ['subject_id', 'select:subjects'], 'title', ['due_date', 'date'], 'status'],
  materials: [['class_id', 'select:classes'], ['subject_id', 'select:subjects'], 'title', 'file_type', 'file_size', 'url'],
  announcements: ['title', ['body', 'textarea'], 'category', 'audience', ['published_at', 'date']],
  violations: [['student_id', 'select:students'], 'title', ['points', 'number'], 'category', ['date', 'date'], ['note', 'textarea']],
  permits: [['student_id', 'select:students'], 'type', ['start_at', 'datetime-local'], ['end_at', 'datetime-local'], ['reason', 'textarea'], 'status'],
  'daily-notes': [['student_id', 'select:students'], ['noted_at', 'datetime-local'], ['note', 'textarea']],
  invoices: [['student_id', 'select:students'], 'invoice_number', 'period', ['amount', 'number'], ['due_date', 'date'], 'status'],
  'invoice-items': [['invoice_id', 'select:invoices'], 'name', ['amount', 'number']],
  payments: [['invoice_id', 'select:invoices'], ['student_id', 'select:students'], ['payment_method_id', 'select:paymentMethods'], ['amount', 'number'], ['paid_at', 'datetime-local'], 'status', 'reference'],
  'payment-methods': ['name', 'status'],
  'bank-accounts': ['bank_name', 'account_number', 'account_holder', ['balance', 'number'], ['is_primary', 'boolean']],
  expenses: ['title', ['amount', 'number'], ['date', 'date'], ['note', 'textarea']],
  'cashflow-entries': [['type', 'select:cashflowType'], 'title', ['amount', 'number'], ['date', 'date']],
  discounts: [['student_id', 'select:students'], 'name', ['amount', 'number'], 'status'],
  letters: ['number', 'subject', ['type', 'select:letterType'], 'destination', ['date', 'date'], 'status'],
  'room-mutations': [['student_id', 'select:students'], 'from_room', 'to_room', ['date', 'date'], 'status'],
  alumni: ['name', 'graduation_year', 'program', 'occupation', 'phone'],
  clinics: ['name', 'phone'],
  doctors: ['name', 'specialization', 'schedule'],
  medicines: ['name', 'unit', ['stock', 'number'], ['minimum_stock', 'number']],
  'medicine-stocks': [['medicine_id', 'select:medicines'], ['quantity', 'number'], ['type', 'select:stockType'], ['date', 'date']],
  'medical-queues': [['student_id', 'select:students'], 'queue_number', 'status', 'estimated_time'],
  'medical-records': [['student_id', 'select:students'], 'complaint', 'diagnosis', ['date', 'date'], 'doctor_name'],
  'medical-examinations': [['medical_record_id', 'select:medicalRecords'], ['temperature', 'number'], 'blood_pressure', ['pulse', 'number'], ['doctor_note', 'textarea']],
  prescriptions: [['medical_record_id', 'select:medicalRecords'], ['medicine_id', 'select:medicines'], 'dosage', ['quantity', 'number']],
  'sick-letters': [['student_id', 'select:students'], 'number', ['date', 'date'], ['duration_days', 'number'], ['description', 'textarea']],
  referrals: [['student_id', 'select:students'], 'hospital_name', 'phone', ['address', 'textarea']],
  'system-settings': ['key', ['value', 'textarea']],
  backups: ['name', 'status', 'file_path', ['completed_at', 'datetime-local']],
};

const statusOptions = ['active', 'inactive', 'pending', 'paid', 'unpaid', 'success', 'waiting', 'draft', 'published', 'done', 'approved', 'rejected', 'cancelled', 'in_progress'];

export default function App({ panel = 'super-admin', screen = 'dashboard', module, dashboard, shared, auth, app }) {
  const config = panels[panel] || panels['super-admin'];
  const [collapsed, setCollapsed] = useState(false);
  const [mobileNavOpen, setMobileNavOpen] = useState(false);

  return (
    <div className="min-h-screen bg-neutral-50 text-neutral-900">
      <aside className={`${collapsed ? 'w-20' : 'w-72'} fixed inset-y-0 left-0 z-20 hidden border-r border-white/10 transition-all lg:block ${config.light ? 'bg-white text-neutral-900' : 'bg-gradient-to-b from-primary-900 via-[#053947] to-[#041C35] text-white'}`}>
        <div className="flex h-full flex-col p-4">
          <div className="flex items-center gap-3 px-2 py-2">
            <Logo />
            {!collapsed && <div><div className="font-semibold">Pesantenku</div><div className={`text-xs ${config.light ? 'text-neutral-500' : 'text-white/70'}`}>{config.role}</div></div>}
          </div>
          <nav className="mt-7 flex-1 space-y-1 overflow-y-auto">
            {config.menu.map(([label, icon, href]) => <SideItem key={label} label={label} icon={icon} href={label === 'Dashboard' ? `/dashboard/${panel}` : href} active={(screen === 'dashboard' && label === 'Dashboard') || href?.endsWith(`/module/${module}`) || module === slug(label)} light={config.light} collapsed={collapsed} />)}
          </nav>
          {!collapsed && !config.light && <div className="rounded-2xl bg-white/10 p-4 text-sm"><div className="font-semibold">Server Status</div><div className="mt-3 flex justify-between text-xs"><span>Online</span><span className="text-green-300">100%</span></div><div className="mt-3 h-2 rounded-full bg-white/15"><div className="h-2 w-full rounded-full bg-primary-500" /></div></div>}
          <button onClick={() => setCollapsed(!collapsed)} className={`mt-3 rounded-xl px-3 py-2 text-xs ${config.light ? 'bg-neutral-100' : 'bg-white/10'}`}>{collapsed ? '>>' : '<<'}</button>
        </div>
      </aside>
      {mobileNavOpen && (
        <div className="fixed inset-0 z-40 lg:hidden">
          <button type="button" className="absolute inset-0 bg-neutral-900/50" onClick={() => setMobileNavOpen(false)} aria-label="Tutup menu" />
          <aside className={`relative flex h-full w-80 max-w-[86vw] flex-col border-r p-4 shadow-2xl ${config.light ? 'bg-white text-neutral-900' : 'bg-gradient-to-b from-primary-900 via-[#053947] to-[#041C35] text-white'}`}>
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <Logo />
                <div><div className="font-semibold">Pesantenku</div><div className={`text-xs ${config.light ? 'text-neutral-500' : 'text-white/70'}`}>{config.role}</div></div>
              </div>
              <button type="button" onClick={() => setMobileNavOpen(false)} className={`rounded-xl p-2 ${config.light ? 'hover:bg-neutral-100' : 'hover:bg-white/10'}`} aria-label="Tutup menu"><icons.X className="h-5 w-5" /></button>
            </div>
            <nav className="mt-7 flex-1 space-y-1 overflow-y-auto">
              {config.menu.map(([label, icon, href]) => <SideItem key={label} label={label} icon={icon} href={label === 'Dashboard' ? `/dashboard/${panel}` : href} active={(screen === 'dashboard' && label === 'Dashboard') || href?.endsWith(`/module/${module}`) || module === slug(label)} light={config.light} onClick={() => setMobileNavOpen(false)} />)}
            </nav>
          </aside>
        </div>
      )}

      <main className={`${collapsed ? 'lg:pl-20' : 'lg:pl-72'} transition-all`}>
        <Topbar panel={panel} auth={auth} shared={shared} app={app} onMenuOpen={() => setMobileNavOpen(true)} />
        <div className="p-4 lg:p-6">
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
      <section className="grid gap-5 xl:grid-cols-[1.45fr_0.8fr_0.8fr]">
        <ChartCard title={panel === 'akademik' ? 'Statistik Siswa' : panel === 'keuangan' ? 'Grafik Pemasukan & Pengeluaran' : 'Grafik Pengguna & Transaksi'}>
          <Suspense fallback={<ChartLoading />}><DashboardLineVisualization data={dashboard.lineChart || []} /></Suspense>
        </ChartCard>
        <ChartCard title={panel === 'akademik' ? 'Distribusi Kelas' : panel === 'keuangan' ? 'Pemasukan per Kategori' : 'Pengguna berdasarkan Role'}>
          <Suspense fallback={<ChartLoading />}><DashboardDonutVisualization data={dashboard.donut || []} /></Suspense>
          <LegendList items={dashboard.donut || []} />
        </ChartCard>
        <PanelCard title={panel === 'keuangan' ? 'Pengingat & Notifikasi' : 'Aktivitas Sistem Terbaru'} action="Lihat Semua" actionHref={activityHref(panel)}>
          <div className="space-y-3">
            {(dashboard.activities || []).map((item) => <ActivityRow key={item.title} item={item} />)}
          </div>
        </PanelCard>
      </section>
      <section className="grid gap-5 xl:grid-cols-3">
        <DataPreview title={preview[0].title} rows={preview[0].rows} shared={shared} href={preview[0].href} />
        <DataPreview title={preview[1].title} rows={preview[1].rows} shared={shared} href={preview[1].href} />
        <PanelCard title={panel === 'super-admin' ? 'Penggunaan Modul (Top 10)' : 'Aksi Cepat'}>
          {panel === 'super-admin' ? <ProgressList items={dashboard.moduleUsage || []} /> : <QuickActions items={dashboard.quickActions || ['Tambah Data', 'Import CSV', 'Export CSV', 'Cetak PDF', 'Pengaturan']} panel={panel} />}
        </PanelCard>
      </section>
      <section className="grid gap-5 xl:grid-cols-2">
        <DataPreview title={preview[2].title} rows={preview[2].rows} shared={shared} href={preview[2].href} />
        <PanelCard title="Notifikasi Penting">
          <div className="space-y-3">{(shared.notifications || []).map((n) => <ActivityRow key={n.id} item={{ title: n.title, description: n.body, time: 'Baru saja' }} />)}</div>
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
      <section className="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
        <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div className="flex items-center gap-4">
            <div className="grid h-14 w-14 place-items-center rounded-2xl bg-primary-100 text-lg font-semibold text-primary-700">{(profile.name || roleLabel(panel)).split(' ').map((part) => part[0]).join('').slice(0, 2)}</div>
            <div>
              <div className="text-xs text-neutral-500">Pesantenku / {roleLabel(panel)}</div>
              <h1 className="mt-1 text-2xl font-semibold">{profile.name || roleLabel(panel)}</h1>
              <p className="text-sm text-neutral-500">{profile.subtitle || 'Ringkasan aktivitas pesantren'}</p>
            </div>
          </div>
          <Link href={`/${panel}/module/profil`} className="inline-flex items-center justify-center gap-2 rounded-xl border border-neutral-200 px-4 py-2 text-sm hover:bg-neutral-50"><icons.UserCog className="h-4 w-4" /> Profil</Link>
        </div>
      </section>

      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {(dashboard.summary || []).map((item) => <StatCard key={item.label} item={{ label: item.label, value: item.value, trend: 'Data aktif', icon: roleSummaryIcon(item.label), tone: roleSummaryTone(item.label) }} />)}
      </section>

      <section className="grid gap-5 xl:grid-cols-[1.2fr_0.8fr]">
        <PanelCard title={primary.title} action="Lihat Semua" actionHref={primary.href}>
          <Table rows={primary.rows} shared={shared} compact />
        </PanelCard>
        <PanelCard title="Pengumuman">
          <div className="space-y-3">
            {rowsOf(dashboard.announcements).slice(0, 5).map((item) => <ActivityRow key={item.id || item.title} item={{ title: item.title, description: item.body || item.category || 'Pengumuman pesantren', time: item.published_at || 'Terbaru' }} />)}
            {!rowsOf(dashboard.announcements).length && <div className="rounded-xl bg-neutral-50 p-5 text-center text-sm text-neutral-500">Belum ada pengumuman.</div>}
          </div>
        </PanelCard>
      </section>

      <section className="grid gap-5 xl:grid-cols-3">
        <PanelCard title="Jadwal">
          <Table rows={dashboard.schedules} shared={shared} compact />
        </PanelCard>
        <PanelCard title={panel === 'klinik' ? 'Riwayat Kesehatan' : 'Tagihan'}>
          <Table rows={panel === 'klinik' ? dashboard.medical : dashboard.invoices} shared={shared} compact />
        </PanelCard>
        <PanelCard title="Notifikasi Penting">
          <div className="space-y-3">{rowsOf(shared.notifications).slice(0, 4).map((n) => <ActivityRow key={n.id} item={{ title: n.title, description: n.body, time: 'Baru saja' }} />)}</div>
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

  return (
    <div className="space-y-5">
      <Header title={meta.title} subtitle={meta.subtitle} />
      <div className="flex flex-wrap gap-2 rounded-2xl border border-neutral-200 bg-white p-2 shadow-sm">
        {meta.tabs.map((item) => (
          <button
            key={item.key}
            type="button"
            onClick={() => setActive(item.key)}
            className={`rounded-xl px-4 py-2 text-sm font-semibold ${item.key === tab.key ? 'bg-primary-600 text-white' : 'text-neutral-600 hover:bg-neutral-100'}`}
          >
            {item.label}
          </button>
        ))}
      </div>
      <CrudModule meta={{ ...tab.meta, title: tab.label, subtitle: tab.subtitle || tab.meta.subtitle, showHeader: false }} shared={shared} />
    </div>
  );
}

function RoleModule({ panel, module, dashboard, shared }) {
  const meta = roleModuleMeta(panel, module, dashboard, shared);

  return (
    <div className="space-y-5">
      <Header title={meta.title} subtitle={meta.subtitle} />
      {meta.profile && (
        <section className="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
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
        <QrTool rows={meta.rows} shared={shared} />
      ) : (
        <PanelCard title={meta.tableTitle || meta.title}>
          <Table rows={meta.rows} columns={meta.columns} shared={shared} />
        </PanelCard>
      )}
      {meta.secondaryRows && (
        <section className="grid gap-5 xl:grid-cols-2">
          <PanelCard title={meta.secondaryTitle || 'Data Terkait'}><Table rows={meta.secondaryRows} shared={shared} compact /></PanelCard>
          <PanelCard title="Pengumuman"><div className="space-y-3">{rowsOf(dashboard.announcements).slice(0, 4).map((item) => <ActivityRow key={item.id || item.title} item={{ title: item.title, description: item.body || item.category, time: item.published_at || 'Terbaru' }} />)}</div></PanelCard>
        </section>
      )}
    </div>
  );
}

function QrTool({ rows, shared }) {
  const [code, setCode] = useState(rowsOf(rows)[0]?.kode || '');
  const [scanCode, setScanCode] = useState('');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const QrIcon = icons.QrCode || icons.Box;
  const ScanIcon = icons.ScanLine || icons.Search;

  const generate = async () => {
    setLoading(true);
    setMessage('');
    setError('');
    try {
      const json = await apiJson('/web-actions/qr/generate', { method: 'GET' });
      setCode(json.code);
      setScanCode(json.code);
      setMessage('QR berhasil dibuat dan dicatat di audit log.');
      router.reload({ only: ['shared'], preserveScroll: true });
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const scan = async (event) => {
    event.preventDefault();
    setLoading(true);
    setMessage('');
    setError('');
    try {
      await apiJson('/web-actions/qr/scan', {
        method: 'POST',
        body: JSON.stringify({ code: scanCode || code }),
      });
      setMessage('QR berhasil diproses dan dicatat di audit log.');
      router.reload({ only: ['shared'], preserveScroll: true });
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <section className="grid gap-5 xl:grid-cols-[0.8fr_1.2fr]">
      <PanelCard title="QR Aktif">
        {(message || error) && <div className="mb-4"><Alert tone={error ? 'error' : 'success'}>{error || message}</Alert></div>}
        <div className="grid aspect-square max-w-xs place-items-center rounded-2xl border border-neutral-200 bg-neutral-50 p-6">
          <div className="grid h-full w-full grid-cols-5 grid-rows-5 gap-2">
            {Array.from({ length: 25 }).map((_, index) => <span key={index} className={`rounded ${((code.charCodeAt(index % Math.max(code.length, 1)) || index) + index) % 3 ? 'bg-neutral-900' : 'bg-white'}`} />)}
          </div>
        </div>
        <div className="mt-4 break-all rounded-xl bg-neutral-50 p-3 font-mono text-xs">{code || 'Belum ada kode QR.'}</div>
        <button type="button" onClick={generate} disabled={loading} className="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">
          {loading ? <icons.Loader2 className="h-4 w-4 animate-spin" /> : <QrIcon className="h-4 w-4" />}
          Generate QR
        </button>
      </PanelCard>
      <PanelCard title="Scan Manual">
        <form onSubmit={scan} className="space-y-3">
          <label className="block text-sm">
            <span className="mb-1 block font-medium">Kode QR</span>
            <input value={scanCode} onChange={(event) => setScanCode(event.target.value)} className="w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-primary-600" placeholder="Tempel kode QR untuk diproses" />
          </label>
          <button type="submit" disabled={loading || !(scanCode || code)} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 px-4 py-2 text-sm font-semibold disabled:opacity-60">
            {loading ? <icons.Loader2 className="h-4 w-4 animate-spin" /> : <ScanIcon className="h-4 w-4" />}
            Proses Scan
          </button>
        </form>
        <div className="mt-5">
          <Table rows={rows} shared={shared} compact />
        </div>
      </PanelCard>
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
  const canCreate = meta.canCreate !== false && Boolean(meta.resource);
  const canEdit = meta.canEdit !== false && Boolean(meta.resource);
  const canDelete = meta.canDelete !== false && Boolean(meta.resource);
  const canExport = meta.canExport !== false && Boolean(meta.resource);
  const canImport = canCreate;
  const canBulk = (canEdit || canDelete) && Boolean(meta.resource);
  const canView = meta.canView !== false;
  const canEditPermissions = ['super-admin', 'owner'].includes(meta.panel);
  const canMonitorQueue = ['super-admin', 'owner'].includes(meta.panel);
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
    setLoading(true);
    setError('');
    const params = buildParams(query);
    try {
      const json = await apiJson(`/web-api/${meta.resource}?${params.toString()}`);
      setRows(rowsOf(json.data));
      setSelectedIds([]);
      setPagination({
        current_page: json.current_page || 1,
        last_page: json.last_page || 1,
        total: json.total ?? rowsOf(json.data).length,
        per_page: json.per_page || perPage,
      });
      const report = await apiJson(`/web-api/${meta.resource}/analytics?${params.toString()}`);
      setAnalytics(report);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const filteredRows = rows;

  const submit = async (event) => {
    event.preventDefault();
    if (!meta.resource) return;
    setSaving(true);
    setError('');
    setMessage('');
    setFormErrors({});
    try {
      const payload = normalizePayload(formData, meta.fields);
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
      await apiJson(`/web-api/${meta.resource}/${row.id}`, { method: 'DELETE' });
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
      setMessage('Backup berhasil dijalankan dan file snapshot dibuat.');
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
      { key: 'download-backup', label: 'Download Backup', icon: 'Download', onClick: downloadBackup },
      { key: 'restore-backup', label: 'Restore Backup', icon: 'RefreshCcw', tone: 'danger', onClick: (row) => setRestoreDraft({ ...row, confirmation: '' }) },
    ] : []),
  ];

  return (
    <div className="space-y-5">
      {meta.showHeader !== false && <Header title={meta.title} subtitle={meta.subtitle} />}
      {(message || error) && <Alert tone={error ? 'error' : 'success'}>{error || message}</Alert>}
      <PanelCard>
        <div className="mb-5 flex flex-col gap-3 2xl:flex-row 2xl:items-start 2xl:justify-between">
          <div className="grid flex-1 gap-3 md:grid-cols-[minmax(220px,1fr)_150px_150px_150px_150px]">
            <div className="relative max-w-lg flex-1"><icons.Search className="absolute left-3 top-3 h-4 w-4 text-neutral-400" /><input value={search} onChange={(event) => setSearch(event.target.value)} className="w-full rounded-xl border border-neutral-200 py-2.5 pl-10 pr-3 text-sm outline-none focus:border-primary-600" placeholder={`Cari ${meta.title.toLowerCase()}...`} /></div>
            <select value={status} onChange={(event) => setStatus(event.target.value)} className="rounded-xl border border-neutral-200 px-3 text-sm"><option>Semua Status</option>{statusOptions.map((item) => <option key={item}>{item}</option>)}</select>
            {meta.enableFilters !== false && <input type="date" value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} className="rounded-xl border border-neutral-200 px-3 text-sm" title="Tanggal mulai" />}
            {meta.enableFilters !== false && <input type="date" value={dateTo} onChange={(event) => setDateTo(event.target.value)} className="rounded-xl border border-neutral-200 px-3 text-sm" title="Tanggal akhir" />}
            {meta.enableFilters !== false && (
              <select value={classFilter} onChange={(event) => setClassFilter(event.target.value)} className="rounded-xl border border-neutral-200 px-3 text-sm">
                <option value="">Semua Kelas</option>
                {rowsOf(shared.classes).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
              </select>
            )}
            {meta.enableFilters !== false && (
              <select value={studentFilter} onChange={(event) => setStudentFilter(event.target.value)} className="rounded-xl border border-neutral-200 px-3 text-sm md:col-start-2">
                <option value="">Semua Santri</option>
                {rowsOf(shared.students).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
              </select>
            )}
          </div>
          <div className="flex flex-wrap gap-2">
            {meta.resource === 'backups' && <button onClick={runBackup} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 px-4 py-2 text-sm"><icons.Database className="h-4 w-4" /> Jalankan Backup</button>}
            {meta.resource === 'backups' && meta.panel === 'super-admin' && <button onClick={() => setRestoreApprovalOpen(true)} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 px-4 py-2 text-sm"><icons.Shield className="h-4 w-4" /> Persetujuan Restore</button>}
            {canExport && <button onClick={() => exportResource(meta.resource, meta.title, 'csv', buildParams())} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 px-4 py-2 text-sm"><icons.Download className="h-4 w-4" /> CSV</button>}
            {canExport && <button onClick={() => exportResource(meta.resource, meta.title, 'excel', buildParams())} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 px-4 py-2 text-sm"><icons.FileText className="h-4 w-4" /> Excel</button>}
            {canExport && <button onClick={() => exportResource(meta.resource, meta.title, 'pdf', buildParams())} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 px-4 py-2 text-sm"><icons.FileText className="h-4 w-4" /> PDF</button>}
            {canExport && <button onClick={() => queueExport('excel')} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 px-4 py-2 text-sm"><icons.Database className="h-4 w-4" /> Export Job</button>}
            <button onClick={() => { setDownloadManagerOpen(true); loadJobs(); }} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 px-4 py-2 text-sm"><icons.Download className="h-4 w-4" /> Download Manager</button>
            {canImport && <button onClick={() => setImportOpen(true)} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 px-4 py-2 text-sm"><ImportIcon className="h-4 w-4" /> Import Excel</button>}
            {canBulk && selectedIds.length > 0 && <button onClick={() => setBulkOpen(true)} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 px-4 py-2 text-sm"><BulkIcon className="h-4 w-4" /> Aksi Massal ({selectedIds.length})</button>}
            {canEditPermissions && <button onClick={() => setPermissionOpen(true)} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 px-4 py-2 text-sm"><icons.Shield className="h-4 w-4" /> Permission</button>}
            {canEditPermissions && <button onClick={() => setCustomRoleOpen(true)} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 px-4 py-2 text-sm"><icons.UserCog className="h-4 w-4" /> Custom Role</button>}
            {canMonitorQueue && <button onClick={() => setQueueMonitorOpen(true)} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 px-4 py-2 text-sm"><icons.Activity className="h-4 w-4" /> Queue Monitor</button>}
            {canEditPdfTemplates && <button onClick={() => setPdfTemplateOpen(true)} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 px-4 py-2 text-sm"><icons.FileText className="h-4 w-4" /> Template PDF</button>}
            <button onClick={() => loadRows()} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 px-4 py-2 text-sm"><icons.RefreshCcw className="h-4 w-4" /> Refresh</button>
            {canCreate && <button onClick={createRow} className="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white"><icons.Plus className="h-4 w-4" /> {meta.createLabel || 'Tambah Data'}</button>}
          </div>
        </div>
        <Table rows={filteredRows} columns={meta.fields.map((field) => field.name)} meta={meta} shared={shared} loading={loading} selectable={canBulk} selectedIds={selectedIds} onToggleRow={toggleSelected} onToggleAll={setSelectedForPage} onView={canView ? viewRow : null} onEdit={canEdit ? editRow : null} onDelete={canDelete ? deleteRow : null} customActions={resourceActions} />
        <PaginationControls pagination={pagination} page={page} setPage={setPage} perPage={perPage} setPerPage={setPerPage} />
      </PanelCard>
      {jobs.length > 0 && <JobStatusStrip jobs={jobs} onRefresh={loadJobs} />}
      {analytics && <Suspense fallback={<ChartLoading />}><AnalyticsPanel analytics={analytics} title={meta.title} /></Suspense>}
      {meta.showInsights !== false && (
        <section className="grid gap-5 lg:grid-cols-2">
          <PanelCard title="Status Modul"><ProgressList items={[{ name: 'Data Aktif', value: 85 }, { name: 'Perlu Review', value: 18 }, { name: 'Arsip', value: 32 }]} /></PanelCard>
          <PanelCard title="Audit Terbaru"><ActivityRow item={{ title: `${meta.title} diperbarui`, description: 'Perubahan dicatat di audit log', time: '5 menit lalu' }} /></PanelCard>
        </section>
      )}
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
  const [message, setMessage] = useState('');
  const [detail, setDetail] = useState(null);
  const rows = useMemo(() => {
    const term = search.toLowerCase();
    if (!term) return rowsOf(meta.rows);
    return rowsOf(meta.rows).filter((row) => Object.values(row).some((value) => String(value ?? '').toLowerCase().includes(term)));
  }, [meta.rows, search]);

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
        <div className="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div className="flex flex-1 gap-3">
            <div className="relative max-w-xl flex-1"><icons.Search className="absolute left-3 top-3 h-4 w-4 text-neutral-400" /><input value={search} onChange={(event) => setSearch(event.target.value)} className="w-full rounded-xl border border-neutral-200 py-2.5 pl-10 pr-3 text-sm outline-none focus:border-primary-600" placeholder={meta.searchPlaceholder} /></div>
            <select className="rounded-xl border border-neutral-200 px-3 text-sm"><option>Semua Status</option><option>Berhasil</option><option>Peringatan</option><option>Diblokir</option><option>Error</option></select>
          </div>
          <div className="flex gap-2">
            <button onClick={exportRows} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 px-4 py-2 text-sm"><icons.Download className="h-4 w-4" /> Export CSV</button>
            <button onClick={() => setMessage('Data berhasil direfresh.')} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 px-4 py-2 text-sm"><icons.RefreshCcw className="h-4 w-4" /> Refresh</button>
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
          <ProgressList items={[
            { name: 'Web Server', value: 100 },
            { name: 'Database', value: 98 },
            { name: 'Queue Worker', value: 96 },
            { name: 'Storage', value: 82 },
          ]} />
        </PanelCard>
      </section>
    </div>
  );
}

function Topbar({ panel, auth, shared, app, onMenuOpen }) {
  const memberships = Array.isArray(auth?.memberships) ? auth.memberships : Object.values(auth?.memberships || {});
  const activeTenantId = auth?.active_pesantren?.id || memberships[0]?.id || '';
  const [searchTerm, setSearchTerm] = useState('');
  const [searchOpen, setSearchOpen] = useState(false);
  const [notificationsOpen, setNotificationsOpen] = useState(false);
  const [notificationState, setNotificationState] = useState({ data: rowsOf(shared?.notifications), unread: rowsOf(shared?.notifications).length });
  const [accountOpen, setAccountOpen] = useState(false);
  const [academicYear, setAcademicYear] = useState('2024/2025');
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

  const markNotificationsRead = async () => {
    if (!notifications.length) return;
    await apiJson('/web-actions/notifications/read', {
      method: 'POST',
      body: JSON.stringify({ ids: notifications.map((item) => item.id).filter(Boolean) }),
    });
    setNotificationState((current) => ({
      data: rowsOf(current.data).map((item) => ({ ...item, read_at: item.read_at || new Date().toISOString() })),
      unread: 0,
    }));
    setNotificationsOpen(false);
    router.reload({ only: ['shared'], preserveScroll: true });
  };

  return (
    <header ref={topbarRef} className="sticky top-0 z-10 border-b border-neutral-200 bg-white/90 px-4 py-3 backdrop-blur lg:px-6">
      <div className="flex items-center gap-4">
        <button type="button" onClick={onMenuOpen} className="rounded-xl border border-neutral-200 p-2 hover:bg-neutral-50 lg:hidden" aria-label="Buka menu"><icons.Menu className="h-5 w-5" /></button>
        <form onSubmit={submitSearch} className="relative flex-1">
          <icons.Search className="absolute left-3 top-3 h-4 w-4 text-neutral-400" />
          <input
            value={searchTerm}
            onFocus={() => setSearchOpen(true)}
            onChange={(event) => {
              setSearchTerm(event.target.value);
              setSearchOpen(true);
            }}
            className="w-full max-w-xl rounded-xl border border-neutral-200 py-2.5 pl-10 pr-10 text-sm outline-none focus:border-primary-600"
            placeholder="Cari menu, modul, laporan..."
          />
          {searchTerm && <button type="button" onClick={() => setSearchTerm('')} className="absolute right-3 top-2.5 rounded-lg p-1 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700" aria-label="Bersihkan pencarian"><icons.X className="h-4 w-4" /></button>}
          {searchOpen && searchTerm && (
            <div className="absolute left-0 top-12 z-30 w-full max-w-xl overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-xl">
              {searchResults.length > 0 ? searchResults.map((item) => {
                const Icon = icons[item.icon] || icons.Search;
                return (
                  <button key={item.href} type="button" onClick={() => openMenu(item.href)} className="flex w-full items-center gap-3 px-4 py-3 text-left text-sm hover:bg-neutral-50">
                    <span className="grid h-8 w-8 place-items-center rounded-lg bg-primary-50 text-primary-700"><Icon className="h-4 w-4" /></span>
                    <span className="font-medium">{item.label}</span>
                  </button>
                );
              }) : <div className="px-4 py-3 text-sm text-neutral-500">Menu tidak ditemukan.</div>}
            </div>
          )}
        </form>
        {memberships.length > 0 && (
          <select
            value={activeTenantId}
            onChange={(event) => router.post('/tenant/switch', { pesantren_id: event.target.value }, { preserveScroll: true })}
            className="hidden max-w-64 rounded-xl border border-neutral-200 px-3 py-2 text-sm xl:block"
          >
            {memberships.map((item) => <option key={`${item.id}-${item.role || item.role_label}`} value={item.id}>{item.name || item.pesantren} {item.role_label ? `- ${item.role_label}` : ''}</option>)}
          </select>
        )}
        <select value={academicYear} onChange={(event) => setAcademicYear(event.target.value)} className="hidden rounded-xl border border-neutral-200 px-3 py-2 text-sm md:block">
          {['2024/2025', '2025/2026', '2026/2027'].map((year) => <option key={year} value={year}>Tahun Ajaran {year}</option>)}
        </select>
        <div className="relative">
          <button type="button" onClick={() => { setNotificationsOpen((open) => !open); setAccountOpen(false); }} className="relative rounded-xl border border-neutral-200 p-2 hover:bg-neutral-50" aria-label="Buka notifikasi">
            <icons.Bell className="h-5 w-5" />
            {unreadCount > 0 && <span className="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-red-600 px-1 text-[10px] font-semibold text-white">{unreadCount}</span>}
          </button>
          {notificationsOpen && (
            <div className="absolute right-0 top-12 z-30 w-80 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-xl">
              <div className="flex items-center justify-between border-b border-neutral-100 px-4 py-3 text-sm">
                <span className="font-semibold">Notifikasi</span>
                {notifications.length > 0 && <button type="button" onClick={markNotificationsRead} className="text-xs font-semibold text-primary-700">Tandai terbaca</button>}
              </div>
              <div className="max-h-80 overflow-y-auto">
                {notifications.length > 0 ? notifications.map((item) => (
                  <div key={item.id || item.title} className="border-b border-neutral-100 px-4 py-3 text-sm last:border-0">
                    <div className="font-medium">{item.title}</div>
                    <div className="mt-1 text-xs text-neutral-500">{item.body || item.description || 'Notifikasi sistem'}</div>
                  </div>
                )) : <div className="px-4 py-6 text-center text-sm text-neutral-500">Belum ada notifikasi.</div>}
              </div>
            </div>
          )}
        </div>
        <div className="relative">
          <button type="button" onClick={() => { setAccountOpen((open) => !open); setNotificationsOpen(false); }} className="flex items-center gap-3 rounded-xl px-2 py-1.5 text-left hover:bg-neutral-50" aria-label="Buka menu akun">
            <div className="grid h-9 w-9 place-items-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700">{initials || 'U'}</div>
            <div className="hidden text-sm md:block"><div className="font-semibold">{userName}</div><div className="text-xs text-neutral-500">{roleLabel(panel)}</div></div>
          </button>
          {accountOpen && (
            <div className="absolute right-0 top-12 z-30 w-80 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-xl">
              <div className="border-b border-neutral-100 px-4 py-4">
                <div className="font-semibold">{userName}</div>
                {userEmail && <div className="mt-1 text-xs text-neutral-500">{userEmail}</div>}
                <div className="mt-2 text-xs text-neutral-500">{roles}</div>
              </div>
              {auth?.active_pesantren && (
                <div className="border-b border-neutral-100 px-4 py-3 text-sm">
                  <div className="text-xs text-neutral-500">Pesantren Aktif</div>
                  <div className="mt-1 font-medium">{auth.active_pesantren.name}</div>
                </div>
              )}
              <Link href={settingsHref} className="flex items-center gap-3 px-4 py-3 text-sm hover:bg-neutral-50" onClick={() => setAccountOpen(false)}><icons.Settings className="h-4 w-4" /> Pengaturan</Link>
              <button type="button" onClick={logout} className="flex w-full items-center gap-3 px-4 py-3 text-left text-sm text-red-600 hover:bg-red-50"><icons.LogOut className="h-4 w-4" /> Logout</button>
            </div>
          )}
        </div>
      </div>
    </header>
  );
}

function Header({ title, subtitle }) {
  return <div><div className="text-xs text-neutral-500">Pesantenku / Dashboard</div><h1 className="mt-1 text-2xl font-semibold">{title}</h1><p className="text-sm text-neutral-500">{subtitle}</p></div>;
}

function StatCard({ item }) {
  const Icon = icons[item.icon] || icons.Activity;
  return <div className="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm"><div className="flex items-center justify-between"><div><p className="text-xs text-neutral-500">{item.label}</p><p className="mt-2 text-2xl font-semibold">{item.value}</p></div><div className={`grid h-14 w-14 place-items-center rounded-full ${tone[item.tone] || tone.success}`}><Icon className="h-7 w-7" /></div></div><p className="mt-3 text-xs text-primary-700">{item.trend}</p></div>;
}

function ChartCard({ title, children }) {
  return <PanelCard title={title}>{children}</PanelCard>;
}

function ChartLoading() {
  return <div className="grid h-56 place-items-center rounded-xl bg-neutral-50 text-sm text-neutral-500">Memuat visualisasi...</div>;
}

function PanelCard({ title, action, actionHref, onAction, children }) {
  return (
    <section className="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
      {title && (
        <div className="mb-4 flex items-center justify-between">
          <h2 className="font-semibold">{title}</h2>
          {actionHref ? <Link href={actionHref} className="text-xs font-semibold text-primary-700">{action}</Link> : action ? <button type="button" onClick={onAction} className="text-xs font-semibold text-primary-700">{action}</button> : null}
        </div>
      )}
      {children}
    </section>
  );
}

function SideItem({ label, icon, href, active, light, collapsed, onClick }) {
  const Icon = icons[icon] || icons.CircleCheck;
  return <Link href={href} onClick={onClick} className={`flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition ${active ? light ? 'bg-primary-50 text-primary-700' : 'bg-blue-600/50 text-white' : light ? 'text-neutral-600 hover:bg-neutral-100' : 'text-white/80 hover:bg-white/10'}`}><Icon className="h-4 w-4" />{!collapsed && <span>{label}</span>}</Link>;
}

function DataPreview({ title, rows, shared, href }) {
  return <PanelCard title={title} action="Lihat Semua" actionHref={href}><Table rows={rows} shared={shared} compact /></PanelCard>;
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
      { title: 'Pesantren Terbaru', rows: shared.pesantrens, href: '/super-admin/module/pesantrens' },
      { title: 'Owner & User Platform', rows: shared.memberships, href: '/super-admin/module/owner' },
      { title: 'Subscription Aktif', rows: shared.subscriptions, href: '/super-admin/module/subscriptions' },
    ];
  }

  if (panel === 'owner') {
    return [
      { title: 'User & Role Tenant', rows: shared.memberships, href: '/owner/module/user-dan-role' },
      { title: 'Modul Tenant', rows: shared.modules, href: '/owner/module/modul-aktif' },
      { title: 'Paket & Subscription', rows: shared.subscriptions, href: '/owner/module/paket-dan-subscription' },
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
  const columns = useMemo(() => preferredColumns || Object.keys(normalized[0] || {}).filter(isVisibleTableColumn).slice(0, compact ? 5 : 7), [rows, preferredColumns, compact]);
  const hasActions = onView || onEdit || onDelete || customActions.length > 0;
  const visibleRows = normalized.slice(0, compact ? 5 : 20);
  const selectableIds = visibleRows.map((row) => row.id).filter(Boolean);
  const allSelected = selectableIds.length > 0 && selectableIds.every((id) => selectedIds.includes(id));
  if (loading) return <div className="rounded-xl bg-neutral-50 p-8 text-center text-sm text-neutral-500">Memuat data...</div>;
  if (!normalized.length) return <div className="rounded-xl bg-neutral-50 p-8 text-center text-sm text-neutral-500">Belum ada data.</div>;
  return (
    <div className="overflow-x-auto">
      <table className="w-full text-left text-sm">
        <thead><tr className="border-b border-neutral-200 text-xs text-neutral-500">{selectable && <th className="w-10 px-3 py-3"><input type="checkbox" checked={allSelected} onChange={(event) => onToggleAll?.(selectableIds, event.target.checked)} className="h-4 w-4 accent-primary-600" aria-label="Pilih semua baris halaman ini" /></th>}{columns.map((c) => <th key={c} className="px-3 py-3 font-medium">{columnTitle(c)}</th>)}{hasActions && <th className="px-3 py-3 font-medium">Aksi</th>}</tr></thead>
        <tbody>{visibleRows.map((row, i) => {
          const visibleCustomActions = customActions.filter((action) => !action.show || action.show(row));
          return (
            <tr key={row.id || i} className="border-b border-neutral-100">
              {selectable && <td className="px-3 py-3"><input type="checkbox" checked={selectedIds.includes(row.id)} onChange={() => onToggleRow?.(row.id)} className="h-4 w-4 accent-primary-600" aria-label={`Pilih baris ${row.id}`} /></td>}
              <>{columns.map((c) => <td key={c} className="px-3 py-3">{renderCell(c, row[c], row, shared, fieldMap[c])}</td>)}</>
              {hasActions && <td className="px-3 py-3"><div className="flex flex-wrap gap-2">{visibleCustomActions.map((action) => {
                const Icon = icons[action.icon] || icons.CircleCheck;
                return <button key={action.key} type="button" title={action.label} onClick={() => action.onClick(row)} className={`rounded-lg border p-1.5 ${action.tone === 'danger' ? 'border-red-200 text-red-600 hover:border-red-500' : 'border-neutral-200 hover:border-primary-600 hover:text-primary-700'}`}><Icon className="h-3.5 w-3.5" /></button>;
              })}{onView && <button type="button" title="Lihat" onClick={() => onView(row)} className="rounded-lg border border-neutral-200 p-1.5 hover:border-primary-600 hover:text-primary-700"><icons.Eye className="h-3.5 w-3.5" /></button>}{onEdit && <button type="button" title="Edit" onClick={() => onEdit(row)} className="rounded-lg border border-neutral-200 p-1.5 hover:border-primary-600 hover:text-primary-700"><icons.Edit2 className="h-3.5 w-3.5" /></button>}{onDelete && <button type="button" title="Hapus" onClick={() => onDelete(row)} className="rounded-lg border border-neutral-200 p-1.5 hover:border-red-500 hover:text-red-600"><icons.Trash2 className="h-3.5 w-3.5" /></button>}</div></td>}
            </tr>
          );
        })}</tbody>
      </table>
    </div>
  );
}

const hiddenTableColumns = new Set(['id', 'created_at', 'updated_at', 'deleted_at', 'metadata', 'profile', 'password']);

const relationSelectTypes = {
  academic_year_id: 'select:academicYears',
  class_id: 'select:classes',
  clinic_id: 'select:clinics',
  doctor_id: 'select:doctors',
  expense_category_id: 'select:expenseCategories',
  guardian_id: 'select:guardians',
  invoice_id: 'select:invoices',
  medical_record_id: 'select:medicalRecords',
  medicine_id: 'select:medicines',
  payment_method_id: 'select:paymentMethods',
  pesantren_id: 'select:pesantrens',
  plan_id: 'select:plans',
  program_id: 'select:programs',
  role_id: 'select:roles',
  room_id: 'select:rooms',
  student_id: 'select:students',
  subject_id: 'select:subjects',
  teacher_id: 'select:teachers',
  user_id: 'select:users',
};

const relationColumnLabels = {
  academic_year_id: 'Tahun Ajaran',
  class_id: 'Kelas',
  clinic_id: 'Klinik',
  doctor_id: 'Dokter',
  expense_category_id: 'Kategori Pengeluaran',
  guardian_id: 'Wali',
  invoice_id: 'Tagihan',
  medical_record_id: 'Rekam Medis',
  medicine_id: 'Obat',
  payment_method_id: 'Metode Pembayaran',
  pesantren_id: 'Pesantren',
  plan_id: 'Paket',
  program_id: 'Program',
  role_id: 'Role',
  room_id: 'Kamar',
  student_id: 'Santri',
  subject_id: 'Mata Pelajaran',
  teacher_id: 'Guru',
  user_id: 'Pengguna',
};

function isVisibleTableColumn(key) {
  return !hiddenTableColumns.has(key);
}

function columnTitle(key) {
  if (relationColumnLabels[key]) return relationColumnLabels[key];
  return titleFromSlug(key.endsWith('_id') ? key.slice(0, -3) : key);
}

function renderCell(key, value, row = {}, shared = {}, field) {
  const displayValue = displayCellValue(key, value, row, shared, field);
  if (key === 'status' || key === 'level') return <Badge label={String(displayValue || 'Aktif')} />;
  if (typeof displayValue === 'boolean') return displayValue ? 'Ya' : 'Tidak';
  if (typeof displayValue === 'number' && (key.includes('amount') || key.includes('balance') || key.includes('price') || key.includes('nominal'))) return rupiah(displayValue);
  return String(displayValue ?? '-');
}

function displayCellValue(key, value, row, shared, field) {
  if (value === null || value === undefined || value === '') return value;
  const resolved = relationLabelFor(key, value, row, shared, field);
  return resolved ?? value;
}

function relationLabelFor(key, value, row, shared, field) {
  const relationField = field?.type?.startsWith('select') ? field : inferredFieldForColumn(key);
  if (!relationField) return null;

  const option = optionsFor(relationField, shared).find((item) => String(item.value) === String(value));
  if (option) return option.label;

  return relationFallbackLabel(key, value, row);
}

function inferredFieldForColumn(key) {
  const type = relationSelectTypes[key];
  return type ? { name: key, type } : null;
}

function relationFallbackLabel(key, value, row) {
  const candidates = [
    key.replace(/_id$/, '_name'),
    key.replace(/_id$/, '_title'),
    key.replace(/_id$/, '_number'),
    key.replace(/_id$/, ''),
  ];
  const label = candidates.map((candidate) => row[candidate]).find((candidateValue) => candidateValue !== undefined && candidateValue !== null && candidateValue !== '');
  return label ?? `#${value}`;
}

function Badge({ label }) {
  const lower = label.toLowerCase();
  const cls = lower.includes('paid') || lower.includes('active') || lower.includes('approved') || lower.includes('aktif') || lower.includes('success') || lower.includes('berhasil') || lower.includes('selesai') || lower.includes('done') || lower.includes('info') ? 'bg-green-50 text-green-700' : lower.includes('pending') || lower.includes('unpaid') || lower.includes('waiting') || lower.includes('in_progress') || lower.includes('warning') || lower.includes('peringatan') || lower.includes('dipantau') ? 'bg-amber-50 text-amber-700' : lower.includes('error') || lower.includes('rejected') || lower.includes('cancelled') || lower.includes('ditolak') || lower.includes('diblokir') ? 'bg-red-50 text-red-700' : 'bg-neutral-100 text-neutral-600';
  return <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${cls}`}>{label.replace('paid', 'Lunas').replace('unpaid', 'Belum Lunas').replace('active', 'Aktif').replace('approved', 'Disetujui').replace('rejected', 'Ditolak').replace('waiting', 'Menunggu').replace('in_progress', 'Diproses').replace('cancelled', 'Dibatalkan')}</span>;
}

function ActivityRow({ item }) {
  return <div className="flex gap-3 rounded-xl border border-neutral-100 p-3"><div className="grid h-9 w-9 place-items-center rounded-xl bg-primary-50 text-primary-700"><icons.CircleCheck className="h-4 w-4" /></div><div className="min-w-0 flex-1"><div className="text-sm font-semibold">{item.title}</div><div className="truncate text-xs text-neutral-500">{item.description}</div></div><div className="text-right text-xs text-neutral-400">{item.time}</div></div>;
}

function LegendList({ items }) {
  return <div className="space-y-2">{items.map((item, i) => <div key={item.name} className="flex items-center justify-between text-sm"><span className="flex items-center gap-2"><span className="h-3 w-3 rounded-full" style={{ background: colors[i % colors.length] }} />{item.name}</span><span className="font-medium">{typeof item.value === 'number' && item.value > 100000 ? rupiah(item.value) : item.value}</span></div>)}</div>;
}

function ProgressList({ items }) {
  return <div className="space-y-4">{items.map((item, i) => <div key={item.name}><div className="mb-1 flex justify-between text-sm"><span>{item.name}</span><span>{item.value}%</span></div><div className="h-2 rounded-full bg-neutral-100"><div className="h-2 rounded-full" style={{ width: `${item.value}%`, background: colors[i % colors.length] }} /></div></div>)}</div>;
}

function QuickActions({ items, panel }) {
  return (
    <div className="grid grid-cols-2 gap-3">
      {items.slice(0, 6).map((item) => {
        const href = quickActionHref(panel, item);
        return (
          <button key={item} type="button" onClick={() => href && router.visit(href)} className="rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm font-medium hover:border-primary-600 hover:text-primary-700">
            {item}
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
    akademik: '/akademik/module/kalender-akademik',
    tu: '/tu/module/pengumuman',
  }[panel] || `/dashboard/${panel}`;
}

function Alert({ tone: alertTone, children }) {
  const cls = alertTone === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700';
  return <div className={`rounded-xl border px-4 py-3 text-sm ${cls}`}>{children}</div>;
}

function PaginationControls({ pagination, page, setPage, perPage, setPerPage }) {
  const total = pagination.total ?? 0;
  const lastPage = Math.max(1, pagination.last_page || 1);
  const currentPage = Math.min(page, lastPage);

  return (
    <div className="mt-4 flex flex-col gap-3 border-t border-neutral-100 pt-4 text-sm text-neutral-600 md:flex-row md:items-center md:justify-between">
      <div>{total} data / halaman {currentPage} dari {lastPage}</div>
      <div className="flex flex-wrap items-center gap-2">
        <select value={perPage} onChange={(event) => setPerPage(Number(event.target.value))} className="rounded-xl border border-neutral-200 px-3 py-2 text-sm">
          {[10, 20, 50, 100].map((value) => <option key={value} value={value}>{value} / halaman</option>)}
        </select>
        <button type="button" disabled={currentPage <= 1} onClick={() => setPage((value) => Math.max(1, value - 1))} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 px-3 py-2 disabled:opacity-50"><icons.ChevronLeft className="h-4 w-4" /> Sebelumnya</button>
        <button type="button" disabled={currentPage >= lastPage} onClick={() => setPage((value) => Math.min(lastPage, value + 1))} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 px-3 py-2 disabled:opacity-50">Berikutnya <icons.ChevronRight className="h-4 w-4" /></button>
      </div>
    </div>
  );
}

function ImportModal({ title, resource, loading, onSubmit, onClose }) {
  const [file, setFile] = useState(null);
  const [preview, setPreview] = useState(null);
  const [mapping, setMapping] = useState({});
  const [queue, setQueue] = useState(false);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [error, setError] = useState('');
  const UploadIcon = icons.Upload || icons.Download;

  const previewFile = async () => {
    if (!file || !resource) return;
    setPreviewLoading(true);
    setError('');
    try {
      const json = await apiUpload(`/web-api/${resource}/import/preview`, file);
      setPreview(json);
      setMapping(json.mapping || {});
    } catch (err) {
      setError(err.message);
    } finally {
      setPreviewLoading(false);
    }
  };

  const submit = (event) => {
    event.preventDefault();
    onSubmit({ file, mapping, queue });
  };

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-neutral-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
      <form onSubmit={submit} className="flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-neutral-200 px-5 py-4">
          <div>
            <h2 className="font-semibold">Import {title}</h2>
            <p className="text-xs text-neutral-500">Preview header, mapping kolom, dan validasi sebelum data disimpan.</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 p-2 hover:bg-neutral-50" aria-label="Tutup modal"><icons.X className="h-4 w-4" /></button>
        </div>
        <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
          {error && <Alert tone="error">{error}</Alert>}
          <div className="grid gap-3 md:grid-cols-[1fr_auto]">
            <label className="block text-sm">
              <span className="mb-1 block font-medium">File Import</span>
              <input type="file" accept=".csv,.txt,.xls,.xlsx" onChange={(event) => { setFile(event.target.files?.[0] || null); setPreview(null); setMapping({}); }} className="w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm" required />
            </label>
            <button type="button" onClick={previewFile} disabled={!file || previewLoading} className="self-end rounded-xl border border-neutral-200 px-4 py-2.5 text-sm font-semibold disabled:opacity-60">
              {previewLoading ? 'Memuat...' : 'Preview'}
            </button>
          </div>
          {preview && (
            <div className="grid gap-4 lg:grid-cols-[1fr_1.2fr]">
              <div className="rounded-xl border border-neutral-200">
                <div className="border-b border-neutral-100 px-4 py-3 text-sm font-semibold">Mapping Kolom</div>
                <div className="max-h-80 space-y-3 overflow-y-auto p-4">
                  {rowsOf(preview.headers).map((header) => (
                    <label key={header} className="grid gap-1 text-sm">
                      <span className="font-medium">{header}</span>
                      <select value={mapping[header] || ''} onChange={(event) => setMapping((current) => ({ ...current, [header]: event.target.value }))} className="rounded-xl border border-neutral-200 px-3 py-2 text-sm">
                        <option value="">Abaikan</option>
                        {rowsOf(preview.columns).map((column) => <option key={column} value={column}>{columnTitle(column)}</option>)}
                      </select>
                    </label>
                  ))}
                </div>
              </div>
              <div className="space-y-3">
                <div className="grid gap-3 sm:grid-cols-3">
                  <StatPill label="Valid" value={preview.summary?.valid ?? 0} />
                  <StatPill label="Invalid" value={preview.summary?.invalid ?? 0} />
                  <StatPill label="Kosong" value={preview.summary?.blank ?? 0} />
                </div>
                <div className="rounded-xl border border-neutral-200">
                  <div className="border-b border-neutral-100 px-4 py-3 text-sm font-semibold">Contoh Data</div>
                  <div className="max-h-52 overflow-auto p-4 text-xs">
                    <pre className="whitespace-pre-wrap">{JSON.stringify(rowsOf(preview.preview), null, 2)}</pre>
                  </div>
                </div>
                {rowsOf(preview.errors).length > 0 && (
                  <div className="rounded-xl border border-red-100 bg-red-50 p-4 text-xs text-red-700">
                    {rowsOf(preview.errors).slice(0, 3).map((item) => <div key={item.row}>Baris {item.row}: {rowsOf(item.messages).join(', ')}</div>)}
                  </div>
                )}
              </div>
            </div>
          )}
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={queue} onChange={(event) => setQueue(event.target.checked)} className="h-4 w-4 accent-primary-600" />
            Proses lewat queue untuk file besar
          </label>
        </div>
        <div className="flex justify-end gap-2 border-t border-neutral-200 bg-neutral-50 px-5 py-4">
          <button type="button" onClick={onClose} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-4 py-2.5 text-sm"><icons.X className="h-4 w-4" /> Batal</button>
          <button type="submit" disabled={loading || !file || !preview} className="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60">
            {loading ? <icons.Loader2 className="h-4 w-4 animate-spin" /> : <UploadIcon className="h-4 w-4" />}
            Commit Import
          </button>
        </div>
      </form>
    </div>
  );
}

function StatPill({ label, value }) {
  return <div className="rounded-xl border border-neutral-200 p-3 text-sm"><div className="text-xs text-neutral-500">{label}</div><div className="mt-1 text-lg font-semibold">{value}</div></div>;
}

function JobStatusStrip({ jobs, onRefresh }) {
  return (
    <PanelCard title="Job Import/Export" action="Refresh" onAction={onRefresh}>
      <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        {rowsOf(jobs).slice(0, 6).map((job) => (
          <div key={job.uuid} className="rounded-xl border border-neutral-200 p-4 text-sm">
            <div className="flex items-center justify-between gap-3">
              <div className="font-semibold">{job.type} {job.resource}</div>
              <Badge label={job.status} />
            </div>
            <div className="mt-2 font-mono text-xs text-neutral-500">{String(job.uuid).slice(0, 8)}...</div>
            <div className="mt-3 h-2 overflow-hidden rounded-full bg-neutral-100"><div className="h-full bg-primary-600" style={{ width: `${job.progress || 0}%` }} /></div>
            <div className="mt-1 text-xs text-neutral-500">{job.progress || 0}% {job.total_rows ? `- ${job.processed_rows || 0}/${job.total_rows} baris` : ''}</div>
            {job.result && <div className="mt-2 text-xs text-neutral-600">{Object.entries(job.result).map(([key, value]) => `${key}: ${value}`).join(' | ')}</div>}
            {job.error && <div className="mt-2 text-xs text-red-600">{job.error}</div>}
            {job.download_url && <button type="button" onClick={() => downloadAuthenticatedFile(job.download_url, job.file_name || `${job.resource}.dat`)} className="mt-3 inline-flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-1.5 text-xs"><icons.Download className="h-3.5 w-3.5" /> Download</button>}
          </div>
        ))}
      </div>
    </PanelCard>
  );
}

function DownloadManagerModal({ jobs, onRefresh, onClose }) {
  const [status, setStatus] = useState('all');
  const filtered = rowsOf(jobs).filter((job) => status === 'all' || job.status === status);
  const remove = async (job) => {
    if (!window.confirm(`Hapus file job ${String(job.uuid).slice(0, 8)}?`)) return;
    await apiJson(`/web-actions/jobs/${job.uuid}`, { method: 'DELETE' });
    await onRefresh();
  };
  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-neutral-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
      <div className="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-neutral-200 px-5 py-4">
          <div><h2 className="font-semibold">Download Manager</h2><p className="text-xs text-neutral-500">Hasil export dan laporan import yang tersimpan sementara.</p></div>
          <button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 p-2" aria-label="Tutup modal"><icons.X className="h-4 w-4" /></button>
        </div>
        <div className="min-h-0 flex-1 overflow-y-auto p-5">
          <div className="mb-4 flex gap-2"><select value={status} onChange={(event) => setStatus(event.target.value)} className="rounded-xl border border-neutral-200 px-3 py-2 text-sm"><option value="all">Semua Status</option>{['queued', 'processing', 'completed', 'failed'].map((item) => <option key={item} value={item}>{item}</option>)}</select><button type="button" onClick={onRefresh} className="rounded-xl border border-neutral-200 px-3 py-2 text-sm">Refresh</button></div>
          <div className="space-y-3">{filtered.map((job) => <div key={job.uuid} className="grid gap-3 rounded-xl border border-neutral-200 p-4 md:grid-cols-[1fr_auto] md:items-center"><div><div className="flex flex-wrap items-center gap-2"><span className="font-semibold">{job.type} {job.resource}</span><Badge label={job.status} /></div><div className="mt-1 text-xs text-neutral-500">{job.file_name || String(job.uuid)} - {job.progress || 0}%</div>{job.expires_at && <div className="mt-1 text-xs text-neutral-400">Kedaluwarsa: {job.expires_at}</div>}</div><div className="flex gap-2">{job.download_url && <button type="button" onClick={() => downloadAuthenticatedFile(job.download_url, job.file_name)} className="rounded-lg border border-neutral-200 p-2" title="Download"><icons.Download className="h-4 w-4" /></button>}<button type="button" onClick={() => remove(job)} className="rounded-lg border border-red-200 p-2 text-red-600" title="Hapus"><icons.Trash2 className="h-4 w-4" /></button></div></div>)}{filtered.length === 0 && <div className="rounded-xl bg-neutral-50 p-8 text-center text-sm text-neutral-500">Belum ada file job.</div>}</div>
        </div>
      </div>
    </div>
  );
}

function QueueMonitorModal({ onClose }) {
  const [data, setData] = useState(null);
  const [error, setError] = useState('');
  const load = async () => { try { setData(await apiJson('/web-actions/queue/monitor')); setError(''); } catch (err) { setError(err.message); } };
  useEffect(() => { load(); }, []);
  const retry = async (uuid) => { await apiJson(`/web-actions/queue/failed/${uuid}/retry`, { method: 'POST', body: '{}' }); await load(); };
  const forget = async (uuid) => { await apiJson(`/web-actions/queue/failed/${uuid}`, { method: 'DELETE' }); await load(); };
  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-neutral-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
      <div className="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-neutral-200 px-5 py-4"><div><h2 className="font-semibold">Queue Monitor</h2><p className="text-xs text-neutral-500">Pantau worker, operation job, dan failed jobs.</p></div><button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 p-2" aria-label="Tutup modal"><icons.X className="h-4 w-4" /></button></div>
        <div className="min-h-0 flex-1 space-y-5 overflow-y-auto p-5">{error && <Alert tone="error">{error}</Alert>}<div className="grid gap-3 sm:grid-cols-3">{Object.entries(data?.workers || {}).map(([key, value]) => <StatPill key={key} label={`Worker ${key}`} value={value} />)}</div><div className="grid gap-3 sm:grid-cols-4">{Object.entries(data?.operations || {}).map(([key, value]) => <StatPill key={key} label={`Operation ${key}`} value={value} />)}</div><PanelCard title="Failed Jobs" action="Refresh" onAction={load}><div className="space-y-3">{rowsOf(data?.failed_jobs).map((job) => <div key={job.uuid} className="rounded-xl border border-red-100 p-4"><div className="flex flex-wrap items-center justify-between gap-3"><div><div className="font-semibold">{job.job}</div><div className="text-xs text-neutral-500">{job.queue} - {job.failed_at}</div></div><div className="flex gap-2"><button type="button" onClick={() => retry(job.uuid)} className="rounded-lg border border-neutral-200 px-3 py-2 text-xs">Retry</button><button type="button" onClick={() => forget(job.uuid)} className="rounded-lg border border-red-200 px-3 py-2 text-xs text-red-600">Hapus</button></div></div><div className="mt-2 text-xs text-red-600">{job.exception}</div></div>)}{rowsOf(data?.failed_jobs).length === 0 && <div className="text-sm text-neutral-500">Tidak ada failed job.</div>}</div></PanelCard></div>
      </div>
    </div>
  );
}

function RestoreBackupModal({ draft, setDraft, loading, onSubmit, onClose }) {
  return <div className="fixed inset-0 z-50 grid place-items-center bg-neutral-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true"><form onSubmit={onSubmit} className="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl"><div className="flex items-center justify-between border-b border-neutral-200 px-5 py-4"><div><h2 className="font-semibold">Ajukan Restore Backup</h2><p className="text-xs text-neutral-500">{draft.name || `Backup #${draft.id}`}</p></div><button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 p-2" aria-label="Tutup modal"><icons.X className="h-4 w-4" /></button></div><div className="space-y-3 p-5"><Alert tone="error">Restore memerlukan MFA dan persetujuan owner serta super admin. Ketik RESTORE untuk membuat permintaan.</Alert><textarea required minLength={10} value={draft.reason || ''} onChange={(event) => setDraft((current) => ({ ...current, reason: event.target.value }))} className="h-24 w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm" placeholder="Alasan restore dan referensi insiden" /><input required inputMode="numeric" pattern="[0-9]{6}" maxLength={6} value={draft.mfa_code || ''} onChange={(event) => setDraft((current) => ({ ...current, mfa_code: event.target.value.replace(/\D/g, '') }))} className="w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm" placeholder="Kode MFA 6 digit" /><input value={draft.confirmation || ''} onChange={(event) => setDraft((current) => ({ ...current, confirmation: event.target.value }))} className="w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm" placeholder="RESTORE" /></div><div className="flex justify-end gap-2 border-t border-neutral-200 bg-neutral-50 px-5 py-4"><button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 bg-white px-4 py-2.5 text-sm">Batal</button><button type="submit" disabled={loading || draft.confirmation !== 'RESTORE' || String(draft.mfa_code || '').length !== 6 || String(draft.reason || '').length < 10} className="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">Ajukan Pemulihan</button></div></form></div>;
}

function RestoreApprovalModal({ onClose }) {
  const [rows, setRows] = useState([]);
  const [mfaCode, setMfaCode] = useState('');
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const load = async () => { try { const json = await apiJson('/web-actions/backup/restore-requests'); setRows(rowsOf(json.data)); setError(''); } catch (err) { setError(err.message); } };
  useEffect(() => { load(); }, []);
  const action = async (row, type) => { setError(''); setMessage(''); try { const json = await apiJson(`/web-actions/backup/restore-requests/${row.id}/${type}`, { method: 'POST', body: JSON.stringify({ mfa_code: mfaCode }) }); setMessage(json.message); await load(); } catch (err) { setError(err.message); } };
  return <div className="fixed inset-0 z-50 grid place-items-center bg-neutral-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true"><div className="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"><div className="flex items-center justify-between border-b border-neutral-200 px-5 py-4"><div><h2 className="font-semibold">Persetujuan Restore</h2><p className="text-xs text-neutral-500">Persetujuan berlapis dan eksekusi khusus super admin.</p></div><button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 p-2" aria-label="Tutup modal"><icons.X className="h-4 w-4" /></button></div><div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">{message && <Alert tone="success">{message}</Alert>}{error && <Alert tone="error">{error}</Alert>}<input inputMode="numeric" maxLength={6} value={mfaCode} onChange={(event) => setMfaCode(event.target.value.replace(/\D/g, ''))} className="w-full max-w-xs rounded-xl border border-neutral-200 px-3 py-2 text-sm" placeholder="Kode MFA super admin" /><div className="space-y-3">{rows.map((row) => <div key={row.id} className="rounded-xl border border-neutral-200 p-4"><div className="flex flex-wrap items-start justify-between gap-3"><div><div className="font-semibold">#{row.id} {row.backup_name || `Backup #${row.backup_id}`}</div><div className="mt-1 text-xs text-neutral-500">{row.requester_name || `User #${row.requested_by}`} - {row.reason}</div><div className="mt-2 flex flex-wrap gap-2"><Badge label={row.status} />{rowsOf(row.approvals).map((approval) => <span key={approval.role} className="rounded-full bg-neutral-100 px-2 py-1 text-xs">{titleFromSlug(approval.role)}</span>)}</div></div><div className="flex gap-2"><button type="button" disabled={mfaCode.length !== 6 || !['pending', 'approved'].includes(row.status)} onClick={() => action(row, 'approve')} className="rounded-lg border border-neutral-200 px-3 py-2 text-xs disabled:opacity-40">Setujui</button><button type="button" disabled={mfaCode.length !== 6 || row.status !== 'approved'} onClick={() => action(row, 'execute')} className="rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white disabled:opacity-40">Eksekusi</button></div></div></div>)}{rows.length === 0 && <div className="rounded-xl bg-neutral-50 p-8 text-center text-sm text-neutral-500">Belum ada permintaan restore.</div>}</div></div></div></div>;
}

function CustomRoleModal({ onClose }) {
  const empty = { id: null, name: '', label: '', description: '', read: [], write: [], status: 'active', membership_ids: [] };
  const [data, setData] = useState(null);
  const [draft, setDraft] = useState(empty);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const load = async () => { try { setData(await apiJson('/web-api/permissions/custom-roles')); setError(''); } catch (err) { setError(err.message); } };
  useEffect(() => { load(); }, []);
  const select = (role) => setDraft({ ...role, membership_ids: rowsOf(data?.memberships).filter((item) => String(item.tenant_role_id) === String(role.id)).map((item) => item.id) });
  const toggle = (resource, ability) => setDraft((current) => ({ ...current, [ability]: current[ability].includes(resource) ? current[ability].filter((item) => item !== resource) : [...current[ability], resource] }));
  const save = async (event) => { event.preventDefault(); setError(''); try { const endpoint = draft.id ? `/web-api/permissions/custom-roles/${draft.id}` : '/web-api/permissions/custom-roles'; const method = draft.id ? 'PATCH' : 'POST'; const saved = await apiJson(endpoint, { method, body: JSON.stringify(draft) }); const roleId = draft.id || saved.id; await apiJson(`/web-api/permissions/custom-roles/${roleId}/assign`, { method: 'POST', body: JSON.stringify({ membership_ids: draft.membership_ids }) }); setMessage('Custom role dan assignment berhasil disimpan.'); setDraft(empty); await load(); } catch (err) { setError(err.message); } };
  const remove = async () => { if (!draft.id || !window.confirm('Hapus custom role ini?')) return; await apiJson(`/web-api/permissions/custom-roles/${draft.id}`, { method: 'DELETE' }); setDraft(empty); await load(); };
  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-neutral-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true"><form onSubmit={save} className="flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"><div className="flex items-center justify-between border-b border-neutral-200 px-5 py-4"><div><h2 className="font-semibold">Custom Role Tenant</h2><p className="text-xs text-neutral-500">Buat role khusus, permission resource, dan assignment pengguna.</p></div><button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 p-2" aria-label="Tutup modal"><icons.X className="h-4 w-4" /></button></div><div className="grid min-h-0 flex-1 overflow-hidden lg:grid-cols-[260px_1fr]"><aside className="overflow-y-auto border-r border-neutral-200 p-4"><button type="button" onClick={() => setDraft(empty)} className="mb-3 w-full rounded-xl bg-primary-600 px-3 py-2 text-sm font-semibold text-white">Role Baru</button>{rowsOf(data?.roles).map((role) => <button key={role.id} type="button" onClick={() => select(role)} className={`mb-2 w-full rounded-xl border px-3 py-2 text-left text-sm ${draft.id === role.id ? 'border-primary-600 bg-primary-50' : 'border-neutral-200'}`}><div className="font-semibold">{role.label}</div><div className="text-xs text-neutral-500">{role.name}</div></button>)}</aside><div className="min-h-0 space-y-4 overflow-y-auto p-5">{message && <Alert tone="success">{message}</Alert>}{error && <Alert tone="error">{error}</Alert>}<div className="grid gap-3 md:grid-cols-2"><label className="text-sm"><span className="mb-1 block font-medium">Kode Role</span><input required value={draft.name} onChange={(event) => setDraft((current) => ({ ...current, name: event.target.value.toLowerCase().replace(/[^a-z0-9_-]/g, '_') }))} className="w-full rounded-xl border border-neutral-200 px-3 py-2" /></label><label className="text-sm"><span className="mb-1 block font-medium">Nama Role</span><input required value={draft.label} onChange={(event) => setDraft((current) => ({ ...current, label: event.target.value }))} className="w-full rounded-xl border border-neutral-200 px-3 py-2" /></label><label className="text-sm md:col-span-2"><span className="mb-1 block font-medium">Deskripsi</span><textarea value={draft.description || ''} onChange={(event) => setDraft((current) => ({ ...current, description: event.target.value }))} className="h-20 w-full rounded-xl border border-neutral-200 px-3 py-2" /></label></div><div className="overflow-x-auto rounded-xl border border-neutral-200"><table className="w-full text-sm"><thead><tr className="border-b bg-neutral-50 text-left text-xs text-neutral-500"><th className="px-3 py-2">Resource</th><th className="px-3 py-2">Baca</th><th className="px-3 py-2">Kelola</th></tr></thead><tbody>{rowsOf(data?.resources).map((resource) => <tr key={resource} className="border-b border-neutral-100"><td className="px-3 py-2 font-medium">{titleFromSlug(resource)}</td><td className="px-3 py-2"><input type="checkbox" checked={draft.read.includes(resource)} onChange={() => toggle(resource, 'read')} /></td><td className="px-3 py-2"><input type="checkbox" checked={draft.write.includes(resource)} onChange={() => toggle(resource, 'write')} /></td></tr>)}</tbody></table></div><div className="rounded-xl border border-neutral-200"><div className="border-b px-4 py-3 text-sm font-semibold">Assignment Pengguna</div><div className="grid gap-2 p-4 md:grid-cols-2">{rowsOf(data?.memberships).map((member) => <label key={member.id} className="flex items-center gap-2 rounded-lg border border-neutral-100 p-2 text-sm"><input type="checkbox" checked={draft.membership_ids.includes(member.id)} onChange={() => setDraft((current) => ({ ...current, membership_ids: current.membership_ids.includes(member.id) ? current.membership_ids.filter((id) => id !== member.id) : [...current.membership_ids, member.id] }))} /><span>{member.user_name} <span className="text-xs text-neutral-500">({member.base_role})</span></span></label>)}</div></div></div></div><div className="flex justify-between border-t border-neutral-200 bg-neutral-50 px-5 py-4"><div>{draft.id && <button type="button" onClick={remove} className="rounded-xl border border-red-200 px-4 py-2 text-sm text-red-600">Hapus Role</button>}</div><div className="flex gap-2"><button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 bg-white px-4 py-2 text-sm">Tutup</button><button type="submit" className="rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white">Simpan Role</button></div></div></form></div>
  );
}

function BulkModal({ count, canDelete, loading, onSubmit, onClose }) {
  const [action, setAction] = useState('status');
  const [status, setStatus] = useState('active');

  const submit = (event) => {
    event.preventDefault();
    onSubmit({ action, status });
  };

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-neutral-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
      <form onSubmit={submit} className="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-neutral-200 px-5 py-4">
          <div>
            <h2 className="font-semibold">Aksi Massal</h2>
            <p className="text-xs text-neutral-500">{count} data terpilih. Aksi tetap dibatasi scope role aktif.</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 p-2 hover:bg-neutral-50" aria-label="Tutup modal"><icons.X className="h-4 w-4" /></button>
        </div>
        <div className="grid gap-3 p-5">
          <label className="block text-sm">
            <span className="mb-1 block font-medium">Aksi</span>
            <select value={action} onChange={(event) => setAction(event.target.value)} className="w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm">
              <option value="status">Ubah Status</option>
              {canDelete && <option value="delete">Hapus Data</option>}
            </select>
          </label>
          {action === 'status' && (
            <label className="block text-sm">
              <span className="mb-1 block font-medium">Status Baru</span>
              <select value={status} onChange={(event) => setStatus(event.target.value)} className="w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm">
                {statusOptions.map((item) => <option key={item} value={item}>{item}</option>)}
              </select>
            </label>
          )}
          {action === 'delete' && <Alert tone="error">Data terpilih akan dihapus atau diarsipkan bila resource mendukung soft delete.</Alert>}
        </div>
        <div className="flex justify-end gap-2 border-t border-neutral-200 bg-neutral-50 px-5 py-4">
          <button type="button" onClick={onClose} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-4 py-2.5 text-sm"><icons.X className="h-4 w-4" /> Batal</button>
          <button type="submit" disabled={loading || count < 1} className="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60">
            {loading ? <icons.Loader2 className="h-4 w-4 animate-spin" /> : <icons.Save className="h-4 w-4" />}
            Terapkan
          </button>
        </div>
      </form>
    </div>
  );
}

function PermissionMatrixModal({ onClose }) {
  const [matrix, setMatrix] = useState(null);
  const [role, setRole] = useState('');
  const [read, setRead] = useState([]);
  const [write, setWrite] = useState([]);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    let mounted = true;
    apiJson('/web-api/permissions/matrix')
      .then((json) => {
        if (!mounted) return;
        setMatrix(json);
        const firstRole = rowsOf(json.roles).find((item) => !['super_admin', 'owner_pesantren'].includes(item.name))?.name || '';
        setRole(firstRole);
      })
      .catch((err) => setError(err.message));
    return () => { mounted = false; };
  }, []);

  useEffect(() => {
    if (!matrix || !role) return;
    setRead(rowsOf(matrix.matrix?.[role]?.read));
    setWrite(rowsOf(matrix.matrix?.[role]?.write));
  }, [matrix, role]);

  const toggle = (resource, ability) => {
    const setter = ability === 'write' ? setWrite : setRead;
    setter((current) => current.includes(resource) ? current.filter((item) => item !== resource) : [...current, resource]);
  };

  const save = async (event) => {
    event.preventDefault();
    setSaving(true);
    setMessage('');
    setError('');
    try {
      await apiJson('/web-api/permissions/matrix', {
        method: 'PATCH',
        body: JSON.stringify({ role, read, write }),
      });
      const fresh = await apiJson('/web-api/permissions/matrix');
      setMatrix(fresh);
      setMessage('Permission berhasil diperbarui.');
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-neutral-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
      <form onSubmit={save} className="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-neutral-200 px-5 py-4">
          <div>
            <h2 className="font-semibold">Permission Matrix</h2>
            <p className="text-xs text-neutral-500">Atur akses baca dan kelola per role. Scope mengikuti {matrix?.source || 'tenant/global'}.</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 p-2 hover:bg-neutral-50" aria-label="Tutup modal"><icons.X className="h-4 w-4" /></button>
        </div>
        <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
          {message && <Alert tone="success">{message}</Alert>}
          {error && <Alert tone="error">{error}</Alert>}
          <label className="block max-w-sm text-sm">
            <span className="mb-1 block font-medium">Role</span>
            <select value={role} onChange={(event) => setRole(event.target.value)} className="w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm">
              {rowsOf(matrix?.roles).filter((item) => item.name !== 'super_admin').map((item) => <option key={item.name} value={item.name}>{item.label}</option>)}
            </select>
          </label>
          <div className="overflow-x-auto rounded-xl border border-neutral-200">
            <table className="w-full text-left text-sm">
              <thead><tr className="border-b border-neutral-200 bg-neutral-50 text-xs text-neutral-500"><th className="px-3 py-3">Resource</th><th className="px-3 py-3">Baca</th><th className="px-3 py-3">Kelola</th></tr></thead>
              <tbody>
                {rowsOf(matrix?.resources).map((resource) => (
                  <tr key={resource} className="border-b border-neutral-100">
                    <td className="px-3 py-2 font-medium">{titleFromSlug(resource)}</td>
                    <td className="px-3 py-2"><input type="checkbox" checked={read.includes(resource)} onChange={() => toggle(resource, 'read')} className="h-4 w-4 accent-primary-600" /></td>
                    <td className="px-3 py-2"><input type="checkbox" checked={write.includes(resource)} onChange={() => toggle(resource, 'write')} className="h-4 w-4 accent-primary-600" /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
        <div className="flex justify-end gap-2 border-t border-neutral-200 bg-neutral-50 px-5 py-4">
          <button type="button" onClick={onClose} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-4 py-2.5 text-sm"><icons.X className="h-4 w-4" /> Tutup</button>
          <button type="submit" disabled={saving || !role} className="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60">
            {saving ? <icons.Loader2 className="h-4 w-4 animate-spin" /> : <icons.Save className="h-4 w-4" />}
            Simpan Permission
          </button>
        </div>
      </form>
    </div>
  );
}

function PdfTemplateModal({ shared, onClose, onSaved }) {
  const templates = [
    { key: 'pdf.receipt_template', label: 'Kwitansi' },
    { key: 'pdf.letter_template', label: 'Surat' },
    { key: 'pdf.report_template', label: 'Laporan' },
  ];
  const [active, setActive] = useState(templates[0].key);
  const [settings, setSettings] = useState([]);
  const [draft, setDraft] = useState({ title: '', header: '', footer: '', accent_color: '#127A4F', logo_text: 'Pesantenku' });
  const [loadingSettings, setLoadingSettings] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    let mounted = true;
    apiJson('/web-api/system-settings?per_page=100&search=pdf.')
      .then((json) => { if (mounted) setSettings(rowsOf(json.data)); })
      .catch(() => { if (mounted) setSettings(rowsOf(shared.settings)); })
      .finally(() => { if (mounted) setLoadingSettings(false); });
    return () => { mounted = false; };
  }, [shared.settings]);

  useEffect(() => {
    const row = settings.find((item) => item.key === active || item.kunci === active);
    const value = parseJsonValue(row?.value ?? row?.nilai) || {};
    setDraft({
      title: value.title || (active.includes('receipt') ? 'Kwitansi Pembayaran' : active.includes('letter') ? 'Surat Pesantren' : 'Laporan Pesantren'),
      header: value.header || 'Pondok Pesantren',
      footer: value.footer || 'Dokumen dibuat otomatis oleh Pesantenku.',
      accent_color: value.accent_color || '#127A4F',
      logo_text: value.logo_text || 'Pesantenku',
    });
  }, [active, settings]);

  const save = async (event) => {
    event.preventDefault();
    setSaving(true);
    setError('');
    const row = settings.find((item) => item.key === active || item.kunci === active);
    const payload = { key: active, value: JSON.stringify(draft) };
    try {
      if (row?.id) {
        await apiJson(`/web-api/system-settings/${row.id}`, { method: 'PATCH', body: JSON.stringify(payload) });
      } else {
        await apiJson('/web-api/system-settings', { method: 'POST', body: JSON.stringify(payload) });
      }
      onSaved?.();
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-neutral-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
      <form onSubmit={save} className="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-neutral-200 px-5 py-4">
          <div>
            <h2 className="font-semibold">Template PDF Tenant</h2>
            <p className="text-xs text-neutral-500">Atur tampilan dasar kwitansi, surat, dan laporan PDF.</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 p-2 hover:bg-neutral-50" aria-label="Tutup modal"><icons.X className="h-4 w-4" /></button>
        </div>
        <div className="min-h-0 flex-1 overflow-y-auto p-5">
          {error && <Alert tone="error">{error}</Alert>}
          <div className="mb-4 flex flex-wrap gap-2">
            {templates.map((item) => <button key={item.key} type="button" onClick={() => setActive(item.key)} className={`rounded-xl border px-4 py-2 text-sm ${active === item.key ? 'border-primary-600 bg-primary-50 text-primary-700' : 'border-neutral-200'}`}>{item.label}</button>)}
          </div>
          <div className="grid gap-5 lg:grid-cols-2">
            <div className="grid gap-3">
              {['title', 'header', 'footer', 'logo_text'].map((field) => (
                <label key={field} className="block text-sm">
                  <span className="mb-1 block font-medium">{columnTitle(field)}</span>
                  {field === 'footer' ? (
                    <textarea value={draft[field]} onChange={(event) => setDraft((current) => ({ ...current, [field]: event.target.value }))} className="h-24 w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-primary-600" />
                  ) : (
                    <input value={draft[field]} onChange={(event) => setDraft((current) => ({ ...current, [field]: event.target.value }))} className="w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-primary-600" />
                  )}
                </label>
              ))}
              <label className="block text-sm">
                <span className="mb-1 block font-medium">Warna Aksen</span>
                <input type="color" value={draft.accent_color} onChange={(event) => setDraft((current) => ({ ...current, accent_color: event.target.value }))} className="h-11 w-24 rounded-xl border border-neutral-200 p-1" />
              </label>
            </div>
            <div className="rounded-xl border border-neutral-200 bg-neutral-50 p-5">
              <div className="rounded-lg bg-white p-6 shadow-sm">
                <div className="flex items-center justify-between border-b pb-4" style={{ borderColor: draft.accent_color }}>
                  <div>
                    <div className="text-xs font-semibold uppercase tracking-wide" style={{ color: draft.accent_color }}>{draft.logo_text}</div>
                    <div className="mt-1 text-xl font-semibold">{draft.title}</div>
                  </div>
                  <div className="h-12 w-12 rounded-lg" style={{ backgroundColor: draft.accent_color }} />
                </div>
                <div className="mt-5 text-sm font-medium">{draft.header}</div>
                <div className="mt-4 space-y-2 text-xs text-neutral-500">
                  <div>Santri: Najwa Karimah</div>
                  <div>Status: Lunas / Disetujui</div>
                  <div>Tanggal: {new Date().toLocaleDateString('id-ID')}</div>
                </div>
                <div className="mt-8 border-t pt-4 text-xs text-neutral-500">{draft.footer}</div>
              </div>
            </div>
          </div>
        </div>
        <div className="flex justify-end gap-2 border-t border-neutral-200 bg-neutral-50 px-5 py-4">
          <button type="button" onClick={onClose} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-4 py-2.5 text-sm"><icons.X className="h-4 w-4" /> Batal</button>
          <button type="submit" disabled={saving || loadingSettings} className="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60">
            {(saving || loadingSettings) ? <icons.Loader2 className="h-4 w-4 animate-spin" /> : <icons.Save className="h-4 w-4" />}
            Simpan Template
          </button>
        </div>
      </form>
    </div>
  );
}

function CrudModal({ meta, shared, formData, setFormData, editing, mode, saving, formErrors = {}, onSubmit, onClose, timeline = [], timelineLoading = false }) {
  const viewOnly = mode === 'view';
  const title = viewOnly ? `Detail ${meta.title}` : `${editing && mode === 'edit' ? 'Edit' : 'Tambah'} ${meta.title}`;

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-neutral-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
      <div className="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-neutral-200 px-5 py-4">
          <div>
            <h2 className="font-semibold">{title}</h2>
            <p className="text-xs text-neutral-500">{viewOnly ? 'Baca data tanpa mengubah isi.' : 'Isi data lalu simpan. Data langsung tersambung ke API.'}</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 p-2 hover:bg-neutral-50" aria-label="Tutup modal"><icons.X className="h-4 w-4" /></button>
        </div>
        <form onSubmit={onSubmit} className="flex min-h-0 flex-1 flex-col">
          <div className="grid gap-3 overflow-y-auto p-5 md:grid-cols-2">
            {meta.fields.map((field) => <FieldInput key={field.name} field={field} shared={shared} value={formData[field.name]} disabled={viewOnly} error={formErrors[field.name]} onChange={(value) => setFormData((current) => ({ ...current, [field.name]: value }))} />)}
            {viewOnly && <div className="md:col-span-2"><TimelineList timeline={timeline} loading={timelineLoading} shared={shared} /></div>}
          </div>
          <div className="flex flex-col gap-2 border-t border-neutral-200 bg-neutral-50 px-5 py-4 sm:flex-row sm:justify-end">
            <button type="button" onClick={onClose} className="inline-flex items-center justify-center gap-2 rounded-xl border border-neutral-200 bg-white px-4 py-2.5 text-sm"><icons.X className="h-4 w-4" /> {viewOnly ? 'Tutup' : 'Batal'}</button>
            {!viewOnly && (
              <button type="submit" disabled={saving} className="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60">
                {saving ? <icons.Loader2 className="h-4 w-4 animate-spin" /> : <icons.Save className="h-4 w-4" />}
                {editing && mode === 'edit' ? 'Simpan Perubahan' : 'Tambah Data'}
              </button>
            )}
          </div>
        </form>
      </div>
    </div>
  );
}

function WorkflowModal({ draft, note, setNote, saving, onSubmit, onClose }) {
  const { row, action } = draft;
  const required = action.requiresNote;
  const title = `${action.label} ${row.name || row.title || row.invoice_number || row.queue_number || `#${row.id}`}`;

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-neutral-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
      <form onSubmit={onSubmit} className="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-neutral-200 px-5 py-4">
          <div>
            <h2 className="font-semibold">{title}</h2>
            <p className="text-xs text-neutral-500">{required ? 'Catatan wajib diisi untuk aksi ini.' : 'Catatan opsional akan masuk ke audit timeline.'}</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 p-2 hover:bg-neutral-50" aria-label="Tutup modal"><icons.X className="h-4 w-4" /></button>
        </div>
        <div className="p-5">
          <label className="block text-sm">
            <span className="mb-1 block font-medium">Catatan</span>
            <textarea required={required} value={note} onChange={(event) => setNote(event.target.value)} className="h-28 w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-primary-600" placeholder={required ? 'Tuliskan alasan penolakan atau pembatalan.' : 'Tambahkan catatan bila diperlukan.'} />
          </label>
        </div>
        <div className="flex justify-end gap-2 border-t border-neutral-200 bg-neutral-50 px-5 py-4">
          <button type="button" onClick={onClose} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-4 py-2.5 text-sm"><icons.X className="h-4 w-4" /> Batal</button>
          <button type="submit" disabled={saving} className="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60">
            {saving ? <icons.Loader2 className="h-4 w-4 animate-spin" /> : <icons.Save className="h-4 w-4" />}
            Simpan Status
          </button>
        </div>
      </form>
    </div>
  );
}

function TimelineList({ timeline, loading, shared }) {
  if (loading) {
    return (
      <div className="rounded-xl border border-neutral-200">
        <div className="border-b border-neutral-100 px-4 py-3 text-sm font-semibold">Timeline Perubahan</div>
        <div className="p-4 text-sm text-neutral-500">Memuat timeline...</div>
      </div>
    );
  }

  if (!timeline.length) {
    return (
      <div className="rounded-xl border border-neutral-200">
        <div className="border-b border-neutral-100 px-4 py-3 text-sm font-semibold">Timeline Perubahan</div>
        <div className="p-4 text-sm text-neutral-500">Belum ada riwayat perubahan.</div>
      </div>
    );
  }

  return (
    <div className="rounded-xl border border-neutral-200">
      <div className="border-b border-neutral-100 px-4 py-3 text-sm font-semibold">Timeline Perubahan</div>
      <div className="max-h-64 overflow-y-auto p-4">
        {timeline.map((item) => (
          <div key={item.id} className="border-l-2 border-primary-100 pb-4 pl-4 last:pb-0">
            <div className="flex flex-wrap items-center gap-2 text-sm font-semibold">
              <span>{titleFromSlug(String(item.action || '').toLowerCase())}</span>
              {item.to_status && <Badge label={item.to_status} />}
            </div>
            <div className="mt-1 text-xs text-neutral-500">{item.actor} - {item.created_at}</div>
            {item.note && <div className="mt-2 rounded-lg bg-neutral-50 px-3 py-2 text-sm">{item.note}</div>}
            {rowsOf(item.changes).length > 0 && (
              <div className="mt-2 space-y-1 text-xs text-neutral-600">
                {rowsOf(item.changes).slice(0, 4).map((change) => (
                  <div key={`${item.id}-${change.field}`}>{change.label || columnTitle(change.field)}: {String(change.before_label ?? displayCellValue(change.field, change.before, {}, shared, inferredFieldForColumn(change.field)) ?? '-')}{' -> '}{String(change.after_label ?? displayCellValue(change.field, change.after, {}, shared, inferredFieldForColumn(change.field)) ?? '-')}</div>
                ))}
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}

function AuditLogModal({ row, shared, onClose }) {
  const metadata = parseJsonValue(row.metadata) || {};
  const before = metadata.before || {};
  const after = metadata.after || {};
  const fields = Array.from(new Set([...Object.keys(before || {}), ...Object.keys(after || {})])).filter((field) => field !== 'updated_at');
  const changes = fields
    .map((field) => ({ field, before: before?.[field], after: after?.[field] }))
    .filter((change) => JSON.stringify(change.before ?? null) !== JSON.stringify(change.after ?? null));

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-neutral-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
      <div className="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-neutral-200 px-5 py-4">
          <div>
            <h2 className="font-semibold">Detail Audit Log</h2>
            <p className="text-xs text-neutral-500">{row.aktivitas || row.action} - {row.waktu || row.created_at}</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 p-2 hover:bg-neutral-50" aria-label="Tutup modal"><icons.X className="h-4 w-4" /></button>
        </div>
        <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
          <div className="grid gap-3 md:grid-cols-2">
            <StatPill label="Modul" value={row.modul || row.module || '-'} />
            <StatPill label="Pengguna" value={row.pengguna || row.actor || 'Sistem'} />
          </div>
          {metadata.note && <Alert tone="info">{metadata.note}</Alert>}
          <div className="rounded-xl border border-neutral-200">
            <div className="border-b border-neutral-100 px-4 py-3 text-sm font-semibold">Diff Perubahan</div>
            <div className="max-h-80 overflow-y-auto p-4">
              {changes.length > 0 ? changes.map((change) => (
                <div key={change.field} className="border-b border-neutral-100 py-2 text-sm last:border-0">
                  <div className="font-medium">{columnTitle(change.field)}</div>
                  <div className="mt-1 text-xs text-neutral-600">
                    {String(displayCellValue(change.field, change.before, before, shared, inferredFieldForColumn(change.field)) ?? '-')} {' -> '}
                    {String(displayCellValue(change.field, change.after, after, shared, inferredFieldForColumn(change.field)) ?? '-')}
                  </div>
                </div>
              )) : <div className="text-sm text-neutral-500">Tidak ada diff field tersimpan.</div>}
            </div>
          </div>
          <div className="rounded-xl border border-neutral-200">
            <div className="border-b border-neutral-100 px-4 py-3 text-sm font-semibold">Metadata Mentah</div>
            <pre className="max-h-52 overflow-auto p-4 text-xs text-neutral-600">{JSON.stringify(metadata, null, 2)}</pre>
          </div>
        </div>
        <div className="flex justify-end border-t border-neutral-200 bg-neutral-50 px-5 py-4">
          <button type="button" onClick={onClose} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-4 py-2.5 text-sm"><icons.X className="h-4 w-4" /> Tutup</button>
        </div>
      </div>
    </div>
  );
}

function FieldInput({ field, shared, value, disabled = false, error, onChange }) {
  const label = titleFromSlug(field.name);
  const options = optionsFor(field, shared);
  const errorText = Array.isArray(error) ? error[0] : error;

  if (field.type === 'textarea') {
    return <label className="block text-sm md:col-span-2"><span className="mb-1 block font-medium">{label}</span><textarea disabled={disabled} value={value ?? ''} onChange={(event) => onChange(event.target.value)} className={`h-24 w-full rounded-xl border px-3 py-2 text-sm outline-none focus:border-primary-600 disabled:bg-neutral-50 disabled:text-neutral-500 ${errorText ? 'border-red-300 bg-red-50/40' : 'border-neutral-200'}`} />{errorText && <span className="mt-1 block text-xs text-red-600">{errorText}</span>}</label>;
  }

  if (field.type === 'boolean') {
    return <label className={`flex items-center gap-3 rounded-xl border px-3 py-2 text-sm ${errorText ? 'border-red-300 bg-red-50/40' : 'border-neutral-200'}`}><input type="checkbox" disabled={disabled} checked={Boolean(value)} onChange={(event) => onChange(event.target.checked)} className="h-4 w-4 accent-primary-600 disabled:opacity-50" /><span className="font-medium">{label}</span>{errorText && <span className="text-xs text-red-600">{errorText}</span>}</label>;
  }

  if (field.type?.startsWith('select')) {
    return (
      <label className="block text-sm">
        <span className="mb-1 block font-medium">{label}</span>
        <select disabled={disabled} value={value ?? ''} onChange={(event) => onChange(event.target.value)} className={`w-full rounded-xl border px-3 py-2 text-sm outline-none focus:border-primary-600 disabled:bg-neutral-50 disabled:text-neutral-500 ${errorText ? 'border-red-300 bg-red-50/40' : 'border-neutral-200'}`}>
          <option value="">Pilih {label}</option>
          {options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
        </select>
        {errorText && <span className="mt-1 block text-xs text-red-600">{errorText}</span>}
      </label>
    );
  }

  return <label className="block text-sm"><span className="mb-1 block font-medium">{label}</span><input disabled={disabled} type={field.type || 'text'} value={value ?? ''} onChange={(event) => onChange(event.target.value)} className={`w-full rounded-xl border px-3 py-2 text-sm outline-none focus:border-primary-600 disabled:bg-neutral-50 disabled:text-neutral-500 ${errorText ? 'border-red-300 bg-red-50/40' : 'border-neutral-200'}`} />{errorText && <span className="mt-1 block text-xs text-red-600">{errorText}</span>}</label>;
}

function Logo() {
  return <div className="grid h-10 w-10 place-items-center rounded-xl border border-current/20 bg-white/10"><svg viewBox="0 0 48 48" className="h-8 w-8" fill="none"><path d="M24 5 43 41H5L24 5Z" stroke="currentColor" strokeWidth="2" /><path d="M24 15v18M18 32c4-2 8-2 12 0M16 36h16" stroke="currentColor" strokeWidth="2" strokeLinecap="round" /></svg></div>;
}

function rowsOf(rows) {
  return Array.isArray(rows) ? rows : Object.values(rows || {});
}

function parseJsonValue(value) {
  if (!value) return null;
  if (typeof value === 'object') return value;
  try {
    return JSON.parse(value);
  } catch {
    return null;
  }
}

function mergeRows(...groups) {
  return groups.flatMap((group) => rowsOf(group));
}

async function apiJson(url, options = {}) {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  const method = String(options.method || 'GET').toUpperCase();
  const requiresIdempotency = ['POST', 'PUT', 'PATCH'].includes(method) && (/\/workflow\//.test(url) || /\/payments(?:\/|$)/.test(url) || /\/backup\/\d+\/restore/.test(url) || /\/backup\/restore-requests\//.test(url));
  const requestSignature = `${method}:${url}:${String(options.body || '')}`;
  const idempotencyKey = requiresIdempotency
    ? pendingIdempotencyKeys.get(requestSignature) || globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`
    : null;
  if (idempotencyKey) pendingIdempotencyKeys.set(requestSignature, idempotencyKey);
  const response = await fetch(url, {
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
      ...(idempotencyKey ? { 'Idempotency-Key': idempotencyKey } : {}),
      ...(options.headers || {}),
    },
    ...options,
  });
  const text = await response.text();
  if (idempotencyKey) pendingIdempotencyKeys.delete(requestSignature);
  const json = text ? JSON.parse(text) : {};
  if (!response.ok) {
    const error = new Error(json.message || json.error || `Request gagal (${response.status})`);
    error.errors = json.errors || {};
    throw error;
  }
  return json;
}

async function apiUpload(url, file, fields = {}) {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  const formData = new FormData();
  formData.append('file', file);
  Object.entries(fields).forEach(([key, value]) => formData.append(key, value));
  const response = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
    },
    body: formData,
  });
  const text = await response.text();
  const json = text ? JSON.parse(text) : {};
  if (!response.ok) {
    const error = new Error(json.message || json.error || `Upload gagal (${response.status})`);
    error.errors = json.errors || {};
    throw error;
  }
  return json;
}

async function exportResource(resource, title, format = 'csv', params = new URLSearchParams()) {
  if (!resource) return;
  const query = new URLSearchParams(params);
  query.set('format', format);
  const response = await fetch(`/web-api/${resource}/export?${query.toString()}`, { credentials: 'same-origin', headers: { Accept: '*/*' } });
  if (!response.ok) {
    window.alert(`Export gagal (${response.status}).`);
    return;
  }
  const blob = await response.blob();
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `${slug(title)}.${format === 'pdf' ? 'pdf' : format === 'excel' ? 'xls' : 'csv'}`;
  link.click();
  window.URL.revokeObjectURL(url);
}

async function downloadAuthenticatedFile(url, filename) {
  const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: '*/*' } });
  if (!response.ok) {
    window.alert(`Download gagal (${response.status}).`);
    return;
  }
  const blob = await response.blob();
  const objectUrl = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = objectUrl;
  link.download = filename;
  link.click();
  window.URL.revokeObjectURL(objectUrl);
}

function exportRowsToCsv(rows, title) {
  const normalized = rowsOf(rows);
  const columns = Object.keys(normalized[0] || {});
  const csv = [
    columns.join(','),
    ...normalized.map((row) => columns.map((column) => `"${String(row[column] ?? '').replaceAll('"', '""')}"`).join(',')),
  ].join('\n');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `${slug(title)}.csv`;
  link.click();
  window.URL.revokeObjectURL(url);
}

function parseField(definition) {
  if (Array.isArray(definition)) {
    return { name: definition[0], type: definition[1] };
  }
  return { name: definition, type: definition === 'status' ? 'select:status' : 'text' };
}

function fieldsFor(resource, rows = []) {
  const configured = resourceFields[resource];
  if (configured) return configured.map(parseField);
  const sample = rowsOf(rows)[0] || {};
  return Object.keys(sample)
    .filter((key) => !['id', 'pesantren_id', 'created_at', 'updated_at', 'deleted_at', 'metadata', 'profile'].includes(key))
    .slice(0, 8)
    .map((key) => parseField(relationSelectTypes[key] ? [key, relationSelectTypes[key]] : key));
}

function defaultFormData(meta, shared = {}) {
  const suffix = Date.now().toString().slice(-6);
  return Object.fromEntries(meta.fields.map((field) => [field.name, defaultValue(field, meta, suffix, shared)]));
}

function defaultValue(field, meta, suffix, shared = {}) {
  if (field.type?.startsWith('select')) {
    const first = optionsFor(field, shared)[0];
    if (first) return first.value;
  }
  if (field.type === 'number') return field.name.includes('score') ? 80 : field.name.includes('amount') || field.name.includes('balance') || field.name.includes('price') ? 100000 : 0;
  if (field.type === 'boolean') return false;
  if (field.type === 'date') return new Date().toISOString().slice(0, 10);
  if (field.type === 'datetime-local') return new Date().toISOString().slice(0, 16);
  if (field.name === 'email') return `demo.${suffix}@pesantenku.test`;
  if (field.name === 'username') return `demo_${suffix}`;
  if (field.name === 'password') return 'password';
  if (field.name === 'code') return `${meta.resource?.slice(0, 3).toUpperCase() || 'KD'}-${suffix}`;
  if (field.name === 'invoice_number') return `INV/WEB/${suffix}`;
  if (field.name === 'number') return `NO/WEB/${suffix}`;
  if (field.name === 'nis') return `WEB${suffix}`;
  if (field.name === 'period') return 'Juli 2026';
  if (field.name === 'semester') return 'Genap 2026/2027';
  if (field.name === 'status') return 'active';
  if (field.name === 'type') return 'general';
  if (field.name.includes('_id')) return 1;
  if (field.type === 'textarea') return '';
  return `${titleFromSlug(field.name)} ${suffix}`;
}

function formDataFromRow(row, meta) {
  return Object.fromEntries(meta.fields.map((field) => {
    const value = row[field.name];
    if (field.type === 'datetime-local' && value) return [field.name, String(value).replace(' ', 'T').slice(0, 16)];
    if (field.type === 'date' && value) return [field.name, String(value).slice(0, 10)];
    if (field.type === 'boolean') return [field.name, Boolean(value)];
    if (field.name === 'password') return [field.name, ''];
    return [field.name, value ?? ''];
  }));
}

function normalizePayload(formData, fields) {
  return Object.fromEntries(fields.map((field) => {
    let value = formData[field.name];
    if (field.type === 'number') value = value === '' || value === null ? null : Number(value);
    if (field.type === 'boolean') value = Boolean(value);
    if (String(field.type).startsWith('select') && value === '') value = null;
    if (field.name === 'password' && !value) value = undefined;
    return [field.name, value];
  }).filter(([, value]) => value !== undefined));
}

function optionsFor(field, shared = {}) {
  const optionType = field.type?.split(':')[1];
  const list = {
    academicYears: rowsOf(shared.academicYears).map((item) => ({ value: item.id, label: item.name })),
    clinics: rowsOf(shared.clinics).map((item) => ({ value: item.id, label: item.name })),
    doctors: rowsOf(shared.doctors).map((item) => ({ value: item.id, label: item.name })),
    expenseCategories: rowsOf(shared.expenseCategories).map((item) => ({ value: item.id, label: item.name })),
    guardians: rowsOf(shared.guardians).map((item) => ({ value: item.id, label: item.name })),
    medicalRecords: rowsOf(shared.medicalRecords).map((item) => ({ value: item.id, label: `${item.date || item.id} - ${item.complaint || item.diagnosis || 'Rekam Medis'}` })),
    medicines: rowsOf(shared.medicines).map((item) => ({ value: item.id, label: item.name })),
    pesantrens: rowsOf(shared.pesantrens).map((item) => ({ value: item.id, label: item.nama_pesantren || item.name })),
    plans: rowsOf(shared.plans).map((item) => ({ value: item.id, label: item.name })),
    programs: rowsOf(shared.programs).map((item) => ({ value: item.id, label: item.name })),
    users: rowsOf(shared.users).map((item) => ({ value: item.id, label: item.name || item.email })),
    roles: rowsOf(shared.roles).map((item) => ({ value: item.id, label: item.label || item.name })),
    rooms: rowsOf(shared.rooms).map((item) => ({ value: item.id, label: item.name })),
    students: rowsOf(shared.students).map((item) => ({ value: item.id, label: item.name })),
    classes: rowsOf(shared.classes).map((item) => ({ value: item.id, label: item.name })),
    subjects: rowsOf(shared.subjects).map((item) => ({ value: item.id, label: item.name })),
    teachers: rowsOf(shared.teachers).map((item) => ({ value: item.id, label: item.name })),
    invoices: rowsOf(shared.invoices).map((item) => ({ value: item.id, label: item.invoice_number || item.id })),
    paymentMethods: rowsOf(shared.paymentMethods).map((item) => ({ value: item.id, label: item.name })),
    gender: [{ value: 'Laki-laki', label: 'Laki-laki' }, { value: 'Perempuan', label: 'Perempuan' }],
    status: statusOptions.map((item) => ({ value: item, label: item })),
    cashflowType: [{ value: 'pemasukan', label: 'Pemasukan' }, { value: 'pengeluaran', label: 'Pengeluaran' }],
    letterType: [{ value: 'incoming', label: 'Surat Masuk' }, { value: 'outgoing', label: 'Surat Keluar' }],
    stockType: [{ value: 'in', label: 'Masuk' }, { value: 'out', label: 'Keluar' }, { value: 'adjustment', label: 'Penyesuaian' }],
  };
  return list[optionType] || list.status;
}

function pickRows(module, shared, panel) {
  const platformRows = {
    pesantrens: shared.pesantrens,
    owner: shared.memberships,
    subscriptions: shared.subscriptions,
    plans: shared.plans,
    users: shared.users,
    modules: shared.modules,
    'integrasi-api': shared.modules,
    'laporan-penggunaan': shared.modules,
    'laporan-pesantren': shared.pesantrens,
    'laporan-keuangan': shared.subscriptions,
    'system-update': shared.backups,
    'cache-management': shared.settings,
    settings: shared.settings,
    backups: shared.backups,
  };

  const ownerRows = {
    'profil-pesantren': shared.pesantrens,
    'user-dan-role': shared.memberships,
    'modul-aktif': shared.modules,
    'paket-dan-subscription': shared.subscriptions,
    'audit-tenant': shared.auditLogs,
    'backup-dan-restore': shared.backups,
    pengaturan: shared.settings,
  };

  const financeRows = {
    santri: shared.students,
    'wali-santri': shared.guardians,
    'program-dan-unit': shared.programs,
    'komponen-biaya': shared.invoiceItems,
    'rekening-bank': shared.bankAccounts,
    'tahun-ajaran': shared.academicYears,
    tagihan: shared.invoices,
    pembayaran: shared.payments,
    'riwayat-pembayaran': shared.payments,
    tunggakan: shared.arrears,
    'diskon-dan-keringanan': shared.discounts,
    'pembayaran-massal': shared.invoices,
    'laporan-pemasukan': shared.cashflows,
    'laporan-pengeluaran': shared.expenses,
    'laporan-arus-kas': shared.cashflows,
    'laporan-tunggakan': shared.arrears,
    'laporan-rekap-bulanan': shared.cashflows,
    'laporan-per-santri': shared.students,
    'pengaturan-umum': shared.settings,
    'pengguna-dan-akses': shared.memberships,
  };

  const academicRows = {
    'data-master': shared.programs,
    kurikulum: shared.subjects,
    kelas: shared.classes,
    'mata-pelajaran': shared.subjects,
    guru: shared.teachers,
    'jadwal-pelajaran': shared.schedules,
    presensi: shared.attendances,
    penilaian: shared.grades,
    'nilai-siswa': shared.grades,
    rapor: shared.reportCards,
    'kalender-akademik': shared.announcements,
    kelulusan: shared.students,
    'laporan-akademik': shared.classes,
    'laporan-nilai': shared.grades,
    pengaturan: shared.settings,
  };

  const tuRows = {
    santri: shared.students,
    'guru-dan-staf': shared.staff,
    kelas: shared.classes,
    kamar: shared.rooms,
    'data-master': shared.programs,
    'surat-menyurat': shared.letters,
    'mutasi-santri': shared.roomMutations,
    alumni: shared.alumni,
    pengumuman: shared.announcements,
    pengaturan: shared.settings,
  };

  const maps = {
    'super-admin': platformRows,
    owner: ownerRows,
    keuangan: financeRows,
    akademik: academicRows,
    tu: tuRows,
  };

  return maps[panel]?.[module] || [];
}

function moduleResource(module, panel) {
  const platform = {
    pesantrens: 'pesantrens',
    owner: 'memberships',
    subscriptions: 'subscriptions',
    plans: 'plans',
    users: 'users',
    modules: 'tenant-modules',
    'integrasi-api': 'system-settings',
    'laporan-penggunaan': 'tenant-modules',
    'laporan-pesantren': 'pesantrens',
    'laporan-keuangan': 'subscriptions',
    backups: 'backups',
    'system-update': 'backups',
    'cache-management': 'system-settings',
    settings: 'system-settings',
  };
  const owner = {
    'profil-pesantren': 'pesantrens',
    'user-dan-role': 'memberships',
    'modul-aktif': 'tenant-modules',
    'paket-dan-subscription': 'subscriptions',
    'backup-dan-restore': 'backups',
    pengaturan: 'system-settings',
  };
  const finance = {
    santri: 'students',
    'wali-santri': 'guardians',
    'program-dan-unit': 'programs',
    'komponen-biaya': 'invoice-items',
    'rekening-bank': 'bank-accounts',
    'tahun-ajaran': 'academic-years',
    tagihan: 'invoices',
    pembayaran: 'payments',
    'riwayat-pembayaran': 'payments',
    tunggakan: 'invoices',
    'diskon-dan-keringanan': 'discounts',
    'pembayaran-massal': 'invoices',
    'laporan-pemasukan': 'cashflow-entries',
    'laporan-pengeluaran': 'expenses',
    'laporan-arus-kas': 'cashflow-entries',
    'laporan-tunggakan': 'invoices',
    'laporan-rekap-bulanan': 'cashflow-entries',
    'laporan-per-santri': 'students',
    'pengaturan-umum': 'system-settings',
    'pengguna-dan-akses': 'memberships',
  };
  const academic = {
    'data-master': 'programs',
    kurikulum: 'subjects',
    kelas: 'classes',
    'mata-pelajaran': 'subjects',
    guru: 'teachers',
    'jadwal-pelajaran': 'schedules',
    presensi: 'attendances',
    penilaian: 'grades',
    'nilai-siswa': 'grades',
    rapor: 'report-cards',
    'kalender-akademik': 'announcements',
    kelulusan: 'students',
    'laporan-akademik': 'classes',
    'laporan-nilai': 'grades',
    pengaturan: 'system-settings',
  };
  const tu = {
    santri: 'students',
    'guru-dan-staf': 'staff',
    kelas: 'classes',
    kamar: 'rooms',
    'data-master': 'programs',
    'surat-menyurat': 'letters',
    'mutasi-santri': 'room-mutations',
    alumni: 'alumni',
    pengumuman: 'announcements',
    pengaturan: 'system-settings',
  };
  return { 'super-admin': platform, owner, keuangan: finance, akademik: academic, tu }[panel]?.[module] || null;
}

function moduleMeta(module, shared, panel) {
  const title = titleFromSlug(module);
  const systemStats = [
    { label: 'Event Hari Ini', value: '128', trend: '24 event kritikal dipantau', icon: 'Activity', tone: 'info' },
    { label: 'Login Berhasil', value: '96', trend: '4 role aktif hari ini', icon: 'Shield', tone: 'success' },
    { label: 'Peringatan', value: '7', trend: 'Perlu review admin', icon: 'TriangleAlert', tone: 'warning' },
    { label: 'Error Kritis', value: '0', trend: 'Tidak ada insiden aktif', icon: 'CircleCheck', tone: 'success' },
  ];

  if (module === 'aktivitas-sistem') {
    return {
      kind: 'system',
      title: 'Aktivitas Sistem',
      subtitle: 'Pantau aktivitas terbaru, keamanan login, backup, update, dan event penting sistem.',
      tableTitle: 'Aktivitas Sistem Terbaru',
      searchPlaceholder: 'Cari aktivitas, modul, pengguna, atau IP...',
      rows: shared.systemActivities,
      columns: ['waktu', 'aktivitas', 'modul', 'pengguna', 'ip', 'status'],
      stats: systemStats,
    };
  }

  if (module === 'audit-logs' || module === 'audit-tenant') {
    return {
      kind: 'system',
      title: module === 'audit-tenant' ? 'Audit Tenant' : 'Log Audit',
      subtitle: module === 'audit-tenant' ? 'Jejak aktivitas penting pada pesantren aktif.' : 'Jejak perubahan data penting untuk kebutuhan keamanan dan kepatuhan operasional.',
      tableTitle: 'Daftar Log Audit',
      searchPlaceholder: 'Cari aktivitas audit, modul, pengguna, atau IP...',
      rows: shared.auditLogs,
      columns: ['waktu', 'aktivitas', 'modul', 'pengguna', 'ip'],
      stats: systemStats,
      audit: true,
    };
  }

  if (module === 'login-monitoring') {
    return {
      kind: 'system',
      title: 'Login Monitoring',
      subtitle: 'Pantau percobaan login, perangkat, alamat IP, dan akses yang diblokir.',
      tableTitle: 'Riwayat Login',
      searchPlaceholder: 'Cari pengguna, role, IP, atau perangkat...',
      rows: shared.loginMonitoring,
      columns: ['waktu', 'pengguna', 'role', 'ip', 'perangkat', 'status'],
      stats: systemStats,
    };
  }

  if (module === 'error-logs') {
    return {
      kind: 'system',
      title: 'Error Log',
      subtitle: 'Pantau error aplikasi, warning integrasi, dan status penanganannya.',
      tableTitle: 'Daftar Error Log',
      searchPlaceholder: 'Cari level, modul, pesan error, atau status...',
      rows: shared.errorLogs,
      columns: ['waktu', 'level', 'modul', 'pesan', 'status'],
      stats: systemStats,
    };
  }

  const resource = moduleResource(module, panel);
  const rows = pickRows(module, shared, panel);
  const readOnly = readOnlyCrudModule(panel, module);
  const createDisabled = createDisabledCrudModule(panel, module);

  return {
    kind: 'crud',
    title,
    subtitle: readOnly ? reportSubtitle(panel, title) : moduleSubtitle(panel, title),
    rows,
    resource,
    panel,
    fields: fieldsFor(resource, rows),
    canCreate: !readOnly && !createDisabled,
    canEdit: !readOnly,
    canDelete: !readOnly,
    canView: true,
    showInsights: !readOnly,
  };
}

function createDisabledCrudModule(panel, module) {
  const maps = {
    owner: new Set(['profil-pesantren']),
  };

  return maps[panel]?.has(module) || false;
}

function readOnlyCrudModule(panel, module) {
  const maps = {
    'super-admin': new Set(['laporan-penggunaan', 'laporan-pesantren', 'laporan-keuangan']),
    owner: new Set(['paket-dan-subscription', 'backup-dan-restore']),
    keuangan: new Set(['riwayat-pembayaran', 'tunggakan', 'laporan-pemasukan', 'laporan-pengeluaran', 'laporan-arus-kas', 'laporan-tunggakan', 'laporan-rekap-bulanan', 'laporan-per-santri']),
    akademik: new Set(['kelulusan', 'laporan-akademik', 'laporan-nilai']),
    tu: new Set([]),
  };

  return maps[panel]?.has(module) || false;
}

function moduleSubtitle(panel, title) {
  if (panel === 'super-admin') return `Kelola ${title.toLowerCase()} untuk platform SaaS Pesantenku.`;
  if (panel === 'owner') return `Kelola ${title.toLowerCase()} untuk tenant pesantren aktif.`;
  if (panel === 'keuangan') return `Kelola ${title.toLowerCase()} keuangan pesantren aktif.`;
  if (panel === 'akademik') return `Kelola ${title.toLowerCase()} akademik pesantren aktif.`;
  return `Kelola ${title.toLowerCase()} tata usaha pesantren aktif.`;
}

function reportSubtitle(panel, title) {
  if (panel === 'super-admin') return `Pantau dan export ${title.toLowerCase()} untuk kebutuhan monitoring platform SaaS.`;
  if (panel === 'owner' && title === 'Backup & Restore') return 'Buat, unduh, dan pulihkan snapshot data tenant dengan jejak audit.';
  if (panel === 'owner') return `Pantau ${title.toLowerCase()} tenant tanpa mengubah data langganan platform.`;
  return `Pantau dan export ${title.toLowerCase()} pesantren aktif.`;
}

function iconFor(name) {
  if (name.includes('Dashboard')) return 'LayoutDashboard';
  if (name.includes('Owner')) return 'Crown';
  if (name.includes('Santri') || name.includes('User') || name.includes('Guru') || name.includes('Pengguna')) return 'Users';
  if (name.includes('Kelas') || name.includes('Pesantren')) return 'School';
  if (name.includes('Kamar')) return 'Bed';
  if (name.includes('Surat') || name.includes('Laporan')) return 'FileText';
  if (name.includes('Pembayaran') || name.includes('Tagihan') || name.includes('Keuangan')) return 'Wallet';
  if (name.includes('Modul') || name.includes('Menu')) return 'Menu';
  if (name.includes('Paket') || name.includes('Subscription')) return 'BadgeCheck';
  if (name.includes('Audit')) return 'Shield';
  return 'Settings';
}

function slug(value) {
  return value.toLowerCase().replaceAll('&', 'dan').replaceAll(' ', '-');
}

function titleFromSlug(value = '') {
  const labels = {
    pesantrens: 'Pesantren',
    owner: 'Owner',
    subscriptions: 'Subscription',
    plans: 'Paket',
    users: 'User & Role',
    modules: 'Menu & Modul',
    'integrasi-api': 'Integrasi & API',
    'aktivitas-sistem': 'Aktivitas Sistem',
    'audit-logs': 'Log Audit',
    'audit-tenant': 'Audit Tenant',
    'login-monitoring': 'Login Monitoring',
    'error-logs': 'Error Log',
    'laporan-penggunaan': 'Laporan Penggunaan',
    'laporan-pesantren': 'Laporan Pesantren',
    'laporan-keuangan': 'Laporan Keuangan',
    backups: 'Backup & Restore',
    'system-update': 'System Update',
    'cache-management': 'Cache Management',
    settings: 'Pengaturan Sistem',
    'profil-pesantren': 'Profil Pesantren',
    'user-dan-role': 'User & Role',
    'modul-aktif': 'Modul Aktif',
    'paket-dan-subscription': 'Paket & Subscription',
    'backup-dan-restore': 'Backup & Restore',
    'wali-santri': 'Wali Santri',
    'program-dan-unit': 'Program & Unit',
    'komponen-biaya': 'Komponen Biaya',
    'rekening-bank': 'Rekening Bank',
    'tahun-ajaran': 'Tahun Ajaran',
    'riwayat-pembayaran': 'Riwayat Pembayaran',
    'diskon-dan-keringanan': 'Diskon & Keringanan',
    'pembayaran-massal': 'Pembayaran Massal',
    'laporan-pemasukan': 'Laporan Pemasukan',
    'laporan-pengeluaran': 'Laporan Pengeluaran',
    'laporan-arus-kas': 'Laporan Arus Kas',
    'laporan-tunggakan': 'Laporan Tunggakan',
    'laporan-rekap-bulanan': 'Laporan Rekap Bulanan',
    'laporan-per-santri': 'Laporan per Santri',
    'pengaturan-umum': 'Pengaturan Umum',
    'pengguna-dan-akses': 'Pengguna & Akses',
    'data-master': 'Data Master',
    'mata-pelajaran': 'Mata Pelajaran',
    'jadwal-pelajaran': 'Jadwal Pelajaran',
    'nilai-siswa': 'Nilai Siswa',
    'kalender-akademik': 'Kalender Akademik',
    'laporan-akademik': 'Laporan Akademik',
    'laporan-nilai': 'Laporan Nilai',
    'guru-dan-staf': 'Guru & Staf',
    'surat-menyurat': 'Surat Menyurat',
    'mutasi-santri': 'Mutasi Santri',
    raport: 'Raport',
    tahfidz: 'Tahfidz',
    billing: 'Billing',
    'qr-scanner': 'QR Scanner',
    'anak-saya': 'Anak Saya',
    'jadwal-anak': 'Jadwal Anak',
    'jadwal-mengajar': 'Jadwal Mengajar',
    'kelas-saya': 'Kelas Saya',
    'presensi-kelas': 'Presensi Kelas',
    'catatan-siswa': 'Catatan Siswa',
    'santri-binaan': 'Santri Binaan',
    'absensi-asrama': 'Absensi Asrama',
    'catatan-harian': 'Catatan Harian',
    'mutasi-kamar': 'Mutasi Kamar',
    'jadwal-piket': 'Jadwal Piket',
    antrian: 'Antrian',
    'rekam-medis': 'Rekam Medis',
    pemeriksaan: 'Pemeriksaan',
    obat: 'Obat',
    'riwayat-berobat': 'Riwayat Berobat',
    'surat-sakit': 'Surat Sakit',
    rujukan: 'Rujukan',
    'jadwal-dokter': 'Jadwal Dokter',
    'stok-obat': 'Stok Obat',
    'laporan-kesehatan': 'Laporan Kesehatan',
  };
  if (labels[value]) return labels[value];

  return String(value).replaceAll('-', ' ').replaceAll('_', ' ').replace(/\b\w/g, (m) => m.toUpperCase());
}

function roleLabel(panel) {
  return panels[panel]?.role || 'Admin';
}

function rupiah(value) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
}
