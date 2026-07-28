<?php

namespace Tests\Unit;

use App\Support\ImportRowScope;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit coverage for the queued-import row scope guard. No database.
 *
 * The contract under test is fail-closed: deniedReason() returns null only
 * when the row can be *proven* to sit inside the actor's assignment.
 */
class ImportRowScopeTest extends TestCase
{
    public function test_unrestricted_scope_accepts_every_row(): void
    {
        $scope = ImportRowScope::unrestricted();

        $this->assertNull($scope->deniedReason([]));
        $this->assertNull($scope->deniedReason(['teacher_id' => 999]));
    }

    public function test_deny_all_rejects_every_row_with_the_given_reason(): void
    {
        $scope = ImportRowScope::denyAll('actor tidak memiliki penugasan aktif.');

        $this->assertSame('actor tidak memiliki penugasan aktif.', $scope->deniedReason([]));
        $this->assertSame('actor tidak memiliki penugasan aktif.', $scope->deniedReason(['teacher_id' => 7]));
    }

    public function test_row_inside_the_assignment_is_accepted(): void
    {
        $scope = ImportRowScope::restricted(['teacher_id' => [7, 8]]);

        $this->assertNull($scope->deniedReason(['teacher_id' => 7, 'subject' => 'Nahwu']));
    }

    public function test_row_outside_the_assignment_is_rejected(): void
    {
        $scope = ImportRowScope::restricted(['teacher_id' => [7, 8]]);

        $this->assertSame(
            'nilai teacher_id berada di luar penugasan aktif actor.',
            $scope->deniedReason(['teacher_id' => 9]),
        );
    }

    public function test_row_without_any_scope_column_cannot_be_proven_and_is_rejected(): void
    {
        $scope = ImportRowScope::restricted(['teacher_id' => [7]]);

        $this->assertSame(
            'baris tidak memuat kolom cakupan penugasan (teacher_id) sehingga keanggotaannya tidak dapat dibuktikan.',
            $scope->deniedReason(['subject' => 'Nahwu']),
        );
    }

    public function test_null_and_empty_scope_values_count_as_absent(): void
    {
        $scope = ImportRowScope::restricted(['teacher_id' => [7]]);
        $expected = 'baris tidak memuat kolom cakupan penugasan (teacher_id) sehingga keanggotaannya tidak dapat dibuktikan.';

        $this->assertSame($expected, $scope->deniedReason(['teacher_id' => null]));
        $this->assertSame($expected, $scope->deniedReason(['teacher_id' => '']));
    }

    public function test_values_are_compared_as_strings(): void
    {
        $scope = ImportRowScope::restricted(['teacher_id' => [7]]);

        $this->assertNull($scope->deniedReason(['teacher_id' => '7']));
        $this->assertNull($scope->deniedReason(['teacher_id' => 7]));
    }

    public function test_every_present_scope_column_must_be_inside_the_assignment(): void
    {
        $scope = ImportRowScope::restricted([
            'teacher_id' => [7],
            'room_id' => [11],
        ]);

        $this->assertNull($scope->deniedReason(['teacher_id' => 7, 'room_id' => 11]));
        $this->assertSame(
            'nilai room_id berada di luar penugasan aktif actor.',
            $scope->deniedReason(['teacher_id' => 7, 'room_id' => 12]),
        );
    }

    public function test_a_single_present_scope_column_is_enough_to_prove_membership(): void
    {
        $scope = ImportRowScope::restricted([
            'teacher_id' => [7],
            'room_id' => [11],
        ]);

        // Documents the current rule: absent columns are not required, only
        // present ones are validated. At least one must be present.
        $this->assertNull($scope->deniedReason(['teacher_id' => 7]));
        $this->assertNull($scope->deniedReason(['room_id' => 11]));
    }

    public function test_assignment_list_is_normalised_and_blank_entries_are_dropped(): void
    {
        $scope = ImportRowScope::restricted(['teacher_id' => [7, 7, null, '', '8']]);

        $this->assertNull($scope->deniedReason(['teacher_id' => 8]));
        $this->assertSame(
            'nilai teacher_id berada di luar penugasan aktif actor.',
            $scope->deniedReason(['teacher_id' => 0]),
        );
    }

    public function test_an_empty_assignment_list_still_rejects_every_row(): void
    {
        $scope = ImportRowScope::restricted(['teacher_id' => []]);

        $this->assertSame(
            'nilai teacher_id berada di luar penugasan aktif actor.',
            $scope->deniedReason(['teacher_id' => 7]),
        );
    }

    /**
     * Edge case worth fixing at the call site rather than here: building a
     * restricted scope with no columns at all produces an empty column list in
     * the rejection message. It still fails closed, but the message is useless.
     */
    public function test_documents_empty_scope_definition_producing_a_bare_message(): void
    {
        $scope = ImportRowScope::restricted([]);

        $this->assertSame(
            'baris tidak memuat kolom cakupan penugasan () sehingga keanggotaannya tidak dapat dibuktikan.',
            $scope->deniedReason(['teacher_id' => 7]),
        );
    }
}
