<?php

namespace App\Services\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

trait DashboardSharedDataConcern
{
    public function shared(?Request $request = null, ?string $panel = null, ?string $module = null): array
    {
        if ($panel === null) {
            return $this->fullShared($request);
        }

        $keys = $module === null
            ? $this->dashboardSharedKeys($panel)
            : $this->moduleSharedKeys($panel, $module);

        if ($panel !== 'super-admin') {
            $keys[] = 'notifications';
        }

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
        $special = match (true) {
            $panel === 'super-admin' && $module === 'aktivitas-sistem' => ['systemActivities'],
            $panel === 'super-admin' && $module === 'audit-logs' => ['auditLogs'],
            $panel === 'super-admin' && $module === 'login-monitoring' => ['loginMonitoring'],
            $panel === 'super-admin' && $module === 'error-logs' => ['errorLogs'],
            $panel === 'owner' && $module === 'audit-tenant' => ['auditLogs'],
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
        if (in_array($key, ['systemActivities', 'loginMonitoring', 'errorLogs'], true)) {
            abort_unless($request?->user()?->hasRole('super_admin'), 403, 'Dataset telemetry platform hanya tersedia untuk super admin.');
        }

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
            'subscriptions' => $this->subscriptions($request),
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
        $query = $this->query($request, 'notification_logs');
        if ($request?->user()) {
            $userId = $request->user()->id;
            $query->leftJoin('notification_receipts', function ($join) use ($userId) {
                $join->on('notification_receipts.notification_id', '=', 'notification_logs.id')
                    ->where('notification_receipts.user_id', '=', $userId);
            });
            $query->where(fn ($nested) => $nested
                ->whereNull('notification_logs.user_id')
                ->orWhere('notification_logs.user_id', $userId));
            $query->whereRaw('COALESCE(notification_logs.read_at, notification_receipts.read_at) IS NULL');
        } else {
            $query->whereNull('notification_logs.user_id')->whereNull('notification_logs.read_at');
        }

        return $query->select('notification_logs.*')->latest('notification_logs.id')->limit(5)->get();
    }
}
