<?php

namespace App\Support;

/**
 * Redacts personal data of previous owners from history/timeline entries.
 *
 * Audit metadata stores complete `before` and `after` snapshots of a row. That
 * is correct for an audit trail, but the timeline endpoint replays those
 * snapshots to anyone who may read the row *today*. When a row changes hands,
 * the history therefore leaks the previous holder's data:
 *
 * - a santri moved to another guardian keeps the old guardian's name, phone
 *   and address in the history;
 * - a re-assigned account keeps the previous user's identifiers;
 * - a corrected contact field keeps the superseded value in clear text.
 *
 * The rule applied here is deliberately narrow: a personal field is shown only
 * while it still matches the current row. As soon as a value has been
 * superseded it belongs to someone else's past and is replaced with a marker,
 * so the timeline still shows *that* a change happened without disclosing
 * *what* the previous personal value was.
 *
 * Secret-like fields are removed unconditionally; they must never appear in a
 * response regardless of whether they changed.
 */
final class TimelineRedactor
{
    public const MARKER = '[diredaksi]';

    /**
     * Always removed, even when unchanged.
     */
    public const SECRET_KEY_FRAGMENTS = [
        'password',
        'secret',
        'token',
        'mfa',
        'recovery_code',
        'signature',
        'private_key',
    ];

    /**
     * Removed once the value no longer matches the current row.
     */
    public const PERSONAL_KEY_FRAGMENTS = [
        'name',
        'email',
        'phone',
        'whatsapp',
        'address',
        'nik',
        'nis',
        'birth',
        'guardian',
        'parent',
        'note',
        'diagnosis',
        'complaint',
        'photo',
        'avatar',
        'account_number',
        'user_id',
    ];

    /**
     * @param  array<string, mixed>  $metadata  Decoded audit metadata.
     * @param  object|array<string, mixed>|null  $currentRow  The row as it exists now.
     * @return array<string, mixed>
     */
    public static function redactMetadata(array $metadata, object|array|null $currentRow): array
    {
        $current = self::normalizeRow($currentRow);

        foreach (['before', 'after'] as $section) {
            if (array_key_exists($section, $metadata)) {
                $metadata[$section] = self::redactSnapshot($metadata[$section], $current);
            }
        }

        return $metadata;
    }

    /**
     * @param  array<string, mixed>  $current
     */
    private static function redactSnapshot(mixed $snapshot, array $current): mixed
    {
        if (is_object($snapshot)) {
            $snapshot = (array) $snapshot;
        }

        if (! is_array($snapshot)) {
            return $snapshot;
        }

        $redacted = [];
        foreach ($snapshot as $key => $value) {
            $stringKey = (string) $key;

            if (is_array($value) || is_object($value)) {
                $redacted[$key] = self::redactSnapshot($value, $current);

                continue;
            }

            if (self::matches($stringKey, self::SECRET_KEY_FRAGMENTS)) {
                $redacted[$key] = self::MARKER;

                continue;
            }

            if (! self::matches($stringKey, self::PERSONAL_KEY_FRAGMENTS)) {
                $redacted[$key] = $value;

                continue;
            }

            // A personal value survives only while it is still the current one.
            // Unknown current state fails closed.
            $stillCurrent = array_key_exists($stringKey, $current)
                && self::comparable($current[$stringKey]) === self::comparable($value);

            $redacted[$key] = $stillCurrent ? $value : self::MARKER;
        }

        return $redacted;
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeRow(object|array|null $row): array
    {
        if ($row === null) {
            return [];
        }

        return is_array($row) ? $row : (array) $row;
    }

    private static function comparable(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value) || is_object($value)) {
            return (string) json_encode($value);
        }

        return trim((string) $value);
    }

    /**
     * @param  array<int, string>  $fragments
     */
    private static function matches(string $key, array $fragments): bool
    {
        $normalized = strtolower($key);
        foreach ($fragments as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
