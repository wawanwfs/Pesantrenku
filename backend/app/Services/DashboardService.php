<?php

namespace App\Services;

use App\Services\Concerns\DashboardAnalyticsConcern;
use App\Services\Concerns\DashboardScopeConcern;
use App\Services\Concerns\DashboardSharedDataConcern;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    use DashboardAnalyticsConcern, DashboardScopeConcern, DashboardSharedDataConcern;

    private array $schemaColumns = [];

    private array $schemaTables = [];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ResourceAuthorization $resourceAuthorization,
    ) {}

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
            default => abort(404),
        };
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
}
