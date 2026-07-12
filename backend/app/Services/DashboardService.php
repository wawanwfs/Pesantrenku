<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            'subscriptions' => $this->subscriptions($request),
            'plans' => DB::table('plans')->select('id', 'name', 'code', 'monthly_price', 'max_students', 'max_users', 'status')->limit(10)->get(),
            'modules' => $this->modules($request),
            'settings' => $this->settings($request),
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
            'notifications' => $this->notifications($request),
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
            'klinik' => ['medicalQueues', 'students'],
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
                ->select('audit_logs.id', 'users.name as pengguna', 'audit_logs.action as aktivitas', 'audit_logs.module as modul', 'audit_logs.ip_address as ip', 'audit_logs.created_at as waktu')
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
            'users' => $this->query($request, 'users')->select('id', 'name', 'email', 'username', 'phone', 'status', 'address', 'created_at', 'updated_at')->latest('id')->limit($this->sharedLimit($key))->get(),
            'memberships' => $this->memberships($request),
            'pesantrens' => $this->pesantrens($request),
            'modules' => $this->modules($request),
            'systemActivities' => $this->systemActivities(),
            'loginMonitoring' => $this->loginMonitoring(),
            'errorLogs' => $this->errorLogs(),
            'settings' => $this->settings($request),
            'notifications' => $this->notifications($request),
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

    private function settings(?Request $request)
    {
        $query = $this->query($request, 'system_settings');
        if ($request && ! $request->user()?->hasRole('super_admin')) {
            foreach (['permissions.', 'security.', 'auth.', 'backup-security.'] as $prefix) {
                $query->where('key', 'not like', $prefix.'%');
            }
        }

        return $query->select('id', 'key as kunci', 'value as nilai', 'updated_at as diperbarui_pada')->latest('id')->limit(10)->get();
    }

    private function notifications(?Request $request)
    {
        $query = $this->query($request, 'notification_logs')->whereNull('read_at');
        if ($request?->user()) {
            $userId = $request->user()->id;
            $query->where(fn ($nested) => $nested->whereNull('user_id')->orWhere('user_id', $userId));
        } else {
            $query->whereNull('user_id');
        }

        return $query->latest('id')->limit(5)->get();
    }

    private function superAdmin(): array
    {
        $pesantrenCount = $this->query(null, 'pesantrens')->count();
        $userCount = $this->query(null, 'users')->count();
        $studentCount = $this->query(null, 'students')->count();
        $monthlyPayments = $this->query(null, 'payments')
            ->where('status', 'success')
            ->where('paid_at', '>=', now()->startOfMonth())
            ->get();
        $monthlyRevenue = (float) $monthlyPayments->sum('amount');

        return [
            'role' => 'super_admin',
            'title' => 'Dashboard',
            'subtitle' => 'Ringkasan sistem Pesantenku',
            'stats' => [
                ['label' => 'Total Pesantren', 'value' => number_format($pesantrenCount, 0, ',', '.'), 'trend' => 'Pesantren terdaftar', 'icon' => 'School', 'tone' => 'info'],
                ['label' => 'Total User', 'value' => number_format($userCount, 0, ',', '.'), 'trend' => 'Akun platform tersimpan', 'icon' => 'Users', 'tone' => 'success'],
                ['label' => 'Total Santri', 'value' => number_format($studentCount, 0, ',', '.'), 'trend' => 'Seluruh tenant', 'icon' => 'Box', 'tone' => 'purple'],
                ['label' => 'Transaksi Bulan Ini', 'value' => number_format($monthlyPayments->count(), 0, ',', '.'), 'trend' => 'Pembayaran sukses', 'icon' => 'PieChart', 'tone' => 'warning'],
                ['label' => 'Pendapatan Bulan Ini', 'value' => $this->rupiah($monthlyRevenue), 'trend' => 'Nilai transaksi sukses', 'icon' => 'Wallet', 'tone' => 'success'],
                ['label' => 'Status Data', 'value' => 'Sinkron', 'trend' => 'Terhubung ke database aktif', 'icon' => 'Server', 'tone' => 'info'],
            ],
            'lineChart' => $this->platformChart(),
            'donut' => $this->roleDistribution(),
            'tables' => [
                'pesantren' => $this->query(null, 'pesantrens')
                    ->leftJoin('students', function ($join) {
                        $join->on('students.pesantren_id', '=', 'pesantrens.id')->whereNull('students.deleted_at');
                    })
                    ->select('pesantrens.name as Nama Pesantren', 'pesantrens.city as Kota', DB::raw('COUNT(students.id) as Santri'), 'pesantrens.created_at as Bergabung', 'pesantrens.status as Status')
                    ->groupBy('pesantrens.id', 'pesantrens.name', 'pesantrens.city', 'pesantrens.created_at', 'pesantrens.status')
                    ->latest('pesantrens.id')
                    ->limit(5)
                    ->get(),
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
            'lineChart' => $this->tenantActivityChart($request),
            'donut' => $this->moduleDistribution($request),
            'quickActions' => ['Tambah User', 'Modul Aktif', 'Paket & Subscription', 'Audit Tenant', 'Pengaturan'],
        ] + $this->sharedWidgets($request);
    }

    private function finance(?Request $request): array
    {
        $income = (float) $this->query($request, 'payments')->where('status', 'success')->sum('amount');
        $expenses = (float) $this->query($request, 'expenses')->sum('amount');
        $arrearsQuery = $this->query($request, 'invoices')->where('status', 'unpaid');
        $arrears = (float) (clone $arrearsQuery)->sum('amount');
        $arrearsStudents = (clone $arrearsQuery)->distinct()->count('student_id');
        $paidStudents = $this->query($request, 'payments')
            ->where('status', 'success')
            ->where('paid_at', '>=', now()->startOfMonth())
            ->distinct()
            ->count('student_id');

        return [
            'role' => 'admin_keuangan',
            'title' => 'Dashboard',
            'subtitle' => 'Ringkasan keuangan pesantren',
            'stats' => [
                ['label' => 'Total Pemasukan', 'value' => $this->rupiah($income), 'trend' => 'Pembayaran sukses', 'icon' => 'Wallet', 'tone' => 'success'],
                ['label' => 'Total Pengeluaran', 'value' => $this->rupiah($expenses), 'trend' => 'Pengeluaran tercatat', 'icon' => 'LogOut', 'tone' => 'error'],
                ['label' => 'Saldo Kas', 'value' => $this->rupiah($income - $expenses), 'trend' => 'Pemasukan dikurangi pengeluaran', 'icon' => 'CreditCard', 'tone' => 'info'],
                ['label' => 'Tunggakan', 'value' => $this->rupiah($arrears), 'trend' => number_format($arrearsStudents, 0, ',', '.').' santri menunggak', 'icon' => 'TriangleAlert', 'tone' => 'warning'],
                ['label' => 'Lunas Bulan Ini', 'value' => number_format($paidStudents, 0, ',', '.').' Santri', 'trend' => 'Pembayar unik bulan berjalan', 'icon' => 'CircleCheck', 'tone' => 'purple'],
            ],
            'lineChart' => $this->financeChart($request),
            'donut' => $this->paymentMethodDistribution($request),
            'quickActions' => ['Tambah Tagihan', 'Tambah Pembayaran', 'Laporan Pemasukan', 'Laporan Tunggakan', 'Pengaturan Umum'],
        ] + $this->sharedWidgets($request);
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
                ['label' => 'Total Siswa', 'value' => number_format($studentCount, 0, ',', '.'), 'trend' => 'Data tenant aktif', 'icon' => 'Users', 'tone' => 'success'],
                ['label' => 'Total Guru', 'value' => number_format($teacherCount, 0, ',', '.'), 'trend' => 'Guru terdaftar', 'icon' => 'GraduationCap', 'tone' => 'info'],
                ['label' => 'Total Kelas', 'value' => number_format($classCount, 0, ',', '.'), 'trend' => 'Seluruh tahun ajaran', 'icon' => 'School', 'tone' => 'purple'],
                ['label' => 'Mata Pelajaran', 'value' => number_format($subjectCount, 0, ',', '.'), 'trend' => 'Semua jenjang', 'icon' => 'BookOpen', 'tone' => 'warning'],
                ['label' => 'Kelas Aktif', 'value' => number_format($this->query($request, 'classes')->where('status', 'active')->count(), 0, ',', '.'), 'trend' => 'Status aktif', 'icon' => 'BadgeCheck', 'tone' => 'success'],
                ['label' => 'Rata-rata Nilai', 'value' => number_format($average, 2, ',', '.'), 'trend' => $average > 0 ? 'Dari nilai tersimpan' : 'Belum ada nilai', 'icon' => 'BarChart3', 'tone' => 'info'],
            ],
            'lineChart' => $this->studentTrend($request),
            'donut' => $this->classLevelDistribution($request),
            'quickActions' => ['Tambah Kelas', 'Input Nilai', 'Jadwal Pelajaran', 'Presensi', 'Laporan Nilai', 'Pengaturan'],
        ] + $this->sharedWidgets($request);
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
                ['label' => 'Total Santri', 'value' => number_format($studentCount, 0, ',', '.'), 'trend' => 'Data tenant aktif', 'icon' => 'Users', 'tone' => 'success'],
                ['label' => 'Guru & Staf', 'value' => number_format($teacherStaffCount, 0, ',', '.'), 'trend' => 'Personel terdaftar', 'icon' => 'UserCog', 'tone' => 'info'],
                ['label' => 'Kamar', 'value' => number_format($roomCount, 0, ',', '.'), 'trend' => 'Kamar terdaftar', 'icon' => 'Bed', 'tone' => 'purple'],
                ['label' => 'Kelas', 'value' => number_format($classCount, 0, ',', '.'), 'trend' => 'Seluruh tahun ajaran', 'icon' => 'School', 'tone' => 'warning'],
                ['label' => 'Surat Keluar', 'value' => number_format($outgoingLetters, 0, ',', '.'), 'trend' => 'Seluruh surat tercatat', 'icon' => 'Send', 'tone' => 'success'],
                ['label' => 'Surat Masuk', 'value' => number_format($incomingLetters, 0, ',', '.'), 'trend' => 'Seluruh surat tercatat', 'icon' => 'Inbox', 'tone' => 'info'],
            ],
            'lineChart' => $this->studentTrend($request),
            'quickActions' => ['Tambah Santri', 'Surat Menyurat', 'Mutasi Santri', 'Kamar', 'Pengumuman', 'Pengaturan'],
        ] + $this->sharedWidgets($request);
    }

    private function mobile(string $role, ?Request $request): array
    {
        $user = $request?->user();
        $student = $this->query($request, 'students')->first();
        $teacher = $role === 'guru' ? $this->query($request, 'teachers')->first() : null;
        $students = $this->query($request, 'students')
            ->leftJoin('classes', 'students.class_id', '=', 'classes.id')
            ->select('students.id', 'students.nis', 'students.name', 'students.gender', 'classes.name as kelas', 'students.room as kamar', 'students.status')
            ->limit(5)
            ->get();
        $schedules = $this->query($request, 'schedules')
            ->leftJoin('classes', 'schedules.class_id', '=', 'classes.id')
            ->leftJoin('subjects', 'schedules.subject_id', '=', 'subjects.id')
            ->select('schedules.id', 'classes.name as kelas', 'subjects.name as mata_pelajaran', 'schedules.day as hari', 'schedules.time as waktu', 'schedules.teacher_name as guru', 'schedules.room_name as ruang', 'schedules.type as jenis')
            ->limit(5)
            ->get();
        $invoices = $this->query($request, 'invoices')
            ->leftJoin('students', 'invoices.student_id', '=', 'students.id')
            ->select('invoices.id', 'invoices.invoice_number as nomor_tagihan', 'students.name as santri', 'invoices.period as periode', 'invoices.amount as nominal', 'invoices.due_date as jatuh_tempo', 'invoices.status')
            ->limit(5)
            ->get();
        $medical = $this->query($request, 'medical_records')
            ->leftJoin('students', 'medical_records.student_id', '=', 'students.id')
            ->select('medical_records.id', 'students.name as santri', 'medical_records.complaint as keluhan', 'medical_records.diagnosis as diagnosis', 'medical_records.date as tanggal', 'medical_records.doctor_name as dokter')
            ->limit(5)
            ->get();
        $profileName = $teacher?->name ?? $user?->name ?? 'Pengguna';
        $profileSubtitle = match ($role) {
            'santri' => collect([$student?->room, $student?->nis])->filter()->implode(' - ') ?: 'Profil santri',
            'wali_murid' => $student ? 'Wali dari '.$student->name : 'Profil wali murid',
            'guru' => $teacher?->subject ? 'Guru '.$teacher->subject : 'Profil guru',
            'musyrif' => 'Pendamping asrama',
            'klinik' => 'Petugas layanan kesehatan',
            default => 'Pengguna Pesantenku',
        };

        return [
            'role' => $role,
            'profile' => ['name' => $profileName, 'subtitle' => $profileSubtitle],
            'summary' => $this->mobileSummary($role, $request),
            'menu' => $this->mobileMenus($role),
            'announcements' => $this->query($request, 'announcements')->latest('id')->limit(5)->get(),
            'students' => $students,
            'schedules' => $schedules,
            'violations' => $this->query($request, 'violations')->limit(5)->get(),
            'invoices' => $invoices,
            'medical' => $medical,
        ];
    }

    private function sharedWidgets(?Request $request = null): array
    {
        $activities = $this->query($request, 'audit_logs')
            ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
            ->select('audit_logs.action', 'audit_logs.module', 'audit_logs.created_at', 'users.name')
            ->latest('audit_logs.id')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'title' => ucwords(str_replace('_', ' ', $row->action)),
                'description' => trim(($row->module ? ucwords(str_replace('_', ' ', $row->module)) : 'Sistem').($row->name ? ' - '.$row->name : '')),
                'time' => $this->dateLabel($row->created_at),
            ])
            ->all();

        $moduleUsage = $this->query($request, 'tenant_modules')
            ->select('module', DB::raw('COUNT(*) as total'))
            ->where('is_enabled', true)
            ->groupBy('module')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn ($row) => ['name' => ucwords(str_replace('_', ' ', $row->module)), 'value' => (int) $row->total])
            ->all();

        return [
            'activities' => $activities,
            'moduleUsage' => $moduleUsage,
            'quickActions' => ['Tambah Pesantren', 'Tambah User', 'Assign Role', 'Backup Sekarang', 'Clear Cache', 'System Update', 'Lihat Log Audit', 'Pengaturan Sistem', 'Dokumentasi'],
        ];
    }

    private function systemActivities(): array
    {
        return $this->query(null, 'audit_logs')
            ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
            ->select('audit_logs.created_at as waktu', 'audit_logs.action as aktivitas', 'audit_logs.module as modul', 'users.name as pengguna', 'audit_logs.ip_address as ip')
            ->latest('audit_logs.id')
            ->limit(20)
            ->get()
            ->map(fn ($row) => (array) $row + ['status' => 'Tercatat'])
            ->all();
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
        return $this->query(null, 'audit_logs')
            ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
            ->where(function ($query) {
                $query->where('audit_logs.module', 'auth')->orWhere('audit_logs.action', 'like', '%login%');
            })
            ->select('audit_logs.created_at as waktu', 'users.name as pengguna', 'audit_logs.ip_address as ip', 'audit_logs.action as status')
            ->latest('audit_logs.id')
            ->limit(20)
            ->get()
            ->map(fn ($row) => (array) $row + ['role' => '-', 'perangkat' => '-'])
            ->all();
    }

    private function errorLogs(): array
    {
        return $this->query(null, 'audit_logs')
            ->where(function ($query) {
                $query->where('action', 'like', '%fail%')->orWhere('action', 'like', '%error%');
            })
            ->select('created_at as waktu', 'module as modul', 'action as pesan')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn ($row) => (array) $row + ['level' => 'Error', 'status' => 'Tercatat'])
            ->all();
    }

    private function financeChart(?Request $request): array
    {
        return $this->monthBuckets()->map(function (Carbon $month) use ($request) {
            return [
                'month' => $this->monthLabel($month),
                'Pemasukan' => (float) $this->query($request, 'payments')->where('status', 'success')->whereBetween('paid_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->sum('amount'),
                'Pengeluaran' => (float) $this->query($request, 'expenses')->whereBetween('date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->sum('amount'),
            ];
        })->all();
    }

    private function platformChart(): array
    {
        return $this->monthBuckets()->map(fn (Carbon $month) => [
            'month' => $this->monthLabel($month),
            'Pengguna Baru' => $this->query(null, 'users')->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->count(),
            'Transaksi' => $this->query(null, 'payments')->where('status', 'success')->whereBetween('paid_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->count(),
        ])->all();
    }

    private function tenantActivityChart(?Request $request): array
    {
        return $this->monthBuckets()->map(fn (Carbon $month) => [
            'month' => $this->monthLabel($month),
            'Santri Baru' => $this->query($request, 'students')->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->count(),
            'Transaksi' => $this->query($request, 'payments')->where('status', 'success')->whereBetween('paid_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->count(),
        ])->all();
    }

    private function studentTrend(?Request $request): array
    {
        return $this->monthBuckets()->map(function (Carbon $month) use ($request) {
            $end = $month->copy()->endOfMonth();

            return [
                'month' => $this->monthLabel($month),
                'Santri Baru' => $this->query($request, 'students')->whereBetween('created_at', [$month->copy()->startOfMonth(), $end])->count(),
                'Total Aktif' => $this->query($request, 'students')->where('status', 'active')->where('created_at', '<=', $end)->count(),
            ];
        })->all();
    }

    private function roleDistribution(): array
    {
        return DB::table('roles')
            ->leftJoin('role_user', 'roles.id', '=', 'role_user.role_id')
            ->select('roles.label as name', DB::raw('COUNT(role_user.user_id) as value'))
            ->groupBy('roles.id', 'roles.label')
            ->havingRaw('COUNT(role_user.user_id) > 0')
            ->orderByDesc('value')
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'value' => (int) $row->value])
            ->all();
    }

    private function moduleDistribution(?Request $request): array
    {
        return $this->query($request, 'tenant_modules')
            ->select('module as name', DB::raw('COUNT(*) as value'))
            ->where('is_enabled', true)
            ->groupBy('module')
            ->orderByDesc('value')
            ->get()
            ->map(fn ($row) => ['name' => ucwords(str_replace('_', ' ', $row->name)), 'value' => (int) $row->value])
            ->all();
    }

    private function classLevelDistribution(?Request $request): array
    {
        return $this->query($request, 'classes')
            ->select('level as name', DB::raw('COUNT(*) as value'))
            ->groupBy('level')
            ->orderByDesc('value')
            ->get()
            ->map(fn ($row) => ['name' => $row->name ?: 'Belum ditentukan', 'value' => (int) $row->value])
            ->all();
    }

    private function paymentMethodDistribution(?Request $request): array
    {
        return $this->query($request, 'payments')
            ->leftJoin('payment_methods', 'payments.payment_method_id', '=', 'payment_methods.id')
            ->where('payments.status', 'success')
            ->selectRaw("COALESCE(payment_methods.name, 'Lainnya') as name, SUM(payments.amount) as value")
            ->groupBy('payment_methods.name')
            ->orderByDesc('value')
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'value' => (float) $row->value])
            ->all();
    }

    private function mobileSummary(string $role, ?Request $request): array
    {
        if ($role === 'guru') {
            return [
                ['label' => 'Kelas', 'value' => (string) $this->query($request, 'classes')->count()],
                ['label' => 'Mapel', 'value' => (string) $this->query($request, 'subjects')->count()],
                ['label' => 'Nilai', 'value' => number_format((float) $this->query($request, 'grades')->avg('average_score'), 1, ',', '.')],
                ['label' => 'Tugas', 'value' => (string) $this->query($request, 'assignments')->count()],
            ];
        }

        if ($role === 'musyrif') {
            return [
                ['label' => 'Santri', 'value' => (string) $this->query($request, 'students')->count()],
                ['label' => 'Kamar', 'value' => (string) $this->query($request, 'rooms')->count()],
                ['label' => 'Pelanggaran', 'value' => number_format((float) $this->query($request, 'violations')->sum('points'), 0, ',', '.').' poin'],
                ['label' => 'Izin Aktif', 'value' => (string) $this->query($request, 'permits')->whereIn('status', ['pending', 'approved'])->count()],
            ];
        }

        if ($role === 'klinik') {
            return [
                ['label' => 'Antrian', 'value' => (string) $this->query($request, 'medical_queues')->where('status', 'waiting')->count()],
                ['label' => 'Rekam Medis', 'value' => (string) $this->query($request, 'medical_records')->count()],
                ['label' => 'Obat', 'value' => (string) $this->query($request, 'medicines')->count()],
                ['label' => 'Rujukan', 'value' => (string) $this->query($request, 'referrals')->count()],
            ];
        }

        $average = (float) $this->query($request, 'grades')->avg('average_score');
        $memorization = (int) $this->query($request, 'memorization_records')->max('juz');
        $points = (float) $this->query($request, 'violations')->sum('points');
        $arrears = (float) $this->query($request, 'invoices')->where('status', 'unpaid')->sum('amount');

        return [
            ['label' => 'Nilai', 'value' => $average > 0 ? number_format($average, 1, ',', '.') : '-'],
            ['label' => 'Hafalan', 'value' => $memorization > 0 ? $memorization.' Juz' : '-'],
            ['label' => 'Pelanggaran', 'value' => number_format($points, 0, ',', '.').' poin'],
            ['label' => 'Tagihan', 'value' => $arrears > 0 ? $this->rupiah($arrears) : 'Lunas'],
        ];
    }

    private function monthBuckets()
    {
        return collect(range(5, 0))->map(fn (int $offset) => now()->startOfMonth()->subMonths($offset));
    }

    private function monthLabel(Carbon $month): string
    {
        $names = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        return $names[$month->month].' '.$month->year;
    }

    private function dateLabel(mixed $value): string
    {
        if (! $value) {
            return '-';
        }

        try {
            return Carbon::parse($value)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
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
        $role = $this->tenantContext->activeRole($request);
        if (! $role || $role === 'super_admin') {
            return;
        }

        if (in_array($role, ['santri', 'wali_murid'], true)) {
            $this->applyPersonalScope($request, $query, $table);

            return;
        }

        if ($role === 'guru') {
            $this->applyTeacherScope($request, $query, $table);

            return;
        }

        if ($role === 'musyrif') {
            $this->applyDormScope($request, $query, $table);
        }
    }

    private function applyPersonalScope(Request $request, $query, string $table): void
    {
        $role = $this->tenantContext->activeRole($request);
        if (! in_array($role, ['santri', 'wali_murid'], true)) {
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
            $user = $request->user();
            $role = $this->tenantContext->activeRole($request);
            if (! $user || ! $role) {
                return [];
            }

            $tenantId = $this->tenantContext->activePesantrenId($request);

            if ($role === 'santri') {
                return DB::table('students')
                    ->where('pesantren_id', $tenantId)
                    ->where('user_id', $user->id)
                    ->pluck('id')
                    ->all();
            }

            if ($role === 'wali_murid') {
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

        $query = DB::table('pesantren_user')
            ->join('pesantrens', 'pesantren_user.pesantren_id', '=', 'pesantrens.id')
            ->join('users', 'pesantren_user.user_id', '=', 'users.id')
            ->join('roles', 'pesantren_user.role_id', '=', 'roles.id')
            ->leftJoin('tenant_roles', 'pesantren_user.tenant_role_id', '=', 'tenant_roles.id')
            ->whereNull('pesantrens.deleted_at')
            ->select([
                'pesantren_user.id',
                'pesantren_user.pesantren_id',
                'pesantren_user.user_id',
                'pesantren_user.role_id',
                'pesantrens.name as pesantren',
                'users.name as pengguna',
                'users.email',
                'roles.label as role',
                'tenant_roles.label as tenant_role',
                'pesantren_user.status',
            ]);

        if (! $request->user()->hasRole('super_admin')) {
            $query->where('pesantren_user.pesantren_id', $this->tenantContext->activePesantrenId($request));
        }

        return $query
            ->latest('pesantren_user.id')
            ->limit(20)
            ->get();
    }

    private function subscriptions(?Request $request)
    {
        return $this->query($request, 'subscriptions')
            ->leftJoin('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->leftJoin('pesantrens', 'subscriptions.pesantren_id', '=', 'pesantrens.id')
            ->select([
                'subscriptions.id',
                'subscriptions.pesantren_id',
                'subscriptions.plan_id',
                'subscriptions.status',
                'subscriptions.starts_at',
                'subscriptions.ends_at',
                'subscriptions.amount',
                'plans.name as plan_name',
                'pesantrens.name as pesantren_name',
            ])
            ->latest('subscriptions.id')
            ->limit(10)
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
