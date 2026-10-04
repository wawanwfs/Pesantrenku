const pendingIdempotencyKeys = new Map();
let openDialogCount = 0;

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
      ['Aktivitas Sistem', 'Activity', '/super-admin/module/aktivitas-sistem'],
      ['Log Audit', 'FileText', '/super-admin/module/audit-logs'],
      ['Login Monitoring', 'Shield', '/super-admin/module/login-monitoring'],
      ['Error Log', 'TriangleAlert', '/super-admin/module/error-logs'],
      ['Laporan Penggunaan', 'BarChart3', '/super-admin/module/laporan-penggunaan'],
      ['Laporan Pesantren', 'FileText', '/super-admin/module/laporan-pesantren'],
      ['Laporan Keuangan', 'Wallet', '/super-admin/module/laporan-keuangan'],
      ['Backup & Restore', 'Database', '/super-admin/module/backups'],
      ['Pengaturan Sistem', 'Settings', '/super-admin/module/settings'],
    ],
  },
  owner: {
    role: 'Owner Pesantren',
    menu: ['Dashboard', 'Profil Pesantren', 'User & Role', 'Modul Aktif', 'Paket & Subscription', 'Audit Tenant', 'Backup & Restore', 'Pengaturan'].map((name) => [name, iconFor(name), `/owner/module/${slug(name)}`]),
  },
  keuangan: {
    role: 'Keuangan',
    menu: ['Dashboard', 'Santri', 'Wali Santri', 'Program & Unit', 'Komponen Biaya', 'Rekening Bank', 'Tahun Ajaran', 'Tagihan', 'Pembayaran', 'Riwayat Pembayaran', 'Tunggakan', 'Diskon & Keringanan', 'Laporan Pemasukan', 'Laporan Pengeluaran', 'Laporan Arus Kas', 'Laporan Tunggakan', 'Laporan Rekap Bulanan', 'Laporan per Santri', 'Pengaturan Umum', 'Pengguna & Akses'].map((name) => [name, iconFor(name), `/keuangan/module/${slug(name)}`]),
  },
  akademik: {
    role: 'Akademik',
    menu: ['Dashboard', 'Data Master', 'Kurikulum', 'Kelas', 'Mata Pelajaran', 'Guru', 'Jadwal Pelajaran', 'Presensi', 'Penilaian', 'Nilai Siswa', 'Rapor', 'Kelulusan', 'Pengumuman', 'Laporan Akademik', 'Laporan Nilai', 'Pengaturan'].map((name) => [name, iconFor(name), `/akademik/module/${slug(name)}`]),
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

const statusTexts = {
  active: 'Aktif', inactive: 'Tidak Aktif', pending: 'Menunggu', paid: 'Lunas', unpaid: 'Belum Lunas', success: 'Berhasil',
  waiting: 'Menunggu', draft: 'Draf', published: 'Terbit', done: 'Selesai', approved: 'Disetujui', rejected: 'Ditolak',
  cancelled: 'Dibatalkan', in_progress: 'Diproses',
};

function statusText(value) {
  return statusTexts[String(value ?? '').toLowerCase()] || titleFromSlug(String(value ?? ''));
}

const statusOptions = ['active', 'inactive', 'pending', 'paid', 'unpaid', 'success', 'waiting', 'draft', 'published', 'done', 'approved', 'rejected', 'cancelled', 'in_progress'];

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

function requestStepUpMfa(action = 'melakukan perubahan keamanan') {
  const factor = window.prompt(`Masukkan kode MFA atau recovery untuk ${action}.`);
  if (!factor || !factor.trim()) throw new Error('Perubahan dibatalkan karena kode MFA tidak diberikan.');
  return factor.trim().toUpperCase();
}

function rowsOf(rows) {
  return Array.isArray(rows) ? rows : Object.values(rows || {});
}

function clampPercent(value) {
  const numeric = Number(value);
  return Number.isFinite(numeric) ? Math.min(100, Math.max(0, Math.round(numeric))) : 0;
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
  const mutating = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method);
  const requestSignature = `${method}:${url}:${String(options.body || '')}`;
  const idempotencyKey = mutating ? mutationIdempotencyKey(requestSignature) : null;
  try {
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
    const json = parseJsonResponse(text, response.status);
    if (!response.ok) {
      const error = new Error(json.message || json.error || `Request gagal (${response.status})`);
      error.errors = json.errors || {};
      throw error;
    }
    pendingIdempotencyKeys.delete(requestSignature);
    return json;
  } catch (error) {
    // Pertahankan key pada kegagalan jaringan agar retry eksplisit tidak menggandakan mutasi.
    if (error instanceof SyntaxError) pendingIdempotencyKeys.delete(requestSignature);
    throw error;
  }
}

async function apiUpload(url, file, fields = {}) {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  const formData = new FormData();
  formData.append('file', file);
  Object.entries(fields).forEach(([key, value]) => formData.append(key, value));
  const signature = `POST:${url}:${file.name}:${file.size}:${file.lastModified}:${JSON.stringify(fields)}`;
  const idempotencyKey = mutationIdempotencyKey(signature);
  const response = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
      'Idempotency-Key': idempotencyKey,
    },
    body: formData,
  });
  const text = await response.text();
  const json = parseJsonResponse(text, response.status);
  if (!response.ok) {
    const error = new Error(json.message || json.error || `Upload gagal (${response.status})`);
    error.errors = json.errors || {};
    throw error;
  }
  pendingIdempotencyKeys.delete(signature);
  return json;
}

function mutationIdempotencyKey(signature) {
  const existing = pendingIdempotencyKeys.get(signature);
  if (existing) return existing;
  const generated = globalThis.crypto?.randomUUID?.() || `web-${Date.now()}-${Math.random().toString(16).slice(2)}`;
  pendingIdempotencyKeys.set(signature, generated);
  return generated;
}

function parseJsonResponse(text, status) {
  if (!text) return {};
  try {
    return JSON.parse(text);
  } catch {
    throw new SyntaxError(`Server mengembalikan respons non-JSON (${status}).`);
  }
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
  const csvCell = (value) => {
    const raw = String(value ?? '');
    const safe = /^[=+\-@\t\r]/.test(raw) ? `'${raw}` : raw;
    return `"${safe.replaceAll('"', '""')}"`;
  };
  const csv = [
    columns.map(csvCell).join(','),
    ...normalized.map((row) => columns.map((column) => csvCell(row[column])).join(',')),
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

function defaultFormData(meta) {
  return Object.fromEntries(meta.fields.map((field) => [field.name, defaultValue(field, meta.resource)]));
}

function defaultValue(field, resource) {
  if (field.type === 'select:status' && ['permits', 'payments', 'medical-queues'].includes(resource)) return 'pending';
  if (field.type === 'select:status') return 'active';
  if (field.type?.startsWith('select')) return '';
  if (field.type === 'boolean') return false;
  return '';
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
    status: statusOptions.map((item) => ({ value: item, label: statusText(item) })),
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
    'laporan-penggunaan': shared.modules,
    'laporan-pesantren': shared.pesantrens,
    'laporan-keuangan': shared.subscriptions,
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
    kelulusan: shared.students,
    pengumuman: shared.announcements,
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
    'laporan-penggunaan': 'tenant-modules',
    'laporan-pesantren': 'pesantrens',
    'laporan-keuangan': 'subscriptions',
    backups: 'backups',
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
    kelulusan: 'students',
    pengumuman: 'announcements',
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
  const activities = rowsOf(shared.systemActivities);
  const logins = rowsOf(shared.loginMonitoring);
  const errors = rowsOf(shared.errorLogs);
  const warnings = activities.filter((row) => /peringatan|warning|blocked|diblokir/i.test(String(row.status || row.aktivitas || '')));
  const systemStats = [
    { label: 'Event Tercatat', value: String(activities.length), trend: 'Data terbaru dari audit sistem', icon: 'Activity', tone: 'info' },
    { label: 'Aktivitas Login', value: String(logins.length), trend: 'Riwayat autentikasi tersedia', icon: 'Shield', tone: 'success' },
    { label: 'Peringatan', value: String(warnings.length), trend: 'Event yang memerlukan review', icon: 'TriangleAlert', tone: 'warning' },
    { label: 'Error Tercatat', value: String(errors.length), trend: errors.length ? 'Perlu tindak lanjut admin' : 'Tidak ada error pada data terbaru', icon: errors.length ? 'TriangleAlert' : 'CircleCheck', tone: errors.length ? 'error' : 'success' },
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
    createLabel: `Tambah ${title}`,
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
    'super-admin': new Set(['subscriptions', 'plans', 'backups', 'laporan-penggunaan', 'laporan-pesantren', 'laporan-keuangan']),
    owner: new Set(['paket-dan-subscription', 'backup-dan-restore']),
    keuangan: new Set(['riwayat-pembayaran', 'tunggakan', 'laporan-pemasukan', 'laporan-pengeluaran', 'laporan-arus-kas', 'laporan-tunggakan', 'laporan-rekap-bulanan', 'laporan-per-santri', 'pengguna-dan-akses']),
    akademik: new Set(['kelulusan', 'laporan-akademik', 'laporan-nilai']),
    tu: new Set([]),
  };

  return maps[panel]?.has(module) || false;
}

function moduleSubtitle(panel, title) {
  if (panel === 'super-admin') return `Kelola ${title.toLowerCase()} untuk platform SaaS Pesantrenku.`;
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
  const rules = [
    ['Dashboard', 'LayoutDashboard'], ['Owner', 'Crown'], ['Pengaturan', 'Settings'], ['Profil', 'UserCog'],
    ['Laporan', 'BarChart3'], ['Presensi', 'CheckSquare'], ['Absensi', 'CheckSquare'], ['Catatan', 'FileText'], ['Jadwal', 'CalendarDays'],
    ['Mutasi', 'MoveHorizontal'], ['Rekam Medis', 'HeartPulse'], ['Riwayat', 'FileText'], ['Surat', 'FileText'],
    ['Wali', 'Users'], ['Santri', 'Users'], ['User', 'Users'], ['Guru', 'GraduationCap'], ['Pengguna', 'Users'], ['Anak', 'Users'],
    ['Kelas', 'School'], ['Pesantren', 'School'], ['Program', 'School'], ['Kurikulum', 'BookOpen'], ['Mata Pelajaran', 'BookOpen'],
    ['Materi', 'BookOpen'], ['Tugas', 'ListChecks'], ['Kamar', 'Bed'], ['Asrama', 'Bed'], ['Piket', 'CheckSquare'],
    ['Jadwal', 'CalendarDays'], ['Kalender', 'CalendarDays'], ['Presensi', 'CheckSquare'], ['Absensi', 'CheckSquare'],
    ['Nilai', 'BarChart3'], ['Penilaian', 'BarChart3'], ['Rapor', 'FileText'], ['Raport', 'FileText'], ['Kelulusan', 'GraduationCap'],
    ['Tahfidz', 'BookOpen'], ['Akademik', 'GraduationCap'], ['Tahun Ajaran', 'CalendarDays'],
    ['Pelanggaran', 'TriangleAlert'], ['Perizinan', 'Send'], ['Mutasi', 'MoveHorizontal'], ['Alumni', 'GraduationCap'],
    ['Pengumuman', 'Inbox'], ['Notifikasi', 'Bell'], ['QR', 'ScanLine'],
    ['Antrian', 'ListChecks'], ['Rekam Medis', 'HeartPulse'], ['Pemeriksaan', 'HeartPulse'], ['Kesehatan', 'HeartPulse'],
    ['Berobat', 'HeartPulse'], ['Obat', 'HeartPulse'], ['Rujukan', 'Send'], ['Dokter', 'HeartPulse'],
    ['Surat', 'FileText'], ['Laporan', 'BarChart3'], ['Log', 'FileText'], ['Error', 'TriangleAlert'], ['Aktivitas', 'Activity'],
    ['Login', 'Shield'], ['Audit', 'Shield'], ['Backup', 'Database'], ['Integrasi', 'Cable'], ['Cache', 'HardDrive'],
    ['Pembayaran', 'CreditCard'], ['Tagihan', 'Wallet'], ['Tunggakan', 'TriangleAlert'], ['Diskon', 'BadgeCheck'],
    ['Rekening', 'Banknote'], ['Biaya', 'Wallet'], ['Billing', 'Wallet'], ['Keuangan', 'Wallet'],
    ['Paket', 'BadgeCheck'], ['Subscription', 'BadgeCheck'], ['Modul', 'Menu'], ['Menu', 'Menu'], ['Data Master', 'Database'],
  ];
  const match = rules.find(([needle]) => name.includes(needle));
  return match ? match[1] : 'Settings';
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
    'aktivitas-sistem': 'Aktivitas Sistem',
    'audit-logs': 'Log Audit',
    'audit-tenant': 'Audit Tenant',
    'login-monitoring': 'Login Monitoring',
    'error-logs': 'Error Log',
    'laporan-penggunaan': 'Laporan Penggunaan',
    'laporan-pesantren': 'Laporan Pesantren',
    'laporan-keuangan': 'Laporan Keuangan',
    backups: 'Backup & Restore',
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

export {
  panels, colors, tone, resourceFields, statusOptions, relationSelectTypes,
  requestStepUpMfa, rowsOf, clampPercent, parseJsonValue, mergeRows, apiJson, apiUpload,
  exportResource, downloadAuthenticatedFile, exportRowsToCsv, fieldsFor, defaultFormData,
  formDataFromRow, normalizePayload, optionsFor, pickRows, moduleResource, moduleMeta, parseField,
  iconFor, slug, titleFromSlug, moduleSubtitle, roleLabel, rupiah,
};
