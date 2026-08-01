<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Support\PrivilegedAccess;
use App\Support\ResourceCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

trait CrudAuthorizationConcern
{
    private function table(string $resource): string
    {
        abort_unless(in_array($resource, ResourceCatalog::ALL, true), 404);

        return match ($resource) {
            'academic-years' => 'academic_years',
            'tenant-modules' => 'tenant_modules',
            'memberships' => 'pesantren_user',
            'room-assignments' => 'room_assignments',
            'dorm-attendances' => 'dorm_attendances',
            'report-cards' => 'report_cards',
            'memorization-records' => 'memorization_records',
            'daily-notes' => 'daily_notes',
            'invoice-items' => 'invoice_items',
            'payment-methods' => 'payment_methods',
            'bank-accounts' => 'bank_accounts',
            'expense-categories' => 'expense_categories',
            'cashflow-entries' => 'cashflow_entries',
            'room-mutations' => 'room_mutations',
            'medicine-stocks' => 'medicine_stocks',
            'medical-queues' => 'medical_queues',
            'medical-records' => 'medical_records',
            'medical-examinations' => 'medical_examinations',
            'sick-letters' => 'sick_letters',
            'audit-logs' => 'audit_logs',
            'system-settings' => 'system_settings',
            'notifications' => 'notification_logs',
            default => str_replace('-', '_', $resource),
        };
    }

    private function scopedQuery(Request $request, string $table)
    {
        $query = DB::table($table)->when($this->hasColumn($table, 'deleted_at'), fn ($q) => $q->whereNull("{$table}.deleted_at"));

        $query = $this->tenantContext->applyScope($request, $query, $table);
        $this->applyProtectedSettingScope($request, $query, $table);

        return $this->applyPersonalScope($request, $query, $table);
    }

    private function applyPersonalScope(Request $request, $query, string $table)
    {
        $role = $this->tenantContext->activeRole($request);
        if (! $role) {
            return $query;
        }

        if ($table === 'notification_logs' && ! in_array($role, ['super_admin', 'owner_pesantren'], true)) {
            $userId = (int) $request->user()?->id;

            return $query->where(function ($notificationQuery) use ($userId) {
                $notificationQuery->whereNull('notification_logs.user_id')
                    ->orWhere('notification_logs.user_id', $userId);
            });
        }

        if (in_array($role, ['santri', 'wali_murid'], true)) {
            return $this->applyStudentFamilyScope($request, $query, $table);
        }

        if ($role === 'guru') {
            return $this->applyTeacherScope($request, $query, $table);
        }

        if ($role === 'musyrif') {
            return $this->applyDormScope($request, $query, $table);
        }

        return $query;
    }

    private function applyStudentFamilyScope(Request $request, $query, string $table)
    {
        $studentIds = $this->accessibleStudentIds($request);
        $classIds = $this->requestMemo($request, 'personal-class-ids', fn () => DB::table('students')
            ->whereIn('id', $studentIds ?: [0])
            ->pluck('class_id')->filter()->values()->all());

        if ($table === 'students') {
            return $query->whereIn('students.id', $studentIds ?: [0]);
        }

        if ($this->hasColumn($table, 'student_id')) {
            return $query->whereIn("{$table}.student_id", $studentIds ?: [0]);
        }

        if ($table === 'schedules') {
            return $query->whereIn('schedules.class_id', $classIds ?: [0]);
        }

        if ($table === 'invoice_items') {
            return $query->whereExists(function ($subQuery) use ($studentIds, $table) {
                $subQuery->selectRaw('1')
                    ->from('invoices')
                    ->whereColumn('invoices.id', "{$table}.invoice_id")
                    ->whereIn('invoices.student_id', $studentIds ?: [0]);
            });
        }

        if (in_array($table, ['medical_examinations', 'prescriptions'], true)) {
            return $query->whereExists(function ($subQuery) use ($studentIds, $table) {
                $subQuery->selectRaw('1')
                    ->from('medical_records')
                    ->whereColumn('medical_records.id', "{$table}.medical_record_id")
                    ->whereIn('medical_records.student_id', $studentIds ?: [0]);
            });
        }

        return $query;
    }

    private function applyTeacherScope(Request $request, $query, string $table)
    {
        $scope = $this->teacherScope($request);

        if ($table === 'teachers') {
            return $query->whereIn('teachers.id', $scope['teacher_ids'] ?: [0]);
        }

        if ($table === 'students') {
            return $query->whereIn('students.class_id', $scope['class_ids'] ?: [0]);
        }

        if ($table === 'classes') {
            return $query->whereIn('classes.id', $scope['class_ids'] ?: [0]);
        }

        if ($table === 'subjects') {
            return $query->whereIn('subjects.id', $scope['subject_ids'] ?: [0]);
        }

        if ($this->hasColumn($table, 'student_id')) {
            return $query->whereIn("{$table}.student_id", $scope['student_ids'] ?: [0]);
        }

        if ($this->hasColumn($table, 'class_id')) {
            $query->whereIn("{$table}.class_id", $scope['class_ids'] ?: [0]);
        }

        if ($this->hasColumn($table, 'subject_id')) {
            $query->whereIn("{$table}.subject_id", $scope['subject_ids'] ?: [0]);
        }

        if ($table === 'schedules') {
            return $query->whereIn('schedules.teacher_name', $scope['teacher_names'] ?: ['__none__']);
        }

        return $query;
    }

    private function applyDormScope(Request $request, $query, string $table)
    {
        $scope = $this->dormScope($request);

        if ($table === 'rooms') {
            return $query->whereIn('rooms.id', $scope['room_ids'] ?: [0]);
        }

        if ($table === 'students') {
            return $query->whereIn('students.id', $scope['student_ids'] ?: [0]);
        }

        if ($this->hasColumn($table, 'room_id')) {
            $query->whereIn("{$table}.room_id", $scope['room_ids'] ?: [0]);
        }

        if ($this->hasColumn($table, 'student_id')) {
            $query->whereIn("{$table}.student_id", $scope['student_ids'] ?: [0]);
        }

        return $query;
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

    private function assertPersonalPayload(Request $request, string $table, array $data): void
    {
        $role = $this->tenantContext->activeRole($request);
        if (! in_array($role, ['santri', 'wali_murid'], true)) {
            return;
        }

        $studentIds = $this->accessibleStudentIds($request);

        if (isset($data['student_id'])) {
            abort_unless(in_array((int) $data['student_id'], $studentIds, true), 403, 'Data santri tidak sesuai akses akun.');
        }

        if (isset($data['invoice_id'])) {
            abort_unless(
                DB::table('invoices')->where('id', (int) $data['invoice_id'])->whereIn('student_id', $studentIds ?: [0])->exists(),
                403,
                'Tagihan tidak sesuai akses akun.'
            );
        }

        if (isset($data['medical_record_id'])) {
            abort_unless(
                DB::table('medical_records')->where('id', (int) $data['medical_record_id'])->whereIn('student_id', $studentIds ?: [0])->exists(),
                403,
                'Rekam medis tidak sesuai akses akun.'
            );
        }
    }

    private function assertOperationalPayload(Request $request, string $table, array $data, ?object $current = null): void
    {
        $role = $this->tenantContext->activeRole($request);
        if (! $role) {
            return;
        }

        $this->assertMembershipMutationAllowed($request, $table);
        $this->assertTenantRelations($request, $table, $data);
        $this->assertPaymentPayload($request, $table, $data, $current);

        if ($role === 'guru') {
            $scope = $this->teacherScope($request);
            if (isset($data['student_id'])) {
                abort_unless(in_array((int) $data['student_id'], $scope['student_ids'], true), 403, 'Santri tidak berada dalam kelas guru.');
            }
            if (isset($data['class_id'])) {
                abort_unless(in_array((int) $data['class_id'], $scope['class_ids'], true), 403, 'Kelas tidak sesuai akses guru.');
            }
            if (isset($data['subject_id'])) {
                abort_unless(in_array((int) $data['subject_id'], $scope['subject_ids'], true), 403, 'Mata pelajaran tidak sesuai akses guru.');
            }
        }

        if ($role === 'musyrif') {
            $scope = $this->dormScope($request);
            if (isset($data['student_id'])) {
                abort_unless(in_array((int) $data['student_id'], $scope['student_ids'], true), 403, 'Santri tidak berada dalam binaan musyrif.');
            }
            if (isset($data['room_id'])) {
                abort_unless(in_array((int) $data['room_id'], $scope['room_ids'], true), 403, 'Kamar tidak berada dalam binaan musyrif.');
            }
        }
    }

    private function assertPaymentPayload(Request $request, string $table, array $data, ?object $current = null): void
    {
        if ($table !== 'payments') {
            return;
        }

        $payload = array_merge((array) ($current ?? new \stdClass), $data);
        $invoiceId = (int) ($payload['invoice_id'] ?? 0);
        $studentId = (int) ($payload['student_id'] ?? 0);
        if (! $invoiceId) {
            return;
        }

        $tenantId = (int) ($payload['pesantren_id'] ?? $this->tenantContext->activePesantrenId($request));
        $invoice = DB::table('invoices')
            ->where('id', $invoiceId)
            ->where('pesantren_id', $tenantId)
            ->whereNull('deleted_at')
            ->first();
        abort_unless($invoice, 422, 'Tagihan pembayaran tidak ditemukan pada tenant aktif.');
        abort_unless((int) $invoice->student_id === $studentId, 422, 'Santri pembayaran harus sama dengan pemilik tagihan.');
        abort_unless((float) ($payload['amount'] ?? 0) > 0, 422, 'Nominal pembayaran harus lebih besar dari nol.');
    }

    private function assertTenantRelations(Request $request, string $table, array $data): void
    {
        $tenantId = (int) ($data['pesantren_id'] ?? $this->tenantContext->activePesantrenId($request));
        if (! $tenantId) {
            return;
        }

        foreach ($this->relationTables() as $column => [$relationTable]) {
            if ($column === 'pesantren_id' || ! isset($data[$column]) || $data[$column] === '') {
                continue;
            }

            $relationId = (int) $data[$column];
            if ($relationTable === 'users') {
                $exists = $table === 'pesantren_user'
                    ? DB::table('users')->where('id', $relationId)->whereNull('deleted_at')->exists()
                    : DB::table('pesantren_user')
                        ->where('pesantren_id', $tenantId)
                        ->where('user_id', $relationId)
                        ->where('status', 'active')
                        ->exists();
                abort_unless($exists, 422, 'Relasi user tidak berada pada tenant aktif.');

                continue;
            }

            if (! $this->hasColumn($relationTable, 'pesantren_id')) {
                continue;
            }

            $relation = DB::table($relationTable)
                ->where('id', $relationId)
                ->where('pesantren_id', $tenantId)
                ->when($this->hasColumn($relationTable, 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'));
            abort_unless($relation->exists(), 422, 'Relasi data tidak berada pada tenant aktif.');
        }
    }

    private function assertPlatformMutationAllowed(Request $request, string $table, string $operation, ?object $current = null): void
    {
        $isSuperAdmin = $this->tenantContext->isSuperAdmin($request);

        if ($table === 'users') {
            abort_unless($isSuperAdmin, 403, 'Hanya super admin yang dapat mengelola akun platform.');
            abort_if($operation === 'bulk', 405, 'Akun platform harus dikelola satu per satu agar perlindungan akun aktif dan pencabutan sesi tetap berlaku.');
            abort_if($operation === 'delete' && (int) $current?->id === (int) $request->user()?->id, 422, 'Super admin tidak dapat menghapus akun yang sedang digunakan.');
        }

        if ($table === 'pesantrens') {
            abort_if($operation === 'bulk', 405, 'Lifecycle tenant harus dikelola satu per satu agar audit dan kontrol owner tetap terjaga.');
            abort_if(in_array($operation, ['store', 'delete', 'import'], true) && ! $isSuperAdmin, 403, 'Lifecycle tenant hanya dapat dikelola super admin.');

            if ($operation === 'update' && ! $isSuperAdmin) {
                $tenantId = (int) $this->tenantContext->activePesantrenId($request, 'owner_pesantren');
                abort_unless((int) $current?->id === $tenantId, 403, 'Owner hanya dapat memperbarui profil tenant aktif.');
                $allowed = ['name', 'city', 'address', 'phone', 'email', 'logo', 'metadata'];
                $forbidden = array_diff(array_keys($request->all()), $allowed);
                abort_if($forbidden !== [], 422, 'Owner tidak dapat mengubah status atau field kontrol tenant.');
            }
        }

        if ($table === 'tenant_modules') {
            abort_unless($isSuperAdmin, 403, 'Lifecycle modul tenant hanya dapat dikelola control plane super admin.');
            abort_if(in_array($operation, ['bulk', 'import'], true), 405, 'Modul tenant harus dikelola satu per satu.');
        }

        if ($table === 'pesantren_user' && in_array($operation, ['bulk', 'import'], true)) {
            abort(405, 'Membership harus dikelola satu per satu agar kontrol role dan audit tetap terjaga.');
        }
    }

    private function assertUserTransitionAllowed(Request $request, string $table, array $data, ?object $current = null): void
    {
        if ($table !== 'users' || ! $current) {
            return;
        }

        abort_if(
            (int) $current->id === (int) $request->user()?->id && ($data['status'] ?? $current->status) !== 'active',
            422,
            'Akun super admin yang sedang digunakan tidak dapat dinonaktifkan.'
        );
    }

    private function revokeUserAccessWhenSecurityChanges(string $table, int $userId, array $data): void
    {
        if ($table !== 'users') {
            return;
        }

        $passwordChanged = array_key_exists('password', $data);
        $accountDisabled = array_key_exists('status', $data) && $data['status'] !== 'active';
        if ($passwordChanged || $accountDisabled) {
            $this->accessRevoker->revoke($userId);
        }
    }

    private function assertMembershipTransitionAllowed(Request $request, string $table, array $data, ?object $current = null, bool $deleting = false): void
    {
        if ($table !== 'pesantren_user') {
            return;
        }

        $roleId = array_key_exists('role_id', $data) ? (int) $data['role_id'] : (int) ($current?->role_id ?? 0);
        $roleName = $roleId ? DB::table('roles')->where('id', $roleId)->value('name') : null;
        abort_if($roleName === 'super_admin', 422, 'Role super admin tidak boleh dipasang sebagai membership tenant.');

        $tenantRoleId = array_key_exists('tenant_role_id', $data)
            ? (int) ($data['tenant_role_id'] ?? 0)
            : (int) ($current?->tenant_role_id ?? 0);
        $targetUserId = array_key_exists('user_id', $data)
            ? (int) $data['user_id']
            : (int) ($current?->user_id ?? 0);
        $targetStatus = (string) ($data['status'] ?? $current?->status ?? 'active');
        $tenantIdForRole = (int) ($data['pesantren_id'] ?? $current?->pesantren_id ?? $this->tenantContext->activePesantrenId($request));
        $willBePrivileged = ! $deleting
            && $targetStatus === 'active'
            && ($roleName === 'owner_pesantren' || $this->customRoleIsPrivileged($tenantRoleId, $tenantIdForRole));
        if ($willBePrivileged) {
            $this->assertUsersMfaReady([$targetUserId]);
        }

        if (! $current) {
            return;
        }

        DB::table('pesantrens')->where('id', $current->pesantren_id)->lockForUpdate()->first();

        $currentRole = DB::table('roles')->where('id', $current->role_id)->value('name');

        // A membership that was privileged (owner, or an active custom role
        // granting sensitive write access) is losing that status here, either
        // through demotion or deletion. This is the same class of event that
        // PrivilegedAccess protects against for MFA; record it explicitly so
        // it is not silently indistinguishable from a routine membership edit.
        $wasPrivileged = $current->status === 'active'
            && ($currentRole === 'owner_pesantren' || $this->customRoleIsPrivileged((int) ($current->tenant_role_id ?? 0), (int) $current->pesantren_id));
        if ($wasPrivileged && ! $willBePrivileged) {
            $this->auditPrivilegedMembershipChange($request, $table, $current, $deleting);
        }

        $willRemainOwner = ! $deleting
            && $roleName === 'owner_pesantren'
            && (($data['status'] ?? $current->status) === 'active');

        if ($currentRole !== 'owner_pesantren' || $current->status !== 'active' || $willRemainOwner) {
            return;
        }

        $otherOwners = DB::table('pesantren_user')
            ->join('roles', 'pesantren_user.role_id', '=', 'roles.id')
            ->where('pesantren_user.pesantren_id', $current->pesantren_id)
            ->where('pesantren_user.id', '!=', $current->id)
            ->where('pesantren_user.status', 'active')
            ->where('roles.name', 'owner_pesantren')
            ->exists();
        abort_unless($otherOwners, 422, 'Tenant harus selalu memiliki minimal satu owner aktif.');
    }

    /**
     * Record a dedicated audit entry when a privileged membership (owner, or
     * an active custom role with sensitive write access) is demoted or
     * removed, tagged with the same reasons PrivilegedAccess would report.
     * This keeps role demotion and membership removal auditable the same way
     * MFA-disable attempts already are for privileged accounts.
     */
    private function auditPrivilegedMembershipChange(Request $request, string $table, object $current, bool $deleting): void
    {
        $targetUser = User::find((int) ($current->user_id ?? 0));
        if (! $targetUser) {
            return;
        }

        $this->audit(
            $request,
            $deleting ? 'privileged membership removed' : 'privileged membership demoted',
            $table,
            (int) $current->id,
            $current,
            null,
            ['privileged_reasons' => PrivilegedAccess::reasons($targetUser)]
        );
    }

    private function assertAccessAdministrationStepUp(Request $request, string $table): void
    {
        // Managing tenant memberships and platform user accounts are both
        // sensitive account-administration actions: either can grant, remove,
        // or delete access. Both require a recently verified second factor,
        // not just an authenticated super admin/owner session.
        if (in_array($table, ['pesantren_user', 'users'], true)) {
            $this->stepUpMfa->assert($request, 'membership-administration');
        }
    }

    private function customRoleIsPrivileged(int $tenantRoleId, int $tenantId): bool
    {
        if ($tenantRoleId <= 0) {
            return false;
        }

        $row = DB::table('tenant_roles')->where('id', $tenantRoleId)->where('pesantren_id', $tenantId)->where('status', 'active')->first();
        if (! $row) {
            return false;
        }
        $permissions = json_decode((string) $row->permissions, true) ?: [];

        return ! empty($permissions['write']) || ! empty($permissions['actions']);
    }

    private function assertUsersMfaReady(iterable $userIds): void
    {
        $ids = collect($userIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        $ready = DB::table('users')
            ->whereIn('id', $ids)
            ->where('status', 'active')
            ->where('mfa_enabled', true)
            ->whereNotNull('mfa_secret')
            ->lockForUpdate()
            ->pluck('id');
        $withRecovery = DB::table('mfa_recovery_codes')
            ->whereIn('user_id', $ids)
            ->whereNull('used_at')
            ->lockForUpdate()
            ->pluck('user_id')
            ->unique();
        $notReady = $ids->diff($ready)->merge($ids->diff($withRecovery))->unique()->values();
        abort_if($notReady->isNotEmpty(), 422, 'Seluruh pengguna privileged harus aktif, memiliki MFA, dan recovery code yang belum terpakai.');
    }

    private function assertMembershipMutationAllowed(Request $request, string $table): void
    {
        if ($table !== 'pesantren_user') {
            return;
        }

        abort_unless(
            in_array($this->tenantContext->activeRole($request), ['super_admin', 'owner_pesantren'], true),
            403,
            'Hanya owner atau super admin yang dapat mengelola membership.'
        );
    }

    private function teacherScope(Request $request): array
    {
        return $this->requestMemo($request, 'teacher-scope', function () use ($request) {
            $tenantId = $this->tenantContext->activePesantrenId($request);
            $userId = $request->user()?->id;
            $teachers = DB::table('teachers')->where('pesantren_id', $tenantId)->where('user_id', $userId)->get();
            $teacherIds = $teachers->pluck('id')->all();
            $teacherNames = $teachers->pluck('name')->filter()->values()->all();

            $assignments = $this->hasTable('teacher_assignments')
                ? DB::table('teacher_assignments')
                    ->where('pesantren_id', $tenantId)
                    ->where('status', 'active')
                    ->whereIn('teacher_id', $teacherIds ?: [0])
                    ->where(fn ($query) => $query->whereNull('assigned_at')->orWhere('assigned_at', '<=', today()))
                    ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', today()))
                    ->get()
                : collect();

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
            $roomIds = $this->hasTable('musyrif_room_assignments')
                ? DB::table('musyrif_room_assignments')
                    ->where('pesantren_id', $tenantId)
                    ->where('user_id', $userId)
                    ->where('status', 'active')
                    ->where(fn ($query) => $query->whereNull('assigned_at')->orWhere('assigned_at', '<=', today()))
                    ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', today()))
                    ->pluck('room_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all()
                : [];

            $rooms = DB::table('rooms')
                ->where('pesantren_id', $tenantId)
                ->whereIn('id', $roomIds ?: [0])
                ->get();

            $studentIds = $this->hasTable('room_assignments')
                ? DB::table('room_assignments')
                    ->where('pesantren_id', $tenantId)
                    ->where('status', 'active')
                    ->whereIn('room_id', $roomIds ?: [0])
                    ->where(fn ($query) => $query->whereNull('assigned_at')->orWhere('assigned_at', '<=', today()))
                    ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', today()))
                    ->pluck('student_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all()
                : [];

            return [
                'room_ids' => $rooms->pluck('id')->all(),
                'room_names' => $rooms->pluck('name')->all(),
                'student_ids' => $studentIds,
            ];
        });
    }
}
