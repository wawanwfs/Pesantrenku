<?php

namespace App\Support;

/**
 * Immutable snapshot of an actor's row-level operational scope for a queued
 * write operation.
 *
 * The snapshot is deliberately fail-closed. A row is only accepted when it
 * carries at least one scoped column AND every scoped column it carries holds
 * a value that is inside the actor's current assignment. A row that cannot be
 * proven to be inside scope is rejected, never accepted by default.
 */
final class ImportRowScope
{
    /**
     * @param  array<string, array<int, string>>  $allowedValues
     * @param  array<int, string>  $scopeColumns
     */
    private function __construct(
        private readonly bool $unrestricted,
        private readonly ?string $denyReason,
        private readonly array $allowedValues,
        private readonly array $scopeColumns,
    ) {}

    /**
     * The actor's role is not restricted to assigned rows (platform, owner,
     * administrative and delegated custom roles).
     */
    public static function unrestricted(): self
    {
        return new self(true, null, [], []);
    }

    /**
     * No row of this table can be proven to be inside the actor's scope.
     */
    public static function denyAll(string $reason): self
    {
        return new self(false, $reason, [], []);
    }

    /**
     * @param  array<string, array<int, int|string|null>>  $allowedValues
     */
    public static function restricted(array $allowedValues): self
    {
        $normalized = [];
        foreach ($allowedValues as $column => $values) {
            $normalized[$column] = array_values(array_unique(array_map(
                static fn ($value) => (string) $value,
                array_filter($values, static fn ($value) => $value !== null && $value !== ''),
            )));
        }

        return new self(false, null, $normalized, array_keys($normalized));
    }

    /**
     * Returns null when the row is inside scope, otherwise the reason it was
     * rejected. The reason is safe to surface in an import result summary.
     *
     * @param  array<string, mixed>  $data
     */
    public function deniedReason(array $data): ?string
    {
        if ($this->unrestricted) {
            return null;
        }

        if ($this->denyReason !== null) {
            return $this->denyReason;
        }

        $present = array_values(array_filter(
            $this->scopeColumns,
            static fn (string $column) => array_key_exists($column, $data)
                && $data[$column] !== null
                && $data[$column] !== '',
        ));

        if ($present === []) {
            return 'baris tidak memuat kolom cakupan penugasan ('
                .implode(', ', $this->scopeColumns)
                .') sehingga keanggotaannya tidak dapat dibuktikan.';
        }

        foreach ($present as $column) {
            $value = (string) $data[$column];
            if (! in_array($value, $this->allowedValues[$column] ?? [], true)) {
                return "nilai {$column} berada di luar penugasan aktif actor.";
            }
        }

        return null;
    }
}
