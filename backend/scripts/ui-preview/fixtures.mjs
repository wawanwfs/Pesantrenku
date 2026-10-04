// Data tiruan untuk preview UI tanpa PHP/DB. Bentuknya mengikuti DashboardService dan
// HandleInertiaRequests; nilainya fiktif dan hanya dipakai pengembangan lokal.

const months = ['Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt'];

export const demoAccounts = [
  ['superadmin@pesantenku.test', 'Super Admin'],
  ['owner@pesantenku.test', 'Owner Pesantren'],
  ['keuangan@pesantenku.test', 'Keuangan'],
  ['akademik@pesantenku.test', 'Akademik'],
  ['tu@pesantenku.test', 'Tata Usaha'],
  ['santri@pesantenku.test', 'Santri'],
].map(([email, role]) => ({ email, role }));

export const panelRole = {
  'super-admin': 'super_admin',
  owner: 'owner_pesantren',
  keuangan: 'admin_keuangan',
  akademik: 'admin_akademik',
  tu: 'admin_tu',
  santri: 'santri',
  'wali-murid': 'wali_murid',
  guru: 'guru',
  musyrif: 'musyrif',
  klinik: 'klinik',
};

export function sharedProps(panel, { authenticated = true } = {}) {
  const role = panelRole[panel] || 'super_admin';
  return {
    auth: authenticated
      ? {
          user: { id: 1, name: 'Ahmad Fauzan', email: 'fauzan@darulhikmah.test' },
          roles: [role],
          memberships: [
            { id: 1, name: 'Pondok Pesantren Darul Hikmah', role_label: 'Peran aktif' },
            { id: 2, name: 'Pesantren Al-Falah', role_label: 'Peran aktif' },
          ],
          active_pesantren: { id: 1, name: 'Pondok Pesantren Darul Hikmah' },
          active_role: role,
        }
      : { user: null, roles: [], memberships: [], active_pesantren: null, active_role: null },
    app: {
      name: 'Pesantrenku',
      environment: 'local',
      demo_enabled: true,
      demo_accounts: demoAccounts,
      academic_year: 'Tahun Ajaran 2026/2027',
      notifications: { sse_enabled: false, websocket_enabled: false, polling_seconds: 600 },
    },
    errors: {},
  };
}

const line = months.map((month, index) => ({
  month,
  'Pengguna Baru': 18 + index * 7 + (index % 2) * 5,
  Transaksi: 40 + index * 11 - (index % 3) * 6,
}));
const financeLine = months.map((month, index) => ({
  month,
  Pemasukan: 42_000_000 + index * 5_500_000,
  Pengeluaran: 28_000_000 + index * 2_100_000,
}));

const rupiah = (value) => `Rp ${value.toLocaleString('id-ID')}`;

export const notifications = [
  { id: 1, title: 'Pembayaran diverifikasi', body: 'Pembayaran SPP Oktober atas nama Zaid telah diverifikasi.', type: 'success', read_at: null },
  { id: 2, title: 'Perizinan menunggu', body: 'Izin pulang Salman menunggu persetujuan musyrif.', type: 'warning', read_at: null },
  { id: 3, title: 'Backup selesai', body: 'Snapshot tenant terenkripsi berhasil dibuat.', type: 'info', read_at: null },
];

const activities = [
  { title: 'Login berhasil', description: 'Ahmad Fauzan masuk dari 10.0.0.12', time: '2 menit lalu' },
  { title: 'Pembayaran diverifikasi', description: 'Tagihan INV-2026-0142 lunas', time: '18 menit lalu' },
  { title: 'Santri baru ditambahkan', description: 'NIS 26-0381 Muhammad Zaid', time: '1 jam lalu' },
  { title: 'Backup tenant dibuat', description: 'Snapshot harian terenkripsi', time: '3 jam lalu' },
];

export function dashboardFor(panel) {
  const base = {
    title: 'Dashboard',
    subtitle: 'Ringkasan aktivitas pesantren',
    lineChart: line,
    donut: [
      { name: 'Santri', value: 312 },
      { name: 'Wali Murid', value: 241 },
      { name: 'Guru', value: 38 },
      { name: 'Staf', value: 22 },
    ],
    activities,
    moduleUsage: [
      { name: 'Akademik', value: 92 },
      { name: 'Keuangan', value: 78 },
      { name: 'Asrama', value: 64 },
      { name: 'Klinik', value: 41 },
      { name: 'Tata Usaha', value: 33 },
    ],
  };
  if (panel === 'keuangan') {
    return {
      ...base,
      role: 'admin_keuangan',
      subtitle: 'Ringkasan keuangan pesantren',
      lineChart: financeLine,
      stats: [
        { label: 'Total Pemasukan', value: rupiah(284_500_000), trend: 'Pembayaran sukses', icon: 'Wallet', tone: 'success' },
        { label: 'Total Pengeluaran', value: rupiah(171_200_000), trend: 'Pengeluaran tercatat', icon: 'LogOut', tone: 'error' },
        { label: 'Saldo Kas', value: rupiah(113_300_000), trend: 'Pemasukan dikurangi pengeluaran', icon: 'CreditCard', tone: 'info' },
        { label: 'Tunggakan', value: rupiah(36_400_000), trend: '27 santri menunggak', icon: 'TriangleAlert', tone: 'warning' },
        { label: 'Lunas Bulan Ini', value: '184 Santri', trend: 'Pembayar unik bulan berjalan', icon: 'CircleCheck', tone: 'purple' },
      ],
      quickActions: ['Tambah Tagihan', 'Tambah Pembayaran', 'Laporan Pemasukan', 'Laporan Tunggakan', 'Pengaturan Umum'],
    };
  }
  return {
    ...base,
    role: panelRole[panel] || 'super_admin',
    stats: [
      { label: 'Total Pesantren', value: '12', trend: 'Pesantren terdaftar', icon: 'School', tone: 'info' },
      { label: 'Total User', value: '1.284', trend: 'Akun platform tersimpan', icon: 'Users', tone: 'success' },
      { label: 'Total Santri', value: '3.906', trend: 'Seluruh tenant', icon: 'Box', tone: 'purple' },
      { label: 'Transaksi Bulan Ini', value: '412', trend: 'Pembayaran sukses', icon: 'PieChart', tone: 'warning' },
      { label: 'Pendapatan Bulan Ini', value: rupiah(284_500_000), trend: 'Nilai transaksi sukses', icon: 'Wallet', tone: 'success' },
      { label: 'Status Data', value: 'Sinkron', trend: 'Terhubung ke database aktif', icon: 'Server', tone: 'info' },
    ],
    quickActions: ['Tambah User', 'Modul Aktif', 'Paket & Subscription', 'Audit Tenant', 'Pengaturan'],
  };
}

const names = ['Muhammad Zaid', 'Salman Al-Farisi', 'Abdullah Rasyid', 'Yusuf Hakim', 'Ibrahim Naufal', 'Hamzah Prasetyo', 'Umar Faruq', 'Khalid Mahfud'];

export function studentRows(count = 8) {
  return Array.from({ length: count }, (_, index) => ({
    id: index + 1,
    nis: `26-${String(381 - index).padStart(4, '0')}`,
    name: names[index % names.length],
    gender: 'L',
    birth_place: ['Bandung', 'Garut', 'Bogor', 'Cirebon'][index % 4],
    birth_date: `2012-0${(index % 9) + 1}-1${index % 9}`,
    room: `Asrama A-${(index % 4) + 1}`,
    status: index % 5 === 4 ? 'Nonaktif' : 'Aktif',
  }));
}

export function sharedData(panel) {
  const students = studentRows();
  return {
    notifications,
    students,
    pesantrens: [
      { id: 1, name: 'Pondok Pesantren Darul Hikmah', city: 'Bandung', status: 'Aktif' },
      { id: 2, name: 'Pesantren Al-Falah', city: 'Garut', status: 'Aktif' },
    ],
    memberships: [],
    subscriptions: [],
    invoices: [],
    payments: [],
    arrears: [],
    plans: [],
    roles: [],
    modules: [],
    announcements: [],
    schedules: [],
    classes: [],
    letters: [],
    rooms: [],
    medicalQueues: [],
    users: [],
  };
}
