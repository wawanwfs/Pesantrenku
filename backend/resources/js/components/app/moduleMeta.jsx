import React from 'react';
import { fieldsFor, moduleSubtitle, parseField, roleLabel, rowsOf, titleFromSlug } from './appSupport.jsx';

export function rolePrimaryRows(panel, dashboard, shared) {
  const map = {
    santri: { title: 'Tagihan Terbaru', rows: dashboard.invoices, href: '/santri/module/billing' },
    'wali-murid': { title: 'Data Anak', rows: dashboard.students, href: '/wali-murid/module/anak-saya' },
    guru: { title: 'Jadwal Mengajar', rows: dashboard.schedules, href: '/guru/module/jadwal-mengajar' },
    musyrif: { title: 'Santri Binaan', rows: dashboard.students, href: '/musyrif/module/santri-binaan' },
    klinik: { title: 'Antrian Klinik', rows: shared.medicalQueues, href: '/klinik/module/antrian' },
  };

  return map[panel] || { title: 'Data Terbaru', rows: dashboard.students, href: `/dashboard/${panel}` };
}

export function roleSummaryIcon(label) {
  const lower = label.toLowerCase();
  if (lower.includes('nilai')) return 'BarChart3';
  if (lower.includes('hafalan')) return 'BookOpen';
  if (lower.includes('pelanggaran')) return 'TriangleAlert';
  if (lower.includes('tagihan')) return 'Wallet';
  return 'CircleCheck';
}

export function roleSummaryTone(label) {
  const lower = label.toLowerCase();
  if (lower.includes('pelanggaran')) return 'warning';
  if (lower.includes('tagihan')) return 'info';
  if (lower.includes('hafalan')) return 'purple';
  return 'success';
}

export function tabbedModuleMeta(panel, module, shared) {
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

export function tabMeta(resource, label, rows, panel, overrides = {}) {
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

export function roleActionMeta(panel, module, dashboard, shared) {
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

export function roleActionResource(panel, module) {
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

export function roleActionConfig(panel, module) {
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

export function workflowActionsFor(meta) {
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

export function roleModuleMeta(panel, module, dashboard, shared) {
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

export function profileRows(dashboard) {
  return [
    { informasi: 'Nama', nilai: dashboard.profile?.name || '-' },
    { informasi: 'Keterangan', nilai: dashboard.profile?.subtitle || '-' },
    ...(dashboard.summary || []).map((item) => ({ informasi: item.label, nilai: item.value })),
  ];
}

export function dashboardPreview(panel, shared) {
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
