<?php

namespace App\Services\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

trait DashboardAnalyticsConcern
{
    /** SQL tahun/bulan lintas mesin basis data (produksi MySQL; SQLite untuk dev/e2e). */
    private function sqlYear(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%Y', {$column}) AS INTEGER)",
            'pgsql' => "EXTRACT(YEAR FROM {$column})::int",
            default => "YEAR({$column})",
        };
    }

    private function sqlMonth(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%m', {$column}) AS INTEGER)",
            'pgsql' => "EXTRACT(MONTH FROM {$column})::int",
            default => "MONTH({$column})",
        };
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
        $buckets = $this->monthBuckets();
        $start   = $buckets->first()->copy()->startOfMonth();
        $end     = $buckets->last()->copy()->endOfMonth();

        // 1 query: sum payments per month
        $payments = $this->query($request, 'payments')
            ->where('status', 'success')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start, $end])
            ->selectRaw($this->sqlYear('paid_at') . ' as yr, ' . $this->sqlMonth('paid_at') . ' as mo, SUM(amount) as total')
            ->groupByRaw($this->sqlYear('paid_at') . ', ' . $this->sqlMonth('paid_at'))
            ->get()->keyBy(fn ($r) => "{$r->yr}-{$r->mo}");

        // 1 query: sum expenses per month
        $expenses = $this->query($request, 'expenses')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw($this->sqlYear('date') . ' as yr, ' . $this->sqlMonth('date') . ' as mo, SUM(amount) as total')
            ->groupByRaw($this->sqlYear('date') . ', ' . $this->sqlMonth('date'))
            ->get()->keyBy(fn ($r) => "{$r->yr}-{$r->mo}");

        return $buckets->map(fn (Carbon $month) => [
            'month'       => $this->monthLabel($month),
            'Pemasukan'   => (float) ($payments->get("{$month->year}-{$month->month}")?->total ?? 0),
            'Pengeluaran' => (float) ($expenses->get("{$month->year}-{$month->month}")?->total ?? 0),
        ])->all();
    }

    private function platformChart(): array
    {
        $buckets = $this->monthBuckets();
        $start   = $buckets->first()->copy()->startOfMonth();
        $end     = $buckets->last()->copy()->endOfMonth();

        // 1 query: new users per month
        $users = $this->query(null, 'users')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw($this->sqlYear('created_at') . ' as yr, ' . $this->sqlMonth('created_at') . ' as mo, COUNT(*) as cnt')
            ->groupByRaw($this->sqlYear('created_at') . ', ' . $this->sqlMonth('created_at'))
            ->get()->keyBy(fn ($r) => "{$r->yr}-{$r->mo}");

        // 1 query: successful payment count per month
        $payments = $this->query(null, 'payments')
            ->where('status', 'success')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start, $end])
            ->selectRaw($this->sqlYear('paid_at') . ' as yr, ' . $this->sqlMonth('paid_at') . ' as mo, COUNT(*) as cnt')
            ->groupByRaw($this->sqlYear('paid_at') . ', ' . $this->sqlMonth('paid_at'))
            ->get()->keyBy(fn ($r) => "{$r->yr}-{$r->mo}");

        return $buckets->map(fn (Carbon $month) => [
            'month'         => $this->monthLabel($month),
            'Pengguna Baru' => (int) ($users->get("{$month->year}-{$month->month}")?->cnt ?? 0),
            'Transaksi'     => (int) ($payments->get("{$month->year}-{$month->month}")?->cnt ?? 0),
        ])->all();
    }

    private function tenantActivityChart(?Request $request): array
    {
        $buckets = $this->monthBuckets();
        $start   = $buckets->first()->copy()->startOfMonth();
        $end     = $buckets->last()->copy()->endOfMonth();

        // 1 query: new students per month
        $students = $this->query($request, 'students')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw($this->sqlYear('created_at') . ' as yr, ' . $this->sqlMonth('created_at') . ' as mo, COUNT(*) as cnt')
            ->groupByRaw($this->sqlYear('created_at') . ', ' . $this->sqlMonth('created_at'))
            ->get()->keyBy(fn ($r) => "{$r->yr}-{$r->mo}");

        // 1 query: successful payment count per month
        $payments = $this->query($request, 'payments')
            ->where('status', 'success')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start, $end])
            ->selectRaw($this->sqlYear('paid_at') . ' as yr, ' . $this->sqlMonth('paid_at') . ' as mo, COUNT(*) as cnt')
            ->groupByRaw($this->sqlYear('paid_at') . ', ' . $this->sqlMonth('paid_at'))
            ->get()->keyBy(fn ($r) => "{$r->yr}-{$r->mo}");

        return $buckets->map(fn (Carbon $month) => [
            'month'       => $this->monthLabel($month),
            'Santri Baru' => (int) ($students->get("{$month->year}-{$month->month}")?->cnt ?? 0),
            'Transaksi'   => (int) ($payments->get("{$month->year}-{$month->month}")?->cnt ?? 0),
        ])->all();
    }

    private function studentTrend(?Request $request): array
    {
        $buckets = $this->monthBuckets();
        $start   = $buckets->first()->copy()->startOfMonth();
        $end     = $buckets->last()->copy()->endOfMonth();

        // 1 query: new students per month
        $newByMonth = $this->query($request, 'students')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw($this->sqlYear('created_at') . ' as yr, ' . $this->sqlMonth('created_at') . ' as mo, COUNT(*) as cnt')
            ->groupByRaw($this->sqlYear('created_at') . ', ' . $this->sqlMonth('created_at'))
            ->get()->keyBy(fn ($r) => "{$r->yr}-{$r->mo}");

        // 1 query: all active student created_at values for cumulative count in PHP
        $activeDates = $this->query($request, 'students')
            ->where('status', 'active')
            ->where('created_at', '<=', $end)
            ->pluck('created_at');

        return $buckets->map(function (Carbon $month) use ($newByMonth, $activeDates) {
            $monthEnd = $month->copy()->endOfMonth()->toDateTimeString();

            return [
                'month'       => $this->monthLabel($month),
                'Santri Baru' => (int) ($newByMonth->get("{$month->year}-{$month->month}")?->cnt ?? 0),
                'Total Aktif' => $activeDates->filter(fn ($d) => $d <= $monthEnd)->count(),
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
