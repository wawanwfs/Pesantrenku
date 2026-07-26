class MobileModule {
  const MobileModule(this.label, this.resource);
  final String label;
  final String resource;
}

const roleModules = <String, List<MobileModule>>{
  'super_admin': [
    MobileModule('Pesantren', 'pesantrens'),
    MobileModule('Pengguna', 'users'),
    MobileModule('Membership', 'memberships'),
    MobileModule('Subscription', 'subscriptions'),
    MobileModule('Paket', 'plans'),
    MobileModule('Backup', 'backups'),
  ],
  'owner_pesantren': [
    MobileModule('Santri', 'students'),
    MobileModule('Pengguna Tenant', 'memberships'),
    MobileModule('Modul Aktif', 'tenant-modules'),
    MobileModule('Tagihan', 'invoices'),
    MobileModule('Pembayaran', 'payments'),
    MobileModule('Audit', 'audit-logs'),
  ],
  'admin_akademik': [
    MobileModule('Tahun Ajaran', 'academic-years'),
    MobileModule('Kelas', 'classes'),
    MobileModule('Mata Pelajaran', 'subjects'),
    MobileModule('Jadwal', 'schedules'),
    MobileModule('Presensi', 'attendances'),
    MobileModule('Nilai', 'grades'),
  ],
  'admin_tu': [
    MobileModule('Santri', 'students'),
    MobileModule('Guru', 'teachers'),
    MobileModule('Staf', 'staff'),
    MobileModule('Kamar', 'rooms'),
    MobileModule('Surat', 'letters'),
    MobileModule('Alumni', 'alumni'),
  ],
  'santri': [
    MobileModule('Nilai', 'grades'),
    MobileModule('Raport', 'report-cards'),
    MobileModule('Hafalan', 'memorization-records'),
    MobileModule('Presensi', 'attendances'),
    MobileModule('Perizinan', 'permits'),
    MobileModule('Tagihan', 'invoices'),
    MobileModule('Pembayaran', 'payments'),
    MobileModule('Pengumuman', 'announcements'),
  ],
  'wali_murid': [
    MobileModule('Anak Saya', 'students'),
    MobileModule('Nilai', 'grades'),
    MobileModule('Raport', 'report-cards'),
    MobileModule('Presensi', 'attendances'),
    MobileModule('Perizinan', 'permits'),
    MobileModule('Tagihan', 'invoices'),
    MobileModule('Pembayaran', 'payments'),
    MobileModule('Kesehatan', 'medical-records'),
  ],
  'guru': [
    MobileModule('Kelas', 'classes'),
    MobileModule('Jadwal', 'schedules'),
    MobileModule('Presensi', 'attendances'),
    MobileModule('Nilai', 'grades'),
    MobileModule('Tugas', 'assignments'),
    MobileModule('Materi', 'materials'),
    MobileModule('Catatan Santri', 'daily-notes'),
  ],
  'musyrif': [
    MobileModule('Santri', 'students'),
    MobileModule('Kamar', 'rooms'),
    MobileModule('Presensi Asrama', 'dorm-attendances'),
    MobileModule('Perizinan', 'permits'),
    MobileModule('Pelanggaran', 'violations'),
    MobileModule('Catatan Harian', 'daily-notes'),
    MobileModule('Mutasi Kamar', 'room-mutations'),
  ],
  'klinik': [
    MobileModule('Antrian', 'medical-queues'),
    MobileModule('Rekam Medis', 'medical-records'),
    MobileModule('Pemeriksaan', 'medical-examinations'),
    MobileModule('Obat', 'medicines'),
    MobileModule('Stok Obat', 'medicine-stocks'),
    MobileModule('Resep', 'prescriptions'),
    MobileModule('Surat Sakit', 'sick-letters'),
    MobileModule('Rujukan', 'referrals'),
  ],
  'admin_keuangan': [
    MobileModule('Tagihan', 'invoices'),
    MobileModule('Pembayaran', 'payments'),
    MobileModule('Metode Pembayaran', 'payment-methods'),
    MobileModule('Pengeluaran', 'expenses'),
    MobileModule('Arus Kas', 'cashflow-entries'),
    MobileModule('Santri', 'students'),
  ],
};

List<MobileModule> modulesFor(String role) => roleModules[role] ?? const [];
