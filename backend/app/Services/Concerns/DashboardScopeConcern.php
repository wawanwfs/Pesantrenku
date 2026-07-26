<?php

namespace App\Services\Concerns;

use App\Support\ResourceCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait DashboardScopeConcern
{
    private function query(?Request $request, string $table)
    {
        $query = DB::table($table);

        if ($this->hasColumn($table, 'deleted_at')) {
            $query->whereNull("{$table}.deleted_at");
        }

        if ($request) {
            $resource = ResourceCatalog::resourceForTable($table);
            if ($resource !== null && ! $this->resourceAuthorization->allows($request, $resource, 'read')) {
                return $query->whereRaw('1 = 0');
            }

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

            $today = now()->toDateString();
            $assignments = $this->hasTable('teacher_assignments')
                ? DB::table('teacher_assignments')
                    ->where('pesantren_id', $tenantId)
                    ->where('status', 'active')
                    ->whereIn('teacher_id', $teacherIds ?: [0])
                    ->where(fn ($query) => $query->whereNull('assigned_at')->orWhere('assigned_at', '<=', $today))
                    ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', $today))
                    ->get()
                : collect();

            // Assignment scope is deliberately fail-closed. Names, subjects and
            // schedules are descriptive data, not authorization grants.
            $subjectIds = $assignments->pluck('subject_id')->filter()->unique()->values()->all();
            $classIds = $assignments->pluck('class_id')->filter()->unique()->values()->all();

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
            $today = now()->toDateString();
            $assignedRoomIds = $this->hasTable('musyrif_room_assignments')
                ? DB::table('musyrif_room_assignments')
                    ->where('pesantren_id', $tenantId)
                    ->where('user_id', $userId)
                    ->where('status', 'active')
                    ->where(fn ($query) => $query->whereNull('assigned_at')->orWhere('assigned_at', '<=', $today))
                    ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', $today))
                    ->pluck('room_id')
                    ->filter()
                    ->values()
                    ->all()
                : [];

            $rooms = DB::table('rooms')
                ->where('pesantren_id', $tenantId)
                ->whereIn('id', $assignedRoomIds ?: [0])
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
        $this->schemaColumns[$table] ??= Cache::remember(
            "pesantenku.schema.columns.{$table}",
            now()->addHours(24),
            fn () => Schema::getColumnListing($table)
        );

        return in_array($column, $this->schemaColumns[$table], true);
    }

    private function hasTable(string $table): bool
    {
        return $this->schemaTables[$table] ??= Cache::remember(
            "pesantenku.schema.tables.{$table}",
            now()->addHours(24),
            fn () => Schema::hasTable($table)
        );
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
            $query->where('id', $this->tenantContext->requireActivePesantrenId($request));
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
        $scopedRequest = $request?->user()?->hasRole('super_admin') ? null : $request;

        return $this->query($scopedRequest, 'subscriptions')
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
