<?php

namespace App\Services\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

trait DashboardAnalyticsConcern
{
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
}
