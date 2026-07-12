<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardService
{
    private array $schemaColumns = [];

    private array $schemaTables = [];

    public function __construct(private readonly TenantContext $tenantContext) {}

    public function for(string $role, ?Request $request = null): array
    {
        return match ($role) {
            'super-admin', 'super_admin' => $this->superAdmin(),
            'owner', 'owner_pesantren' => $this->owner($request),
            'keuangan', 'admin_keuangan' => $this->finance($request),
            'akademik', 'admin_akademik' => $this->academic($request),
            'tu', 'admin_tu' => $this->tu($request),
            'santri' => $this->mobile('santri', $request),
            'wali-murid', 'wali_murid' => $this->mobile('wali_murid', $request),
            'musyrif' => $this->mobile('musyrif', $request),
            'guru' => $this->mobile('guru', $request),
            'klinik' => $this->mobile('klinik', $request),
            default => $this->superAdmin(),
        };
    }

    public function shared(?Request $request = null, ?string $panel = null, ?string $module = null): array
    {
        if ($panel === null) {
            return $this->fullShared($request);
        }

        $keys = $module === null
            ? $this->dashboardSharedKeys($panel)
            : $this->moduleSharedKeys($panel, $module);

        $keys[] = 'notifications';

        return collect(array_unique($keys))
            ->mapWithKeys(fn (string $key) => [$key => $this->sharedDataset($request, $key)])
            ->all();
    }

    private function fullShared(?Request $request = null): array
    {
        $auditLogs = $this->query($request, 'audit_logs')
            ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
            ->select([
                'audit_logs.id',
                'users.name as pengguna',
                'audit_logs.action as aktivitas',
                'audit_logs.module as modul',
                'audit_logs.ip_address as ip',
                'audit_logs.metadata',
                'audit_logs.created_at as waktu',
            ])
            ->latest('audit_logs.id')
            ->limit(10)
            ->get();

        $arrears = $this->query($request, 'invoices')
            ->leftJoin('students', 'invoices.student_id', '=', 'students.id')
            ->where('invoices.status', 'unpaid')
            ->select([
                'invoices.id',
                'invoices.invoice_number as nomor_tagihan',
                'students.name as santri',
                'invoices.period as periode',
                'invoices.amount as nominal',
                'invoices.due_date as jatuh_tempo',
                'invoices.status',
            ])
            ->latest('invoices.id')
            ->limit(10)
            ->get();

        return [
            'users' => $this->query($request, 'users')->select('id', 'name', 'email', 'username', 'phone', 'status')->limit(10)->get(),
            'roles' => DB::table('roles')->select('id', 'name', 'label')->orderBy('label')->get(),
            'memberships' => $this->memberships($request),
            'pesantrens' => $this->pesantrens($request),
            'subscriptions' => $this->query($request, 'subscriptions')->select('id', 'pesantren_id', 'plan_id', 'status', 'starts_at', 'ends_at', 'amount')->latest('id')->limit(10)->get(),
            'plans' => DB::table('plans')->select('id', 'name', 'code', 'monthly_price', 'max_students', 'max_users', 'status')->limit(10)->get(),
            'modules' => $this->modules($request),
            'settings' => $this->query($request, 'system_settings')->select('id', 'key as kunci', 'value as nilai', 'updated_at as diperbarui_pada')->limit(10)->get(),
            'auditLogs' => $auditLogs,
            'systemActivities' => $this->systemActivities(),
            'loginMonitoring' => $this->loginMonitoring(),
            'errorLogs' => $this->errorLogs(),
            'backups' => $this->query($request, 'backups')->select('id', 'name', 'status', 'file_path', 'completed_at')->latest('id')->limit(10)->get(),
            'academicYears' => $this->query($request, 'academic_years')->limit(10)->get(),
            'programs' => $this->query($request, 'programs')->limit(10)->get(),
            'students' => $this->query($request, 'students')->limit(10)->get(),
            'guardians' => $this->query($request, 'guardians')->limit(10)->get(),
            'classes' => $this->query($request, 'classes')->limit(10)->get(),
            'teachers' => $this->query($request, 'teachers')->limit(10)->get(),
            'teacherAssignments' => $this->query($request, 'teacher_assignments')->limit(20)->get(),
            'staff' => $this->query($request, 'staff')->limit(10)->get(),
            'rooms' => $this->query($request, 'rooms')->limit(10)->get(),
            'musyrifRoomAssignments' => $this->query($request, 'musyrif_room_assignments')->limit(20)->get(),
            'subjects' => $this->query($request, 'subjects')->limit(10)->get(),
            'schedules' => $this->query($request, 'schedules')->limit(10)->get(),
            'attendances' => $this->query($request, 'attendances')->limit(10)->get(),
            'dormAttendances' => $this->query($request, 'dorm_attendances')->limit(10)->get(),
            'grades' => $this->query($request, 'grades')->limit(10)->get(),
            'reportCards' => $this->query($request, 'report_cards')->limit(10)->get(),
            'memorizationRecords' => $this->query($request, 'memorization_records')->limit(10)->get(),
            'assignments' => $this->query($request, 'assignments')->limit(10)->get(),
            'materials' => $this->query($request, 'materials')->limit(10)->get(),
            'permits' => $this->query($request, 'permits')->limit(10)->get(),
            'dailyNotes' => $this->query($request, 'daily_notes')->limit(10)->get(),
            'invoices' => $this->query($request, 'invoices')->limit(10)->get(),
            'invoiceItems' => $this->query($request, 'invoice_items')->limit(10)->get(),
            'arrears' => $arrears,
            'payments' => $this->query($request, 'payments')->limit(10)->get(),
            'paymentMethods' => $this->query($request, 'payment_methods')->limit(10)->get(),
            'bankAccounts' => $this->query($request, 'bank_accounts')->limit(10)->get(),
            'expenseCategories' => $this->query($request, 'expense_categories')->limit(10)->get(),
            'cashflows' => $this->query($request, 'cashflow_entries')->limit(10)->get(),
            'expenses' => $this->query($request, 'expenses')->limit(10)->get(),
            'discounts' => $this->query($request, 'discounts')->limit(10)->get(),
            'letters' => $this->query($request, 'letters')->latest('id')->limit(10)->get(),
            'roomMutations' => $this->query($request, 'room_mutations')->latest('id')->limit(10)->get(),
            'alumni' => $this->query($request, 'alumni')->limit(10)->get(),
            'clinics' => $this->query($request, 'clinics')->limit(10)->get(),
            'doctors' => $this->query($request, 'doctors')->limit(10)->get(),
            'medicines' => $this->query($request, 'medicines')->limit(10)->get(),
            'medicalQueues' => $this->query($request, 'medical_queues')->limit(10)->get(),
            'medicalRecords' => $this->query($request, 'medical_records')->limit(10)->get(),
            'medicalExaminations' => $this->query($request, 'medical_examinations')->limit(10)->get(),
            'medicineStocks' => $this->query($request, 'medicine_stocks')->limit(10)->get(),
            'sickLetters' => $this->query($request, 'sick_letters')->limit(10)->get(),
            'referrals' => $this->query($request, 'referrals')->limit(10)->get(),
            'announcements' => $this->query($request, 'announcements')->latest('id')->limit(5)->get(),
            'notifications' => $this->query($request, 'notification_logs')->whereNull('read_at')->latest('id')->limit(5)->get(),
        ];
    }

    private function dashboardSharedKeys(string $panel): array
    {
        return match ($panel) {
            'super-admin' => ['pesantrens', 'memberships', 'subscriptions'],
            'owner' => ['memberships', 'modules', 'subscriptions'],
            'keuangan' => ['invoices', 'payments', 'arrears'],
            'akademik' => ['grades', 'schedules', 'classes'],
            'tu' => ['students', 'letters', 'rooms'],
            'klinik' => ['medicalQueues'],
            default => [],
        };
    }

    private function moduleSharedKeys(string $panel, string $module): array
    {
        $special = match ($module) {
            'aktivitas-sistem' => ['systemActivities'],
            'audit-logs', 'audit-tenant' => ['auditLogs'],
            'login-monitoring' => ['loginMonitoring'],
            'error-logs' => ['errorLogs'],
            default => [],
        };

        if ($special !== []) {
            return $special;
        }

        $resources = $this->moduleResources($panel, $module);
        $keys = [];

        foreach ($resources as $resource) {
            $key = $this->resourceSharedKey($resource);
            if ($key !== null) {
                $keys[] = $key;
            }
            $keys = [...$keys, ...$this->resourceRelationKeys($resource)];
        }

        if (in_array($module, ['akademik', 'raport', 'kesehatan', 'laporan', 'laporan-kesehatan'], true)) {
            $keys[] = 'announcements';
        }

        return array_values(array_unique($keys));
    }

    private function moduleResources(string $panel, string $module): array
    {
        $modules = [
            'super-admin' => [
                'pesantrens' => ['pesantrens'], 'owner' => ['memberships'], 'subscriptions' => ['subscriptions'],
                'plans' => ['plans'], 'users' => ['users'], 'modules' => ['tenant-modules'],
                'integrasi-api' => ['system-settings'], 'laporan-penggunaan' => ['tenant-modules'],
                'laporan-pesantren' => ['pesantrens'], 'laporan-keuangan' => ['subscriptions'],
                'backups' => ['backups'], 'system-update' => ['backups'],
                'cache-management' => ['system-settings'], 'settings' => ['system-settings'],
            ],
            'owner' => [
                'profil-pesantren' => ['pesantrens'], 'user-dan-role' => ['memberships'],
                'modul-aktif' => ['tenant-modules'], 'paket-dan-subscription' => ['subscriptions', 'plans'],
                'backup-dan-restore' => ['backups'], 'pengaturan' => ['system-settings'],
            ],
            'keuangan' => [
                'santri' => ['students'], 'wali-santri' => ['guardians'], 'program-dan-unit' => ['programs'],
                'komponen-biaya' => ['invoice-items'], 'rekening-bank' => ['bank-accounts'],
                'tahun-ajaran' => ['academic-years'], 'tagihan' => ['invoices'],
                'pembayaran' => ['payments'], 'riwayat-pembayaran' => ['payments'], 'tunggakan' => ['invoices'],
                'diskon-dan-keringanan' => ['discounts'], 'pembayaran-massal' => ['invoices'],
                'laporan-pemasukan' => ['cashflow-entries'], 'laporan-pengeluaran' => ['expenses'],
                'laporan-arus-kas' => ['cashflow-entries'], 'laporan-tunggakan' => ['invoices'],
                'laporan-rekap-bulanan' => ['cashflow-entries'], 'laporan-per-santri' => ['students'],
                'pengaturan-umum' => ['system-settings'], 'pengguna-dan-akses' => ['memberships'],
            ],
            'akademik' => [
                'data-master' => ['programs', 'academic-years'], 'kurikulum' => ['subjects'],
                'kelas' => ['classes'], 'mata-pelajaran' => ['subjects'],
                'guru' => ['teachers', 'teacher-assignments'], 'jadwal-pelajaran' => ['schedules'],
                'presensi' => ['attendances'], 'penilaian' => ['grades'], 'nilai-siswa' => ['grades'],
                'rapor' => ['report-cards'], 'kalender-akademik' => ['announcements'],
                'kelulusan' => ['students'], 'laporan-akademik' => ['classes'],
                'laporan-nilai' => ['grades'], 'pengaturan' => ['system-settings'],
            ],
            'tu' => [
                'santri' => ['students'], 'guru-dan-staf' => ['teachers', 'staff'], 'kelas' => ['classes'],
                'kamar' => ['rooms', 'musyrif-room-assignments'],
                'data-master' => ['programs', 'classes', 'rooms'], 'surat-menyurat' => ['letters'],
                'mutasi-santri' => ['room-mutations'], 'alumni' => ['alumni'],
                'pengumuman' => ['announcements'], 'pengaturan' => ['system-settings'],
            ],
            'santri' => [
                'akademik' => ['grades'], 'raport' => ['report-cards'], 'tahfidz' => ['memorization-records'],
                'presensi' => ['attendances'], 'perizinan' => ['permits'], 'kesehatan' => ['medical-records'],
                'billing' => ['invoices'], 'jadwal' => ['schedules'],
            ],
            'wali-murid' => [
                'anak-saya' => ['students'], 'akademik' => ['grades'], 'raport' => ['report-cards'],
                'presensi' => ['attendances'], 'perizinan' => ['permits'], 'pembayaran' => ['payments'],
                'tagihan' => ['invoices'], 'riwayat-pembayaran' => ['payments'],
                'kesehatan' => ['medical-records'], 'jadwal-anak' => ['schedules'],
            ],
            'guru' => [
                'jadwal-mengajar' => ['schedules'], 'kelas-saya' => ['classes'],
                'presensi-kelas' => ['attendances'], 'nilai' => ['grades'], 'tugas' => ['assignments'],
                'materi' => ['materials'], 'raport' => ['report-cards'], 'catatan-siswa' => ['daily-notes'],
            ],
            'musyrif' => [
                'santri-binaan' => ['students'], 'kamar' => ['rooms'], 'absensi-asrama' => ['dorm-attendances'],
                'perizinan' => ['permits'], 'pelanggaran' => ['violations'], 'catatan-harian' => ['daily-notes'],
                'mutasi-kamar' => ['room-mutations'], 'jadwal-piket' => ['schedules'], 'laporan' => ['students'],
            ],
            'klinik' => [
                'antrian' => ['medical-queues'], 'rekam-medis' => ['medical-records'],
                'pemeriksaan' => ['medical-examinations'], 'obat' => ['medicines'],
                'riwayat-berobat' => ['medical-records'], 'surat-sakit' => ['sick-letters'],
                'rujukan' => ['referrals'], 'jadwal-dokter' => ['doctors'],
                'stok-obat' => ['medicine-stocks'], 'laporan-kesehatan' => ['medical-records'],
            ],
        ];

        return $modules[$panel][$module] ?? [];
    }

    private function resourceSharedKey(string $resource): ?string
    {
        return [
            'academic-years' => 'academicYears', 'tenant-modules' => 'modules',
            'memberships' => 'memberships', 'teacher-assignments' => 'teacherAssignments',
            'musyrif-room-assignments' => 'musyrifRoomAssignments', 'dorm-attendances' => 'dormAttendances',
            'report-cards' => 'reportCards', 'memorization-records' => 'memorizationRecords',
            'daily-notes' => 'dailyNotes', 'invoice-items' => 'invoiceItems',
            'payment-methods' => 'paymentMethods', 'bank-accounts' => 'bankAccounts',
            'expense-categories' => 'expenseCategories', 'cashflow-entries' => 'cashflows',
            'room-mutations' => 'roomMutations', 'medicine-stocks' => 'medicineStocks',
            'medical-queues' => 'medicalQueues', 'medical-records' => 'medicalRecords',
            'medical-examinations' => 'medicalExaminations', 'sick-letters' => 'sickLetters',
            'system-settings' => 'settings',
        ][$resource] ?? match ($resource) {
            'pesantrens', 'users', 'roles', 'subscriptions', 'plans', 'students', 'guardians', 'classes',
            'programs', 'subjects', 'teachers', 'staff', 'rooms', 'schedules', 'attendances', 'grades',
            'assignments', 'materials', 'announcements', 'violations', 'permits', 'invoices', 'payments',
            'expenses', 'discounts', 'letters', 'alumni', 'clinics', 'doctors', 'medicines', 'referrals',
            'backups' => $resource,
            default => null,
        };
    }

    private function resourceRelationKeys(string $resource): array
    {
        return [
            'memberships' => ['users', 'roles', 'pesantrens'],
            'subscriptions' => ['pesantrens', 'plans'],
            'tenant-modules' => ['pesantrens'],
            'classes' => ['academicYears', 'programs'],
            'students' => ['users', 'guardians', 'classes', 'programs'],
            'guardians' => ['users'],
            'teachers' => ['users', 'subjects'],
            'staff' => ['users'],
            'teacher-assignments' => ['teachers', 'classes', 'subjects'],
            'musyrif-room-assignments' => ['users', 'rooms'],
            'schedules' => ['classes', 'subjects', 'teachers'],
            'attendances' => ['students', 'classes', 'subjects'],
            'dorm-attendances' => ['students', 'rooms'],
            'grades' => ['students', 'classes', 'subjects'],
            'report-cards' => ['students', 'classes'],
            'memorization-records' => ['students'],
            'assignments', 'materials' => ['classes', 'subjects'],
            'permits', 'daily-notes', 'violations', 'medical-queues', 'medical-records', 'sick-letters', 'referrals' => ['students'],
            'invoices' => ['students'],
            'invoice-items' => ['invoices'],
            'payments' => ['invoices', 'paymentMethods'],
            'expenses' => ['expenseCategories'],
            'room-mutations' => ['students', 'rooms'],
            'medicine-stocks' => ['medicines'],
            'medical-examinations' => ['medicalRecords'],
        ][$resource] ?? [];
    }

    private function sharedDataset(?Request $request, string $key): mixed
    {
        if ($key === 'auditLogs') {
            return $this->query($request, 'audit_logs')
                ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
                ->select('audit_logs.id', 'users.name as pengguna', 'audit_logs.action as aktivitas', 'audit_logs.module as modul', 'audit_logs.ip_address as ip', 'audit_logs.metadata', 'audit_logs.created_at as waktu')
                ->latest('audit_logs.id')->limit(10)->get();
        }

        if ($key === 'arrears') {
            return $this->query($request, 'invoices')
                ->leftJoin('students', 'invoices.student_id', '=', 'students.id')
                ->where('invoices.status', 'unpaid')
                ->select('invoices.id', 'invoices.invoice_number as nomor_tagihan', 'students.name as santri', 'invoices.period as periode', 'invoices.amount as nominal', 'invoices.due_date as jatuh_tempo', 'invoices.status')
                ->latest('invoices.id')->limit(10)->get();
        }

        return match ($key) {
            'roles' => DB::table('roles')->select('id', 'name', 'label')->orderBy('label')->get(),
            'memberships' => $this->memberships($request),
            'pesantrens' => $this->pesantrens($request),
            'modules' => $this->modules($request),
            'systemActivities' => $this->systemActivities(),
            'loginMonitoring' => $this->loginMonitoring(),
            'errorLogs' => $this->errorLogs(),
            'notifications' => $this->query($request, 'notification_logs')->whereNull('read_at')->latest('id')->limit(5)->get(),
            'announcements' => $this->query($request, 'announcements')->latest('id')->limit(5)->get(),
            default => $this->query($request, $this->sharedTable($key))->latest('id')->limit($this->sharedLimit($key))->get(),
        };
    }

    private function sharedTable(string $key): string
    {
        return [
            'academicYears' => 'academic_years', 'teacherAssignments' => 'teacher_assignments',
            'musyrifRoomAssignments' => 'musyrif_room_assignments', 'dormAttendances' => 'dorm_attendances',
            'reportCards' => 'report_cards', 'memorizationRecords' => 'memorization_records',
            'dailyNotes' => 'daily_notes', 'invoiceItems' => 'invoice_items',
            'paymentMethods' => 'payment_methods', 'bankAccounts' => 'bank_accounts',
            'expenseCategories' => 'expense_categories', 'cashflows' => 'cashflow_entries',
            'roomMutations' => 'room_mutations', 'medicineStocks' => 'medicine_stocks',
            'medicalQueues' => 'medical_queues', 'medicalRecords' => 'medical_records',
            'medicalExaminations' => 'medical_examinations', 'sickLetters' => 'sick_letters',
            'settings' => 'system_settings',
        ][$key] ?? str_replace('-', '_', $key);
    }

    private function sharedLimit(string $key): int
    {
        return in_array($key, ['teacherAssignments', 'musyrifRoomAssignments'], true) ? 20 : 10;
    }

    private function superAdmin(): array
    {
        return [
            'role' => 'super_admin',
            'title' => 'Dashboard',
            'subtitle' => 'Ringkasan sistem Pesantenku',
            'stats' => [
                ['label' => 'Total Pesantren', 'value' => '128', 'trend' => '+8 dari bulan lalu', 'icon' => 'School', 'tone' => 'info'],
                ['label' => 'Total User', 'value' => '5.842', 'trend' => '+156 dari bulan lalu', 'icon' => 'Users', 'tone' => 'success'],
                ['label' => 'Total Santri', 'value' => '34.782', 'trend' => '+1.248 dari bulan lalu', 'icon' => 'Box', 'tone' => 'purple'],
                ['label' => 'Transaksi Bulan Ini', 'value' => 'Rp 3,24 M', 'trend' => '+12,6% dari bulan lalu', 'icon' => 'PieChart', 'tone' => 'warning'],
                ['label' => 'Pendapatan Bulan Ini', 'value' => 'Rp 2,18 M', 'trend' => '+15,3% dari bulan lalu', 'icon' => 'Wallet', 'tone' => 'error'],
                ['label' => 'Server Status', 'value' => '100%', 'trend' => 'Semua sistem normal', 'icon' => 'Server', 'tone' => 'info'],
            ],
            'lineChart' => [
                ['month' => 'Des 2024', 'Pengguna Aktif' => 1600, 'Transaksi' => 900],
                ['month' => 'Jan 2025', 'Pengguna Aktif' => 2600, 'Transaksi' => 1800],
                ['month' => 'Feb 2025', 'Pengguna Aktif' => 3000, 'Transaksi' => 2200],
                ['month' => 'Mar 2025', 'Pengguna Aktif' => 3800, 'Transaksi' => 2500],
                ['month' => 'Apr 2025', 'Pengguna Aktif' => 3100, 'Transaksi' => 3800],
                ['month' => 'Mei 2025', 'Pengguna Aktif' => 3800, 'Transaksi' => 2900],
            ],
            'donut' => [
                ['name' => 'Santri', 'value' => 2987],
                ['name' => 'Wali Santri', 'value' => 1245],
                ['name' => 'Guru', 'value' => 685],
                ['name' => 'Musyrif', 'value' => 365],
                ['name' => 'TU', 'value' => 235],
                ['name' => 'Keuangan', 'value' => 180],
            ],
            'tables' => [
                'pesantren' => [
                    ['Nama Pesantren' => 'Pondok Pesantren Darul Hikmah', 'Kota' => 'Surabaya', 'Santri' => 320, 'Bergabung' => '10 Mei 2025', 'Status' => 'Aktif'],
                    ['Nama Pesantren' => 'Pesantren Al-Falah', 'Kota' => 'Bandung', 'Santri' => 250, 'Bergabung' => '9 Mei 2025', 'Status' => 'Aktif'],
                    ['Nama Pesantren' => 'Pesantren Nurul Huda', 'Kota' => 'Yogyakarta', 'Santri' => 180, 'Bergabung' => '8 Mei 2025', 'Status' => 'Aktif'],
                ],
            ],
        ] + $this->sharedWidgets();
    }

    private function owner(?Request $request): array
    {
        $tenant = $request ? $this->tenantContext->activePesantren($request, 'owner_pesantren') : null;
        $students = $this->query($request, 'students')->count();
        $users = $this->memberships($request)->count();
        $income = (float) $this->query($request, 'payments')->where('status', 'success')->sum('amount');
        $arrears = (float) $this->query($request, 'invoices')->where('status', 'unpaid')->sum('amount');
        $modules = $this->modules($request)->where('status', 'Aktif')->count();
        $subscription = $this->query($request, 'subscriptions')->latest('id')->first();

        return [
            'role' => 'owner_pesantren',
            'title' => 'Dashboard Pesantren',
            'subtitle' => 'Kontrol tenant, paket, modul, user, dan kesehatan operasional '.($tenant?->name ?? 'pesantren'),
            'stats' => [
                ['label' => 'Santri Aktif', 'value' => number_format($students, 0, ',', '.'), 'trend' => 'Data tenant aktif', 'icon' => 'Users', 'tone' => 'success'],
                ['label' => 'User Tenant', 'value' => number_format($users, 0, ',', '.'), 'trend' => 'Termasuk admin, guru, wali, santri', 'icon' => 'UserCog', 'tone' => 'info'],
                ['label' => 'Modul Aktif', 'value' => (string) $modules, 'trend' => 'Akademik, Keuangan, TU, Asrama, Klinik', 'icon' => 'Menu', 'tone' => 'purple'],
                ['label' => 'Paket SaaS', 'value' => $subscription?->status ? ucfirst($subscription->status) : 'Trial', 'trend' => 'Kelola subscription tenant', 'icon' => 'BadgeCheck', 'tone' => 'warning'],
                ['label' => 'Pendapatan', 'value' => $this->rupiah($income), 'trend' => 'Pembayaran sukses', 'icon' => 'Wallet', 'tone' => 'success'],
                ['label' => 'Tunggakan', 'value' => $this->rupiah($arrears), 'trend' => 'Tagihan belum lunas', 'icon' => 'TriangleAlert', 'tone' => 'warning'],
            ],
            'lineChart' => [
                ['month' => 'Jan', 'User Aktif' => 180, 'Transaksi' => 86],
                ['month' => 'Feb', 'User Aktif' => 220, 'Transaksi' => 112],
                ['month' => 'Mar', 'User Aktif' => 245, 'Transaksi' => 148],
                ['month' => 'Apr', 'User Aktif' => 270, 'Transaksi' => 175],
                ['month' => 'Mei', 'User Aktif' => 312, 'Transaksi' => 203],
                ['month' => 'Jun', 'User Aktif' => 340, 'Transaksi' => 226],
            ],
            'donut' => [
                ['name' => 'Akademik', 'value' => 92],
                ['name' => 'Keuangan', 'value' => 88],
                ['name' => 'TU', 'value' => 81],
                ['name' => 'Asrama', 'value' => 74],
                ['name' => 'Klinik', 'value' => 63],
            ],
            'quickActions' => ['Tambah User', 'Modul Aktif', 'Paket & Subscription', 'Audit Tenant', 'Pengaturan'],
        ] + $this->sharedWidgets();
    }

    private function finance(?Request $request): array
    {
        return [
            'role' => 'admin_keuangan',
            'title' => 'Dashboard',
            'subtitle' => 'Ringkasan keuangan pesantren',
            'stats' => [
                ['label' => 'Total Pemasukan', 'value' => 'Rp 3.245.780.000', 'trend' => '+12.5% dari bulan lalu', 'icon' => 'Wallet', 'tone' => 'success'],
                ['label' => 'Total Pengeluaran', 'value' => 'Rp 1.256.340.000', 'trend' => '-4.2% dari bulan lalu', 'icon' => 'LogOut', 'tone' => 'error'],
                ['label' => 'Saldo Kas', 'value' => 'Rp 1.989.440.000', 'trend' => '+8.7% dari bulan lalu', 'icon' => 'CreditCard', 'tone' => 'info'],
                ['label' => 'Tunggakan', 'value' => 'Rp 622.780.000', 'trend' => '246 santri menunggak', 'icon' => 'TriangleAlert', 'tone' => 'warning'],
                ['label' => 'Lunas Bulan Ini', 'value' => '458 Santri', 'trend' => '+18.6% dari bulan lalu', 'icon' => 'CircleCheck', 'tone' => 'purple'],
            ],
            'lineChart' => $this->financeChart(),
            'donut' => [
                ['name' => 'SPP', 'value' => 1850450000],
                ['name' => 'Uang Makan', 'value' => 780120000],
                ['name' => 'Seragam', 'value' => 320000000],
                ['name' => 'Kegiatan', 'value' => 180300000],
                ['name' => 'Lain-lain', 'value' => 119910000],
            ],
            'quickActions' => ['Tambah Tagihan', 'Tambah Pembayaran', 'Laporan Pemasukan', 'Laporan Tunggakan', 'Pengaturan Umum'],
        ] + $this->sharedWidgets();
    }

    private function academic(?Request $request): array
    {
        $studentCount = $this->query($request, 'students')->count();
        $teacherCount = $this->query($request, 'teachers')->count();
        $classCount = $this->query($request, 'classes')->count();
        $subjectCount = $this->query($request, 'subjects')->count();
        $average = (float) $this->query($request, 'grades')->avg('average_score');

        return [
            'role' => 'admin_akademik',
            'title' => 'Dashboard',
            'subtitle' => 'Ringkasan aktivitas akademik pesantren',
            'stats' => [
                ['label' => 'Total Siswa', 'value' => number_format($studentCount ?: 1248, 0, ',', '.'), 'trend' => '+32 dari bulan lalu', 'icon' => 'Users', 'tone' => 'success'],
                ['label' => 'Total Guru', 'value' => number_format($teacherCount ?: 98, 0, ',', '.'), 'trend' => '+5 dari bulan lalu', 'icon' => 'GraduationCap', 'tone' => 'info'],
                ['label' => 'Total Kelas', 'value' => number_format($classCount ?: 42, 0, ',', '.'), 'trend' => 'Tahun Ajaran 2024/2025', 'icon' => 'School', 'tone' => 'purple'],
                ['label' => 'Mata Pelajaran', 'value' => number_format($subjectCount ?: 156, 0, ',', '.'), 'trend' => 'Semua jenjang', 'icon' => 'BookOpen', 'tone' => 'warning'],
                ['label' => 'Kelas Aktif', 'value' => number_format($classCount ?: 38, 0, ',', '.'), 'trend' => 'Tahun Ajaran 2024/2025', 'icon' => 'BadgeCheck', 'tone' => 'success'],
                ['label' => 'Rata-rata Nilai', 'value' => number_format($average ?: 82.45, 2, ',', '.'), 'trend' => 'Semua kelas', 'icon' => 'BarChart3', 'tone' => 'info'],
            ],
            'lineChart' => [
                ['month' => 'Jul', 'Siswa Masuk' => 650, 'Siswa Keluar' => 320, 'Total Aktif' => 850],
                ['month' => 'Agu', 'Siswa Masuk' => 920, 'Siswa Keluar' => 450, 'Total Aktif' => 1100],
                ['month' => 'Sep', 'Siswa Masuk' => 930, 'Siswa Keluar' => 430, 'Total Aktif' => 1105],
                ['month' => 'Okt', 'Siswa Masuk' => 980, 'Siswa Keluar' => 510, 'Total Aktif' => 1120],
                ['month' => 'Nov', 'Siswa Masuk' => 1200, 'Siswa Keluar' => 420, 'Total Aktif' => 1250],
                ['month' => 'Des', 'Siswa Masuk' => 1190, 'Siswa Keluar' => 650, 'Total Aktif' => 1210],
            ],
            'donut' => [
                ['name' => 'Tsanawiyah', 'value' => 18],
                ['name' => 'Aliyah', 'value' => 16],
                ['name' => 'Tahfidz', 'value' => 8],
            ],
            'quickActions' => ['Tambah Kelas', 'Input Nilai', 'Jadwal Pelajaran', 'Presensi', 'Laporan Nilai', 'Pengaturan'],
        ] + $this->sharedWidgets();
    }

    private function tu(?Request $request): array
    {
        $studentCount = $this->query($request, 'students')->count();
        $teacherStaffCount = $this->query($request, 'teachers')->count() + $this->query($request, 'staff')->count();
        $roomCount = $this->query($request, 'rooms')->count();
        $classCount = $this->query($request, 'classes')->count();
        $incomingLetters = $this->query($request, 'letters')->where('type', 'incoming')->count();
        $outgoingLetters = $this->query($request, 'letters')->where('type', 'outgoing')->count();

        return [
            'role' => 'admin_tu',
            'title' => 'Dashboard',
            'subtitle' => 'Ringkasan data pesantren',
            'stats' => [
                ['label' => 'Total Santri', 'value' => number_format($studentCount ?: 1248, 0, ',', '.'), 'trend' => '+12 dari bulan lalu', 'icon' => 'Users', 'tone' => 'success'],
                ['label' => 'Guru & Staf', 'value' => number_format($teacherStaffCount ?: 98, 0, ',', '.'), 'trend' => '+3 dari bulan lalu', 'icon' => 'UserCog', 'tone' => 'info'],
                ['label' => 'Kamar', 'value' => number_format($roomCount ?: 156, 0, ',', '.'), 'trend' => 'Terisi 85%', 'icon' => 'Bed', 'tone' => 'purple'],
                ['label' => 'Kelas', 'value' => number_format($classCount ?: 42, 0, ',', '.'), 'trend' => 'Tahun ajaran 2024/2025', 'icon' => 'School', 'tone' => 'warning'],
                ['label' => 'Surat Keluar', 'value' => number_format($outgoingLetters ?: 76, 0, ',', '.'), 'trend' => 'Bulan ini', 'icon' => 'Send', 'tone' => 'success'],
                ['label' => 'Surat Masuk', 'value' => number_format($incomingLetters ?: 34, 0, ',', '.'), 'trend' => 'Bulan ini', 'icon' => 'Inbox', 'tone' => 'info'],
            ],
            'lineChart' => [
                ['month' => 'Des', 'Total Santri' => 1100],
                ['month' => 'Jan', 'Total Santri' => 1160],
                ['month' => 'Feb', 'Total Santri' => 1180],
                ['month' => 'Mar', 'Total Santri' => 1150],
                ['month' => 'Apr', 'Total Santri' => 1210],
                ['month' => 'Mei', 'Total Santri' => 1260],
            ],
            'quickActions' => ['Tambah Santri', 'Surat Menyurat', 'Mutasi Santri', 'Kamar', 'Pengumuman', 'Pengaturan'],
        ] + $this->sharedWidgets();
    }

    private function mobile(string $role, ?Request $request): array
    {
        $profile = [
            'santri' => ['name' => 'Najwa Karimah', 'subtitle' => 'Kamar C-07 - Tsanawiyah 2A'],
            'wali_murid' => ['name' => 'Bapak/Ibu Nur Azizah Ghafur', 'subtitle' => 'Wali dari Najwa Karimah'],
            'musyrif' => ['name' => 'Ustadzah Siti Aisyah', 'subtitle' => 'Musyrifah Putri - Blok C'],
            'guru' => ['name' => 'Ustadzah Siti Aisyah', 'subtitle' => 'Guru Bahasa Arab'],
            'klinik' => ['name' => 'Najwa Karimah', 'subtitle' => 'Kelas Tsanawiyah 2A'],
        ][$role];

        return [
            'role' => $role,
            'profile' => $profile,
            'summary' => [
                ['label' => 'Nilai', 'value' => '85.6'],
                ['label' => 'Hafalan', 'value' => '12 Juz'],
                ['label' => 'Pelanggaran', 'value' => '2 poin'],
                ['label' => 'Tagihan', 'value' => 'Lunas'],
            ],
            'menu' => $this->mobileMenus($role),
            'announcements' => $this->query($request, 'announcements')->latest('id')->limit(5)->get(),
            'students' => $this->query($request, 'students')->limit(5)->get(),
            'schedules' => $this->query($request, 'schedules')->limit(5)->get(),
            'violations' => $this->query($request, 'violations')->limit(5)->get(),
            'invoices' => $this->query($request, 'invoices')->limit(5)->get(),
            'medical' => $this->query($request, 'medical_records')->limit(5)->get(),
        ];
    }

    private function sharedWidgets(): array
    {
        return [
            'activities' => [
                ['title' => 'Pesantren Baru Didaftarkan', 'description' => 'Pondok Pesantren Darul Hikmah', 'time' => '10 Mei 2025 10:24'],
                ['title' => 'Backup Sistem Berhasil', 'description' => 'Backup otomatis harian', 'time' => '10 Mei 2025 02:15'],
                ['title' => 'Update Sistem', 'description' => 'Versi 1.0.0 berhasil diinstal', 'time' => '9 Mei 2025 21:30'],
            ],
            'moduleUsage' => [
                ['name' => 'Akademik', 'value' => 85],
                ['name' => 'Keuangan', 'value' => 78],
                ['name' => 'Santri', 'value' => 72],
                ['name' => 'Klinik', 'value' => 58],
                ['name' => 'Presensi', 'value' => 55],
            ],
            'quickActions' => ['Tambah Pesantren', 'Tambah User', 'Assign Role', 'Backup Sekarang', 'Clear Cache', 'System Update', 'Lihat Log Audit', 'Pengaturan Sistem', 'Dokumentasi'],
        ];
    }

    private function systemActivities(): array
    {
        return [
            ['waktu' => '10 Mei 2025 10:24', 'aktivitas' => 'Pesantren Baru Didaftarkan', 'modul' => 'Manajemen Sistem', 'pengguna' => 'Super Admin', 'ip' => '103.147.12.45', 'status' => 'Berhasil'],
            ['waktu' => '10 Mei 2025 09:15', 'aktivitas' => 'User Baru Dibuat', 'modul' => 'User & Role', 'pengguna' => 'Super Admin', 'ip' => '103.147.12.45', 'status' => 'Berhasil'],
            ['waktu' => '10 Mei 2025 02:15', 'aktivitas' => 'Backup Sistem Berhasil', 'modul' => 'Maintenance', 'pengguna' => 'Scheduler', 'ip' => '127.0.0.1', 'status' => 'Berhasil'],
            ['waktu' => '9 Mei 2025 23:48', 'aktivitas' => 'Login Mencurigakan Diblokir', 'modul' => 'Login Monitoring', 'pengguna' => 'Firewall', 'ip' => '103.147.12.88', 'status' => 'Peringatan'],
            ['waktu' => '9 Mei 2025 21:30', 'aktivitas' => 'Update Sistem Terpasang', 'modul' => 'System Update', 'pengguna' => 'Super Admin', 'ip' => '103.147.12.45', 'status' => 'Selesai'],
        ];
    }

    private function systemModules(): array
    {
        return [
            ['kode' => 'akademik', 'nama_modul' => 'Akademik', 'role_akses' => 'admin_akademik, guru, santri, wali_murid', 'status' => 'Aktif'],
            ['kode' => 'keuangan', 'nama_modul' => 'Keuangan', 'role_akses' => 'admin_keuangan, santri, wali_murid', 'status' => 'Aktif'],
            ['kode' => 'tata_usaha', 'nama_modul' => 'Tata Usaha', 'role_akses' => 'admin_tu', 'status' => 'Aktif'],
            ['kode' => 'asrama', 'nama_modul' => 'Kepesantrenan & Musyrif', 'role_akses' => 'musyrif, santri, wali_murid', 'status' => 'Aktif'],
            ['kode' => 'klinik', 'nama_modul' => 'Klinik', 'role_akses' => 'klinik, santri, wali_murid', 'status' => 'Aktif'],
            ['kode' => 'monitoring', 'nama_modul' => 'Monitoring Sistem', 'role_akses' => 'super_admin', 'status' => 'Aktif'],
        ];
    }

    private function loginMonitoring(): array
    {
        return [
            ['waktu' => '10 Mei 2025 10:30', 'pengguna' => 'Super Admin', 'role' => 'super_admin', 'ip' => '103.147.12.45', 'perangkat' => 'Edge / Windows', 'status' => 'Berhasil'],
            ['waktu' => '10 Mei 2025 09:45', 'pengguna' => 'Keuangan Admin', 'role' => 'admin_keuangan', 'ip' => '103.147.12.46', 'perangkat' => 'Chrome / Windows', 'status' => 'Berhasil'],
            ['waktu' => '10 Mei 2025 09:20', 'pengguna' => 'Ustadzah Siti Aisyah', 'role' => 'guru', 'ip' => '114.10.21.8', 'perangkat' => 'Android App', 'status' => 'Berhasil'],
            ['waktu' => '9 Mei 2025 23:48', 'pengguna' => 'Tidak dikenal', 'role' => '-', 'ip' => '103.147.12.88', 'perangkat' => 'Unknown Browser', 'status' => 'Diblokir'],
        ];
    }

    private function errorLogs(): array
    {
        return [
            ['waktu' => '10 Mei 2025 08:11', 'level' => 'Info', 'modul' => 'API', 'pesan' => 'Sinkronisasi dashboard selesai', 'status' => 'Selesai'],
            ['waktu' => '9 Mei 2025 22:14', 'level' => 'Warning', 'modul' => 'Integrasi API', 'pesan' => 'Timeout gateway pembayaran, retry berhasil', 'status' => 'Dipantau'],
            ['waktu' => '9 Mei 2025 02:15', 'level' => 'Info', 'modul' => 'Backup', 'pesan' => 'Backup database selesai tanpa error', 'status' => 'Selesai'],
            ['waktu' => '8 Mei 2025 18:05', 'level' => 'Error', 'modul' => 'Import CSV', 'pesan' => '3 baris nilai ditolak karena format tidak valid', 'status' => 'Ditangani'],
        ];
    }

    private function financeChart(): array
    {
        return [
            ['month' => 'Des 2024', 'Pemasukan' => 260000000, 'Pengeluaran' => 130000000],
            ['month' => 'Jan 2025', 'Pemasukan' => 410000000, 'Pengeluaran' => 320000000],
            ['month' => 'Feb 2025', 'Pemasukan' => 540000000, 'Pengeluaran' => 220000000],
            ['month' => 'Mar 2025', 'Pemasukan' => 750000000, 'Pengeluaran' => 370000000],
            ['month' => 'Apr 2025', 'Pemasukan' => 590000000, 'Pengeluaran' => 310000000],
            ['month' => 'Mei 2025', 'Pemasukan' => 890000000, 'Pengeluaran' => 500000000],
        ];
    }

    private function query(?Request $request, string $table)
    {
        $query = DB::table($table);

        if ($this->hasColumn($table, 'deleted_at')) {
            $query->whereNull("{$table}.deleted_at");
        }

        if ($request) {
            $this->tenantContext->applyScope($request, $query, $table);
            $this->applyRoleScope($request, $query, $table);
        }

        return $query;
    }

    private function applyRoleScope(Request $request, $query, string $table): void
    {
        $user = $request->user()?->loadMissing('roles');
        if (! $user) {
            return;
        }

        if ($user->hasRole(['santri', 'wali_murid'])) {
            $this->applyPersonalScope($request, $query, $table);
            return;
        }

        if ($user->hasRole('guru')) {
            $this->applyTeacherScope($request, $query, $table);
            return;
        }

        if ($user->hasRole('musyrif')) {
            $this->applyDormScope($request, $query, $table);
        }
    }

    private function applyPersonalScope(Request $request, $query, string $table): void
    {
        $user = $request->user()?->loadMissing('roles');
        if (! $user || ! $user->hasRole(['santri', 'wali_murid'])) {
            return;
        }

        $studentIds = $this->accessibleStudentIds($request);
        $classIds = $this->requestMemo($request, 'personal-class-ids', fn () => DB::table('students')
            ->whereIn('id', $studentIds ?: [0])
            ->pluck('class_id')->filter()->values()->all());

        if ($table === 'students') {
            $query->whereIn('students.id', $studentIds ?: [0]);
            return;
        }

        if ($this->hasColumn($table, 'student_id')) {
            $query->whereIn("{$table}.student_id", $studentIds ?: [0]);
            return;
        }

        if ($table === 'schedules') {
            $query->whereIn('schedules.class_id', $classIds ?: [0]);
            return;
        }

        if ($table === 'invoice_items') {
            $query->whereExists(function ($subQuery) use ($studentIds, $table) {
                $subQuery->selectRaw('1')
                    ->from('invoices')
                    ->whereColumn('invoices.id', "{$table}.invoice_id")
                    ->whereIn('invoices.student_id', $studentIds ?: [0]);
            });
            return;
        }

        if (in_array($table, ['medical_examinations', 'prescriptions'], true)) {
            $query->whereExists(function ($subQuery) use ($studentIds, $table) {
                $subQuery->selectRaw('1')
                    ->from('medical_records')
                    ->whereColumn('medical_records.id', "{$table}.medical_record_id")
                    ->whereIn('medical_records.student_id', $studentIds ?: [0]);
            });
        }
    }

    private function applyTeacherScope(Request $request, $query, string $table): void
    {
        $scope = $this->teacherScope($request);

        if ($table === 'teachers') {
            $query->whereIn('teachers.id', $scope['teacher_ids'] ?: [0]);
            return;
        }

        if ($table === 'students') {
            $query->whereIn('students.class_id', $scope['class_ids'] ?: [0]);
            return;
        }

        if ($table === 'classes') {
            $query->whereIn('classes.id', $scope['class_ids'] ?: [0]);
            return;
        }

        if ($table === 'subjects') {
            $query->whereIn('subjects.id', $scope['subject_ids'] ?: [0]);
            return;
        }

        if ($this->hasColumn($table, 'student_id')) {
            $query->whereIn("{$table}.student_id", $scope['student_ids'] ?: [0]);
            return;
        }

        if ($this->hasColumn($table, 'class_id')) {
            $query->whereIn("{$table}.class_id", $scope['class_ids'] ?: [0]);
        }

        if ($this->hasColumn($table, 'subject_id')) {
            $query->whereIn("{$table}.subject_id", $scope['subject_ids'] ?: [0]);
        }

        if ($table === 'schedules') {
            $query->whereIn('schedules.teacher_name', $scope['teacher_names'] ?: ['__none__']);
        }
    }

    private function applyDormScope(Request $request, $query, string $table): void
    {
        $scope = $this->dormScope($request);

        if ($table === 'rooms') {
            $query->whereIn('rooms.id', $scope['room_ids'] ?: [0]);
            return;
        }

        if ($table === 'students') {
            $query->whereIn('students.id', $scope['student_ids'] ?: [0]);
            return;
        }

        if ($this->hasColumn($table, 'room_id')) {
            $query->whereIn("{$table}.room_id", $scope['room_ids'] ?: [0]);
        }

        if ($this->hasColumn($table, 'student_id')) {
            $query->whereIn("{$table}.student_id", $scope['student_ids'] ?: [0]);
        }
    }

    private function accessibleStudentIds(Request $request): array
    {
        return $this->requestMemo($request, 'accessible-student-ids', function () use ($request) {
        $user = $request->user()?->loadMissing('roles');
        if (! $user) {
            return [];
        }

        $tenantId = $this->tenantContext->activePesantrenId($request);

        if ($user->hasRole('santri')) {
            return DB::table('students')
                ->where('pesantren_id', $tenantId)
                ->where('user_id', $user->id)
                ->pluck('id')
                ->all();
        }

        if ($user->hasRole('wali_murid')) {
            return DB::table('students')
                ->join('guardians', 'students.guardian_id', '=', 'guardians.id')
                ->where('students.pesantren_id', $tenantId)
                ->where('guardians.user_id', $user->id)
                ->pluck('students.id')
                ->all();
        }

        return [];
        });
    }

    private function teacherScope(Request $request): array
    {
        return $this->requestMemo($request, 'teacher-scope', function () use ($request) {
        $tenantId = $this->tenantContext->activePesantrenId($request);
        $userId = $request->user()?->id;
        $teachers = DB::table('teachers')->where('pesantren_id', $tenantId)->where('user_id', $userId)->get();
        $teacherIds = $teachers->pluck('id')->all();
        $teacherNames = $teachers->pluck('name')->filter()->values()->all();
        $teacherSubjects = $teachers->pluck('subject')->filter()->values()->all();

        $assignments = $this->hasTable('teacher_assignments')
            ? DB::table('teacher_assignments')
                ->where('pesantren_id', $tenantId)
                ->where('status', 'active')
                ->whereIn('teacher_id', $teacherIds ?: [0])
                ->get()
            : collect();

        if ($assignments->isNotEmpty()) {
            $subjectIds = $assignments->pluck('subject_id')->filter()->unique()->values()->all();
            $classIds = $assignments->pluck('class_id')->filter()->unique()->values()->all();
        } else {
            $subjectIds = DB::table('subjects')
                ->where('pesantren_id', $tenantId)
                ->where(function ($query) use ($teacherNames, $teacherSubjects) {
                    $query->whereIn('teacher_name', $teacherNames ?: ['__none__'])
                        ->orWhereIn('name', $teacherSubjects ?: ['__none__']);
                })
                ->pluck('id')
                ->all();

            $classIds = DB::table('schedules')
                ->where('pesantren_id', $tenantId)
                ->where(function ($query) use ($teacherNames, $subjectIds) {
                    $query->whereIn('teacher_name', $teacherNames ?: ['__none__'])
                        ->orWhereIn('subject_id', $subjectIds ?: [0]);
                })
                ->pluck('class_id')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $studentIds = DB::table('students')
            ->where('pesantren_id', $tenantId)
            ->whereIn('class_id', $classIds ?: [0])
            ->pluck('id')
            ->all();

        return [
            'teacher_ids' => $teacherIds,
            'teacher_names' => $teacherNames,
            'subject_ids' => $subjectIds,
            'class_ids' => $classIds,
            'student_ids' => $studentIds,
        ];
        });
    }

    private function dormScope(Request $request): array
    {
        return $this->requestMemo($request, 'dorm-scope', function () use ($request) {
        $tenantId = $this->tenantContext->activePesantrenId($request);
        $userId = $request->user()?->id;
        $assignedRoomIds = $this->hasTable('musyrif_room_assignments')
            ? DB::table('musyrif_room_assignments')
                ->where('pesantren_id', $tenantId)
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->pluck('room_id')
                ->filter()
                ->values()
                ->all()
            : [];

        $rooms = DB::table('rooms')
            ->where('pesantren_id', $tenantId)
            ->when($assignedRoomIds, fn ($query) => $query->whereIn('id', $assignedRoomIds), function ($query) {
                $query->where(function ($query) {
                    $query->where('block', 'like', '%Putri%')
                        ->orWhere('name', 'like', 'C-%');
                });
            })
            ->get();

        $roomNames = $rooms->pluck('name')->all();
        $studentIds = DB::table('students')
            ->where('pesantren_id', $tenantId)
            ->whereIn('room', $roomNames ?: ['__none__'])
            ->pluck('id')
            ->all();

        return [
            'room_ids' => $rooms->pluck('id')->all(),
            'room_names' => $roomNames,
            'student_ids' => $studentIds,
        ];
        });
    }

    private function hasColumn(string $table, string $column): bool
    {
        $this->schemaColumns[$table] ??= Schema::getColumnListing($table);

        return in_array($column, $this->schemaColumns[$table], true);
    }

    private function hasTable(string $table): bool
    {
        return $this->schemaTables[$table] ??= Schema::hasTable($table);
    }

    private function requestMemo(Request $request, string $key, callable $resolver): mixed
    {
        $attribute = "pesantenku.dashboard.{$key}";
        if (! $request->attributes->has($attribute)) {
            $request->attributes->set($attribute, $resolver());
        }

        return $request->attributes->get($attribute);
    }

    private function pesantrens(?Request $request)
    {
        $query = DB::table('pesantrens')
            ->select('id', 'name as nama_pesantren', 'city as kota', 'phone as telepon', 'email', 'status')
            ->whereNull('deleted_at');

        if ($request && ! $request->user()?->hasRole('super_admin')) {
            $ids = $this->tenantContext->memberships($request)->pluck('id')->all();
            $query->whereIn('id', $ids ?: [0]);
        }

        return $query->limit(10)->get();
    }

    private function memberships(?Request $request)
    {
        if (! $request || ! $request->user()) {
            return collect();
        }

        if (! $request->user()->hasRole('super_admin')) {
            return $this->tenantContext->memberships($request);
        }

        return DB::table('pesantren_user')
            ->join('pesantrens', 'pesantren_user.pesantren_id', '=', 'pesantrens.id')
            ->join('users', 'pesantren_user.user_id', '=', 'users.id')
            ->join('roles', 'pesantren_user.role_id', '=', 'roles.id')
            ->whereNull('pesantrens.deleted_at')
            ->select([
                'pesantren_user.id',
                'pesantrens.name as pesantren',
                'users.name as pengguna',
                'users.email',
                'roles.label as role',
                'pesantren_user.status',
            ])
            ->latest('pesantren_user.id')
            ->limit(20)
            ->get();
    }

    private function modules(?Request $request)
    {
        if (! $request || $request->user()?->hasRole('super_admin')) {
            return collect($this->systemModules());
        }

        $tenantId = $this->tenantContext->activePesantrenId($request);
        $labels = collect($this->systemModules())->keyBy('kode');

        return DB::table('tenant_modules')
            ->where('pesantren_id', $tenantId)
            ->get()
            ->map(fn ($item) => [
                'kode' => $item->module,
                'nama_modul' => $labels->get($item->module)['nama_modul'] ?? ucwords(str_replace('_', ' ', $item->module)),
                'role_akses' => $labels->get($item->module)['role_akses'] ?? 'owner_pesantren',
                'status' => $item->is_enabled ? 'Aktif' : 'Nonaktif',
            ]);
    }

    private function rupiah(float $value): string
    {
        return 'Rp '.number_format($value, 0, ',', '.');
    }

    private function mobileMenus(string $role): array
    {
        return match ($role) {
            'santri' => ['Dashboard', 'Profil', 'Akademik', 'Raport', 'Tahfidz', 'Presensi', 'Perizinan', 'Pelanggaran', 'Kesehatan', 'Billing', 'Pengumuman', 'QR Scanner', 'Jadwal', 'Notifikasi', 'Pengaturan'],
            'wali_murid' => ['Dashboard', 'Anak Saya', 'Akademik', 'Raport', 'Presensi', 'Perizinan', 'Pembayaran', 'Tagihan', 'Riwayat Pembayaran', 'Pelanggaran', 'Kesehatan', 'Jadwal Anak', 'Notifikasi', 'Profil'],
            'musyrif' => ['Dashboard', 'Santri Binaan', 'Kamar', 'Absensi Asrama', 'Perizinan', 'Pelanggaran', 'Catatan Harian', 'Mutasi Kamar', 'Jadwal Piket', 'Pengumuman', 'Laporan', 'Pengaturan'],
            'guru' => ['Dashboard', 'Jadwal Mengajar', 'Kelas Saya', 'Presensi Kelas', 'Nilai', 'Tugas', 'Materi', 'Raport', 'Catatan Siswa', 'Pengumuman', 'Profil', 'Pengaturan'],
            'klinik' => ['Dashboard', 'Antrian', 'Rekam Medis', 'Pemeriksaan', 'Obat', 'Riwayat Berobat', 'Surat Sakit', 'Rujukan', 'Jadwal Dokter', 'Stok Obat', 'Pengumuman', 'Laporan Kesehatan', 'Profil', 'Pengaturan'],
            default => [],
        };
    }
}
