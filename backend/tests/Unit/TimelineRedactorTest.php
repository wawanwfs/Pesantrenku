<?php

namespace Tests\Unit;

use App\Support\TimelineRedactor;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit coverage for the timeline redactor. No database, no Laravel boot.
 *
 * Several assertions below pin down behaviour that is arguably wrong (see the
 * over-matching group at the bottom). They are written as characterisation
 * tests: they document what the code does today so that a deliberate fix
 * fails loudly instead of slipping through unnoticed.
 */
class TimelineRedactorTest extends TestCase
{
    public function test_secret_fields_are_redacted_even_when_unchanged(): void
    {
        $current = ['password' => 'hash-abc', 'mfa_secret' => 'JBSWY3DP'];

        $result = TimelineRedactor::redactMetadata([
            'before' => ['password' => 'hash-abc', 'mfa_secret' => 'JBSWY3DP', 'api_token' => 'plain'],
            'after' => ['password' => 'hash-abc', 'mfa_secret' => 'JBSWY3DP', 'api_token' => 'plain'],
        ], $current);

        foreach (['before', 'after'] as $section) {
            $this->assertSame(TimelineRedactor::MARKER, $result[$section]['password']);
            $this->assertSame(TimelineRedactor::MARKER, $result[$section]['mfa_secret']);
            $this->assertSame(TimelineRedactor::MARKER, $result[$section]['api_token']);
        }
    }

    public function test_personal_value_survives_while_it_matches_the_current_row(): void
    {
        $result = TimelineRedactor::redactMetadata([
            'after' => ['guardian_name' => 'Ahmad Fauzi'],
        ], ['guardian_name' => 'Ahmad Fauzi']);

        $this->assertSame('Ahmad Fauzi', $result['after']['guardian_name']);
    }

    public function test_superseded_personal_value_is_redacted_but_the_change_stays_visible(): void
    {
        $result = TimelineRedactor::redactMetadata([
            'before' => ['guardian_name' => 'Ahmad Fauzi', 'phone' => '0811111111'],
            'after' => ['guardian_name' => 'Budi Santoso', 'phone' => '0822222222'],
        ], ['guardian_name' => 'Budi Santoso', 'phone' => '0822222222']);

        // The previous holder's data is gone...
        $this->assertSame(TimelineRedactor::MARKER, $result['before']['guardian_name']);
        $this->assertSame(TimelineRedactor::MARKER, $result['before']['phone']);

        // ...but the reader can still see that a change occurred.
        $this->assertArrayHasKey('guardian_name', $result['before']);
        $this->assertSame('Budi Santoso', $result['after']['guardian_name']);
    }

    public function test_unknown_current_row_fails_closed(): void
    {
        $result = TimelineRedactor::redactMetadata([
            'before' => ['name' => 'Ahmad', 'status' => 'aktif'],
        ], null);

        $this->assertSame(TimelineRedactor::MARKER, $result['before']['name']);
        $this->assertSame('aktif', $result['before']['status'], 'non-personal fields stay readable');
    }

    public function test_non_personal_fields_are_never_touched(): void
    {
        $result = TimelineRedactor::redactMetadata([
            'before' => ['status' => 'draft', 'amount' => 150000, 'is_active' => false],
        ], null);

        $this->assertSame('draft', $result['before']['status']);
        $this->assertSame(150000, $result['before']['amount']);
        $this->assertFalse($result['before']['is_active']);
    }

    public function test_only_before_and_after_sections_are_walked(): void
    {
        $result = TimelineRedactor::redactMetadata([
            'actor_name' => 'Petugas TU',
            'reason' => 'koreksi data',
            'before' => ['name' => 'Ahmad'],
        ], null);

        $this->assertSame('Petugas TU', $result['actor_name'], 'top-level keys are out of scope by design');
        $this->assertSame('koreksi data', $result['reason']);
        $this->assertSame(TimelineRedactor::MARKER, $result['before']['name']);
    }

    public function test_metadata_without_snapshots_is_returned_unchanged(): void
    {
        $metadata = ['action' => 'LOGIN', 'ip' => '10.0.0.1'];

        $this->assertSame($metadata, TimelineRedactor::redactMetadata($metadata, null));
    }

    public function test_current_row_may_be_an_object(): void
    {
        $current = (object) ['email' => 'wali@example.test'];

        $result = TimelineRedactor::redactMetadata([
            'after' => ['email' => 'wali@example.test'],
        ], $current);

        $this->assertSame('wali@example.test', $result['after']['email']);
    }

    public function test_comparison_ignores_type_and_surrounding_whitespace(): void
    {
        $result = TimelineRedactor::redactMetadata([
            'after' => ['user_id' => '42', 'name' => '  Ahmad  '],
        ], ['user_id' => 42, 'name' => 'Ahmad']);

        $this->assertSame('42', $result['after']['user_id'], 'int 42 and string "42" are the same value');
        $this->assertSame('  Ahmad  ', $result['after']['name'], 'whitespace differences do not count as a change');
    }

    public function test_nested_snapshots_are_walked_recursively(): void
    {
        $result = TimelineRedactor::redactMetadata([
            'before' => [
                'status' => 'aktif',
                'guardian' => ['name' => 'Ahmad', 'password' => 'x'],
            ],
        ], ['status' => 'aktif']);

        $this->assertSame('aktif', $result['before']['status']);
        $this->assertSame(TimelineRedactor::MARKER, $result['before']['guardian']['password']);
    }

    public function test_nested_personal_values_are_compared_against_the_flat_current_row(): void
    {
        // Known limitation: nested keys are matched against top-level columns of
        // the current row, so a nested personal value can only ever survive when
        // a same-named top-level column happens to hold the same value.
        $result = TimelineRedactor::redactMetadata([
            'before' => ['guardian' => ['name' => 'Ahmad']],
        ], ['guardian' => ['name' => 'Ahmad']]);

        $this->assertSame(TimelineRedactor::MARKER, $result['before']['guardian']['name']);
    }

    public function test_key_matching_is_case_insensitive(): void
    {
        $result = TimelineRedactor::redactMetadata([
            'before' => ['Guardian_Name' => 'Ahmad', 'PASSWORD' => 'x'],
        ], null);

        $this->assertSame(TimelineRedactor::MARKER, $result['before']['Guardian_Name']);
        $this->assertSame(TimelineRedactor::MARKER, $result['before']['PASSWORD']);
    }

    /**
     * Fragment matching uses str_contains, so ordinary Indonesian column names
     * collide with the personal-data fragments. These are false positives: the
     * data is over-redacted, which is safe but degrades the timeline.
     *
     * - 'jenis_kelamin' contains 'nis'
     * - 'klinik_id' contains 'nik'
     * - 'catatan_denominator' style keys containing 'note' behave the same way
     */
    public function test_documents_over_matching_on_indonesian_column_names(): void
    {
        $result = TimelineRedactor::redactMetadata([
            'before' => ['jenis_kelamin' => 'L', 'klinik_id' => 3],
        ], ['jenis_kelamin' => 'P', 'klinik_id' => 4]);

        $this->assertSame(TimelineRedactor::MARKER, $result['before']['jenis_kelamin']);
        $this->assertSame(TimelineRedactor::MARKER, $result['before']['klinik_id']);
    }
}
