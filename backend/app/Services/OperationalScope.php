<?php

namespace App\Services;

use App\Support\ImportRowScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves an actor's row-level operational scope without a Request instance.
 *
 * The synchronous read path applies this scope through DashboardScopeConcern.
 * Queued operations run outside a request lifecycle, so they need the same
 * rules resolved directly from current database state. The queued actor context
 * is never trusted as the source of truth here; only the role that has already
 * been re-verified against live membership rows is accepted.
 */
class OperationalScope
{
    /**
     * Roles whose access is limited to explicitly assigned rows.
     */
    private const SCOPED_ROLES = ['guru', 'musyrif', 'santri', 'wali_murid'];

    /** @var array<string, array<int, string>> */
    private array $columns = [];

    /** @var array<string, bool> */
    private array $tables = [];

    /**
     * Build the row scope that a queued write against $table must satisfy.
     */
    public function importRowScope(?int $tenantId, int $userId, string $role, string $table): ImportRowScope
    {
        if (! in_array($role, self::SCOPED_ROLES, true)) {
            return ImportRowScope::unrestricted();
        }

        if (! $tenantId) {
            return ImportRowScope::denyAll('actor dengan cakupan penugasan tidak memiliki tenant aktif.');
        }

        if (in_array($role, ['santri', 'wali_murid'], true)) {
            return ImportRowScope::denyAll('role personal tidak dapat membuat baris baru melalui operasi antrean.');
        }

        return $role === 'guru'
            ? $this->teacherImportScope($tenantId, $userId, $table)
            : $this->dormImportScope($tenantId, $userId, $table);
    }

    private function teacherImportScope(int $tenantId, int $userId, string $table): ImportRowScope
    {
        if (in_array($table, ['teachers', 'classes', 'subjects'], true)) {
            return ImportRowScope::denyAll(
                "role guru tidak dapat membuat baris {$table} baru karena penugasan hanya memberi akses pada baris yang sudah ada."
            );
        }

        $scope = $this->teacherScope($tenantId, $userId);
        $columns = $this->columns($table);
        $allowed = [];

        if ($table === 'students') {
            $allowed['class_id'] = $scope['class_ids'];

            return ImportRowScope::restricted($allowed);
        }

        if (in_array('student_id', $columns, true)) {
            $allowed['student_id'] = $scope['student_ids'];
        }
        if (in_array('class_id', $columns, true)) {
            $allowed['class_id'] = $scope['class_ids'];
        }
        if (in_array('subject_id', $columns, true)) {
            $allowed['subject_id'] = $scope['subject_ids'];
        }

        if ($allowed === []) {
            return ImportRowScope::denyAll(
                "tabel {$table} tidak memiliki kolom cakupan penugasan guru sehingga import antrean tidak dapat dibatasi."
            );
        }

        return ImportRowScope::restricted($allowed);
    }

    private function dormImportScope(int $tenantId, int $userId, string $table): ImportRowScope
    {
        if ($table === 'rooms') {
            return ImportRowScope::denyAll(
                'role musyrif tidak dapat membuat kamar baru karena penugasan hanya memberi akses pada kamar binaan.'
            );
        }

        $scope = $this->dormScope($tenantId, $userId);
        $columns = $this->columns($table);
        $allowed = [];

        if ($table === 'students') {
            return ImportRowScope::restricted(['room' => $scope['room_names']]);
        }

        if (in_array('room_id', $columns, true)) {
            $allowed['room_id'] = $scope['room_ids'];
        }
        if (in_array('student_id', $columns, true)) {
            $allowed['student_id'] = $scope['student_ids'];
        }

        if ($allowed === []) {
            return ImportRowScope::denyAll(
                "tabel {$table} tidak memiliki kolom cakupan penugasan musyrif sehingga import antrean tidak dapat dibatasi."
            );
        }

        return ImportRowScope::restricted($allowed);
    }

    /**
     * @return array{teacher_ids: array<int, int>, subject_ids: array<int, int>, class_ids: array<int, int>, student_ids: array<int, int>}
     */
    private function teacherScope(int $tenantId, int $userId): array
    {
        $teacherIds = DB::table('teachers')
            ->where('pesantren_id', $tenantId)
            ->where('user_id', $userId)
            ->pluck('id')
            ->all();

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

        $classIds = $assignments->pluck('class_id')->filter()->unique()->values()->all();
        $subjectIds = $assignments->pluck('subject_id')->filter()->unique()->values()->all();

        $studentIds = DB::table('students')
            ->where('pesantren_id', $tenantId)
            ->whereIn('class_id', $classIds ?: [0])
            ->pluck('id')
            ->all();

        return [
            'teacher_ids' => $teacherIds,
            'subject_ids' => $subjectIds,
            'class_ids' => $classIds,
            'student_ids' => $studentIds,
        ];
    }

    /**
     * @return array{room_ids: array<int, int>, room_names: array<int, string>, student_ids: array<int, int>}
     */
    private function dormScope(int $tenantId, int $userId): array
    {
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

        $roomNames = $rooms->pluck('name')->filter()->values()->all();
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
    }

    /**
     * @return array<int, string>
     */
    private function columns(string $table): array
    {
        return $this->columns[$table] ??= Schema::getColumnListing($table);
    }

    private function hasTable(string $table): bool
    {
        return $this->tables[$table] ??= Schema::hasTable($table);
    }
}
