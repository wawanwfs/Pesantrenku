<?php

namespace Tests\Feature;

use App\Services\OperationalScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Queued writes run outside a request, so the row scope of a scoped actor has
 * to be rebuilt from live database state. These tests pin down that rebuild.
 *
 * The decisive property is fail-closed behaviour: an assignment that has ended,
 * has not started yet, or is no longer active must collapse the allowed set to
 * nothing rather than fall back to "everything".
 *
 * A fresh OperationalScope instance is used per assertion because the class
 * memoises column and table lookups per instance.
 */
class OperationalScopeTest extends TestCase
{
    use RefreshDatabase;

    private const OUTSIDE_ID = 999999;

    public function test_unscoped_roles_are_never_restricted(): void
    {
        $this->seed();
        $tenantId = $this->tenantId();

        foreach (['super_admin', 'owner_pesantren', 'admin_tu', 'admin_keuangan', 'klinik'] as $role) {
            $scope = (new OperationalScope())->importRowScope($tenantId, 1, $role, 'students');

            $this->assertNull(
                $scope->deniedReason(['class_id' => self::OUTSIDE_ID]),
                "role {$role} must not be limited to assigned rows",
            );
        }
    }

    public function test_scoped_role_without_an_active_tenant_is_denied(): void
    {
        $this->seed();

        $scope = (new OperationalScope())->importRowScope(null, 1, 'guru', 'students');

        $this->assertSame(
            'actor dengan cakupan penugasan tidak memiliki tenant aktif.',
            $scope->deniedReason(['class_id' => 1]),
        );
    }

    public function test_personal_roles_cannot_create_rows_through_queued_operations(): void
    {
        $this->seed();
        $tenantId = $this->tenantId();

        foreach (['santri', 'wali_murid'] as $role) {
            $scope = (new OperationalScope())->importRowScope($tenantId, 1, $role, 'students');

            $this->assertSame(
                'role personal tidak dapat membuat baris baru melalui operasi antrean.',
                $scope->deniedReason(['class_id' => 1]),
                "role {$role} must be refused outright",
            );
        }
    }

    public function test_teacher_cannot_create_reference_rows(): void
    {
        $this->seed();
        $tenantId = $this->tenantId();
        $guru = $this->userWithRole('guru');

        foreach (['teachers', 'classes', 'subjects'] as $table) {
            $scope = (new OperationalScope())->importRowScope($tenantId, $guru, 'guru', $table);
            $reason = $scope->deniedReason([]);

            $this->assertNotNull($reason);
            $this->assertStringContainsString($table, $reason);
        }
    }

    public function test_musyrif_cannot_create_rooms(): void
    {
        $this->seed();
        $tenantId = $this->tenantId();
        $musyrif = $this->userWithRole('musyrif');

        $scope = (new OperationalScope())->importRowScope($tenantId, $musyrif, 'musyrif', 'rooms');

        $this->assertSame(
            'role musyrif tidak dapat membuat kamar baru karena penugasan hanya memberi akses pada kamar binaan.',
            $scope->deniedReason([]),
        );
    }

    public function test_teacher_may_only_import_students_into_assigned_classes(): void
    {
        $this->seed();
        $tenantId = $this->tenantId();
        $guru = $this->userWithRole('guru');
        $classId = $this->assignTeacherToClass($tenantId, $guru);

        $scope = (new OperationalScope())->importRowScope($tenantId, $guru, 'guru', 'students');

        $this->assertNull($scope->deniedReason(['class_id' => $classId]));
        $this->assertSame(
            'nilai class_id berada di luar penugasan aktif actor.',
            $scope->deniedReason(['class_id' => self::OUTSIDE_ID]),
        );
    }

    public function test_student_row_without_a_class_cannot_be_proven_to_be_in_scope(): void
    {
        $this->seed();
        $tenantId = $this->tenantId();
        $guru = $this->userWithRole('guru');
        $this->assignTeacherToClass($tenantId, $guru);

        $scope = (new OperationalScope())->importRowScope($tenantId, $guru, 'guru', 'students');
        $reason = $scope->deniedReason(['nis' => 'X-001', 'name' => 'Tanpa Kelas']);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('tidak memuat kolom cakupan penugasan', $reason);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function inactiveAssignmentProvider(): array
    {
        return [
            'assignment already ended' => ['ended'],
            'assignment not started yet' => ['future'],
            'assignment revoked' => ['revoked'],
        ];
    }

    /**
     * @dataProvider inactiveAssignmentProvider
     */
    public function test_an_assignment_that_is_not_currently_valid_grants_nothing(string $variant): void
    {
        $this->seed();
        $tenantId = $this->tenantId();
        $guru = $this->userWithRole('guru');
        $classId = $this->assignTeacherToClass($tenantId, $guru);

        $update = match ($variant) {
            'ended' => ['ended_at' => now()->subDay()->toDateString()],
            'future' => ['assigned_at' => now()->addWeek()->toDateString()],
            'revoked' => ['status' => 'revoked'],
        };

        DB::table('teacher_assignments')
            ->where('pesantren_id', $tenantId)
            ->update($update + ['updated_at' => now()]);

        $scope = (new OperationalScope())->importRowScope($tenantId, $guru, 'guru', 'students');

        $this->assertSame(
            'nilai class_id berada di luar penugasan aktif actor.',
            $scope->deniedReason(['class_id' => $classId]),
            'an assignment outside its validity window must not grant access',
        );
    }

    public function test_an_assignment_valid_today_at_its_boundaries_still_grants_access(): void
    {
        $this->seed();
        $tenantId = $this->tenantId();
        $guru = $this->userWithRole('guru');
        $classId = $this->assignTeacherToClass($tenantId, $guru);

        DB::table('teacher_assignments')
            ->where('pesantren_id', $tenantId)
            ->update([
                'assigned_at' => now()->toDateString(),
                'ended_at' => now()->toDateString(),
                'updated_at' => now(),
            ]);

        $scope = (new OperationalScope())->importRowScope($tenantId, $guru, 'guru', 'students');

        $this->assertNull(
            $scope->deniedReason(['class_id' => $classId]),
            'the window is inclusive on both ends',
        );
    }

    public function test_teacher_without_any_assignment_is_denied_every_row(): void
    {
        $this->seed();
        $tenantId = $this->tenantId();
        $guru = $this->userWithRole('guru');
        DB::table('teacher_assignments')->where('pesantren_id', $tenantId)->delete();

        $scope = (new OperationalScope())->importRowScope($tenantId, $guru, 'guru', 'students');
        $classId = (int) DB::table('students')->where('pesantren_id', $tenantId)->value('class_id');

        $this->assertNotNull($scope->deniedReason(['class_id' => $classId]));
    }

    public function test_musyrif_may_only_import_students_into_supervised_rooms(): void
    {
        $this->seed();
        $tenantId = $this->tenantId();
        $musyrif = $this->userWithRole('musyrif');
        $roomName = $this->assignMusyrifToRoom($tenantId, $musyrif);

        $scope = (new OperationalScope())->importRowScope($tenantId, $musyrif, 'musyrif', 'students');

        $this->assertNull($scope->deniedReason(['room' => $roomName]));
        $this->assertSame(
            'nilai room berada di luar penugasan aktif actor.',
            $scope->deniedReason(['room' => 'Kamar Yang Tidak Dibina']),
        );
    }

    public function test_musyrif_without_any_room_assignment_is_denied_every_student_row(): void
    {
        $this->seed();
        $tenantId = $this->tenantId();
        $musyrif = $this->userWithRole('musyrif');
        DB::table('musyrif_room_assignments')->where('pesantren_id', $tenantId)->delete();
        $roomName = (string) DB::table('rooms')->where('pesantren_id', $tenantId)->value('name');

        $scope = (new OperationalScope())->importRowScope($tenantId, $musyrif, 'musyrif', 'students');

        $this->assertNotNull($scope->deniedReason(['room' => $roomName]));
    }

    private function tenantId(): int
    {
        return (int) DB::table('pesantrens')->orderBy('id')->value('id');
    }

    private function userWithRole(string $roleName): int
    {
        $userId = DB::table('pesantren_user')
            ->join('roles', 'roles.id', '=', 'pesantren_user.role_id')
            ->where('roles.name', $roleName)
            ->where('pesantren_user.status', 'active')
            ->value('pesantren_user.user_id');

        $this->assertNotNull($userId, "seeder must provide an active membership with role {$roleName}");

        return (int) $userId;
    }

    /**
     * Replaces the seeded assignments with a single controlled one and returns
     * the class the teacher is now responsible for.
     */
    private function assignTeacherToClass(int $tenantId, int $guruUserId): int
    {
        $teacherId = DB::table('teachers')
            ->where('pesantren_id', $tenantId)
            ->where('user_id', $guruUserId)
            ->value('id');

        $this->assertNotNull($teacherId, 'seeder must link the guru account to a teachers row');

        $classId = (int) DB::table('students')
            ->where('pesantren_id', $tenantId)
            ->whereNotNull('class_id')
            ->value('class_id');

        $this->assertGreaterThan(0, $classId, 'seeder must provide a class holding students');

        DB::table('teacher_assignments')->where('pesantren_id', $tenantId)->delete();
        DB::table('teacher_assignments')->insert([
            'pesantren_id' => $tenantId,
            'teacher_id' => $teacherId,
            'class_id' => $classId,
            'subject_id' => DB::table('subjects')->where('pesantren_id', $tenantId)->value('id'),
            'assigned_at' => now()->subMonth()->toDateString(),
            'ended_at' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $classId;
    }

    /**
     * Replaces the seeded room assignments with a single controlled one and
     * returns the name of the supervised room.
     */
    private function assignMusyrifToRoom(int $tenantId, int $musyrifUserId): string
    {
        $room = DB::table('rooms')->where('pesantren_id', $tenantId)->orderBy('id')->first();

        $this->assertNotNull($room, 'seeder must provide at least one room');

        DB::table('musyrif_room_assignments')->where('pesantren_id', $tenantId)->delete();
        DB::table('musyrif_room_assignments')->insert([
            'pesantren_id' => $tenantId,
            'user_id' => $musyrifUserId,
            'room_id' => $room->id,
            'assigned_at' => now()->subMonth()->toDateString(),
            'ended_at' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (string) $room->name;
    }
}
