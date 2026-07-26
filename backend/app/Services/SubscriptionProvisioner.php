<?php

namespace App\Services;

use App\Support\ProvisioningException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent, audited provisioning of tenant plan/subscription state.
 *
 * Provisioning used to be an ordinary insert through the generic CRUD path,
 * which made a retried or duplicated call able to leave a tenant with more
 * than one entitlement-bearing subscription, and left no dedicated audit
 * record of who granted which plan.
 *
 * This service expresses provisioning as desired-state reconciliation:
 *
 * - the tenant row and the affected subscription rows are locked before any
 *   comparison, so concurrent provisioning cannot interleave;
 * - a repeated call carrying the same intent changes nothing and reports
 *   `unchanged`, so retries are safe without needing an Idempotency-Key;
 * - a tenant that already holds more than one live subscription is refused
 *   rather than silently mutated, because guessing which row is authoritative
 *   could revoke a paid entitlement;
 * - every outcome, including no-ops and rejections, is written to `audit_logs`.
 */
final class SubscriptionProvisioner
{
    public const OUTCOME_CREATED = 'created';

    public const OUTCOME_UPDATED = 'updated';

    public const OUTCOME_UNCHANGED = 'unchanged';

    /** Statuses this workflow is allowed to set. */
    public const ALLOWED_STATUSES = ['trial', 'active', 'suspended', 'expired', 'cancelled'];

    /** Statuses that carry entitlements, so at most one may exist per tenant. */
    private const LIVE_STATUSES = ['trial', 'active'];

    /** Columns owned by this workflow. Anything else is left untouched. */
    private const RECONCILED_COLUMNS = ['plan_id', 'status', 'starts_at', 'ends_at'];

    /**
     * Evaluate an intent without writing anything.
     *
     * @param  array<string, mixed>  $intent
     * @return array<string, mixed>
     */
    public function plan(array $intent): array
    {
        return $this->run($intent, false);
    }

    /**
     * Reconcile subscription state to the given intent.
     *
     * @param  array<string, mixed>  $intent
     * @return array<string, mixed>
     */
    public function provision(array $intent): array
    {
        return $this->run($intent, true);
    }

    /**
     * @param  array<string, mixed>  $intent
     * @return array<string, mixed>
     */
    private function run(array $intent, bool $apply): array
    {
        $desired = $this->desiredState($intent);
        $fingerprint = $this->fingerprint($desired);

        try {
            $result = DB::transaction(fn () => $this->reconcile($desired, $fingerprint, $intent, $apply), 3);
        } catch (ProvisioningException $exception) {
            // The transaction has rolled back, so the rejection is recorded
            // outside of it. A refused provisioning attempt is exactly the kind
            // of event an operator needs to see afterwards.
            if ($apply) {
                $this->auditRejection($desired, $fingerprint, $intent, $exception->getMessage());
            }

            throw $exception;
        }

        // Entitlement checks read a cached tenant record, so a real state
        // change must drop that cache. A no-op deliberately does not.
        if ($apply && $result['outcome'] !== self::OUTCOME_UNCHANGED) {
            Cache::forget('pesantenku:tenant:'.$desired['pesantren_id']);
        }

        return $result + [
            'fingerprint' => $fingerprint,
            'applied' => $apply,
        ];
    }

    /**
     * Validate the intent and translate it into the state we want to hold.
     *
     * @param  array<string, mixed>  $intent
     * @return array<string, mixed>
     */
    private function desiredState(array $intent): array
    {
        $tenantId = (int) ($intent['tenant_id'] ?? 0);
        if ($tenantId <= 0) {
            throw new ProvisioningException('Provisioning wajib menyebutkan pesantren_id yang valid.');
        }

        $status = strtolower(trim((string) ($intent['status'] ?? 'trial')));
        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new ProvisioningException(
                'Status subscription "'.$status.'" tidak diizinkan. Pilihan yang sah: '.implode(', ', self::ALLOWED_STATUSES).'.'
            );
        }

        $planId = null;
        if (isset($intent['plan_id']) && $intent['plan_id'] !== null && $intent['plan_id'] !== '') {
            $planId = (int) $intent['plan_id'];
            if ($planId <= 0) {
                throw new ProvisioningException('plan_id provisioning tidak valid.');
            }
        }

        if ($planId === null && in_array($status, self::LIVE_STATUSES, true)) {
            throw new ProvisioningException('Subscription berstatus "'.$status.'" wajib terikat pada satu plan.');
        }

        $startsAt = $this->normalizeDate($intent['starts_at'] ?? null, 'starts_at');
        $endsAt = $this->normalizeDate($intent['ends_at'] ?? null, 'ends_at');
        if ($startsAt !== null && $endsAt !== null && $endsAt < $startsAt) {
            throw new ProvisioningException('Tanggal berakhir subscription tidak boleh lebih awal dari tanggal mulai.');
        }

        $reason = trim((string) ($intent['reason'] ?? ''));
        if ($reason === '') {
            throw new ProvisioningException('Provisioning wajib menyertakan alasan atau nomor tiket untuk jejak audit.');
        }

        return [
            'pesantren_id' => $tenantId,
            'plan_id' => $planId,
            'status' => $status,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $desired
     * @param  array<string, mixed>  $intent
     * @return array<string, mixed>
     */
    private function reconcile(array $desired, string $fingerprint, array $intent, bool $apply): array
    {
        $tenantId = (int) $desired['pesantren_id'];

        $tenant = DB::table('pesantrens')
            ->where('id', $tenantId)
            ->when(Schema::hasColumn('pesantrens', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->lockForUpdate()
            ->first();
        if (! $tenant) {
            throw new ProvisioningException('Pesantren '.$tenantId.' tidak ditemukan atau sudah dihapus.');
        }

        $plan = null;
        if ($desired['plan_id'] !== null) {
            $plan = DB::table('plans')->where('id', $desired['plan_id'])->lockForUpdate()->first();
            if (! $plan) {
                throw new ProvisioningException('Plan '.$desired['plan_id'].' tidak ditemukan.');
            }
        }

        $live = DB::table('subscriptions')
            ->where('pesantren_id', $tenantId)
            ->whereIn('status', self::LIVE_STATUSES)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($live->count() > 1) {
            throw new ProvisioningException(
                'Tenant ini memiliki '.$live->count().' subscription aktif sekaligus (id: '
                .$live->pluck('id')->implode(', ')
                .'). Provisioning dihentikan karena memilih salah satu secara otomatis bisa mencabut entitlement yang sah. Rekonsiliasi manual diperlukan lebih dulu.'
            );
        }

        $current = $live->first();
        $targetIsLive = in_array($desired['status'], self::LIVE_STATUSES, true);

        // Menonaktifkan subscription berarti mengubah baris terakhir yang ada,
        // bukan menerbitkan baris terminal yang baru.
        if ($current === null && ! $targetIsLive) {
            $current = DB::table('subscriptions')
                ->where('pesantren_id', $tenantId)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($current === null) {
                throw new ProvisioningException(
                    'Tenant ini belum punya subscription, sehingga tidak ada yang bisa diubah ke status "'.$desired['status'].'".'
                );
            }
        }

        $before = $current ? $this->snapshot($current) : null;
        $changes = [];
        $outcome = self::OUTCOME_UNCHANGED;
        $subscriptionId = $current ? (int) $current->id : 0;

        if ($current === null) {
            $outcome = self::OUTCOME_CREATED;
            $changes = $desired;
        } else {
            foreach (self::RECONCILED_COLUMNS as $column) {
                $currentValue = $this->comparable($current->{$column} ?? null, $column);
                $desiredValue = $this->comparable($desired[$column], $column);
                if ($currentValue !== $desiredValue) {
                    $changes[$column] = $desired[$column];
                }
            }

            if ($changes !== []) {
                $outcome = self::OUTCOME_UPDATED;
            }
        }

        if (! $apply) {
            return [
                'outcome' => $outcome,
                'subscription_id' => $subscriptionId,
                'before' => $before,
                'after' => $outcome === self::OUTCOME_UNCHANGED ? $before : null,
                'changes' => $changes,
            ];
        }

        $now = now();

        if ($outcome === self::OUTCOME_CREATED) {
            $payload = $desired;
            if (Schema::hasColumn('subscriptions', 'created_at')) {
                $payload['created_at'] = $now;
            }
            if (Schema::hasColumn('subscriptions', 'updated_at')) {
                $payload['updated_at'] = $now;
            }

            $subscriptionId = (int) DB::table('subscriptions')->insertGetId($payload);
        } elseif ($outcome === self::OUTCOME_UPDATED) {
            $update = $changes;
            if (Schema::hasColumn('subscriptions', 'updated_at')) {
                $update['updated_at'] = $now;
            }

            // Guard against a writer that slipped in between the lock and the
            // update on databases without row locking (SQLite in tests).
            $affected = DB::table('subscriptions')
                ->where('id', $subscriptionId)
                ->where('status', $current->status)
                ->update($update);

            if ($affected !== 1) {
                throw new ProvisioningException('Subscription berubah oleh proses lain saat provisioning berjalan. Ulangi permintaan.');
            }
        }

        $after = DB::table('subscriptions')->where('id', $subscriptionId)->first();
        $afterSnapshot = $after ? $this->snapshot($after) : null;

        $this->audit($tenantId, $subscriptionId, 'PROVISION SUBSCRIPTION '.strtoupper($outcome), $intent, [
            'fingerprint' => $fingerprint,
            'outcome' => $outcome,
            'desired' => $desired,
            'changes' => $changes,
            'before' => $before,
            'after' => $afterSnapshot,
            'plan' => $plan ? [
                'id' => (int) $plan->id,
                'code' => $plan->code ?? null,
                'name' => $plan->name ?? null,
                'status' => $plan->status ?? null,
            ] : null,
        ]);

        return [
            'outcome' => $outcome,
            'subscription_id' => $subscriptionId,
            'before' => $before,
            'after' => $afterSnapshot,
            'changes' => $changes,
        ];
    }

    /**
     * @param  array<string, mixed>  $desired
     * @param  array<string, mixed>  $intent
     */
    private function auditRejection(array $desired, string $fingerprint, array $intent, string $reason): void
    {
        $this->audit((int) $desired['pesantren_id'], null, 'PROVISION SUBSCRIPTION REJECTED', $intent, [
            'fingerprint' => $fingerprint,
            'outcome' => 'rejected',
            'desired' => $desired,
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * @param  array<string, mixed>  $intent
     * @param  array<string, mixed>  $metadata
     */
    private function audit(int $tenantId, ?int $rowId, string $action, array $intent, array $metadata): void
    {
        DB::table('audit_logs')->insert([
            'pesantren_id' => $tenantId,
            'user_id' => isset($intent['actor_user_id']) ? (int) $intent['actor_user_id'] : null,
            'action' => $action,
            'module' => 'subscriptions',
            'row_id' => $rowId,
            'ip_address' => $intent['ip_address'] ?? null,
            'metadata' => json_encode($metadata + [
                'reason' => trim((string) ($intent['reason'] ?? '')),
                'source' => $intent['source'] ?? 'unknown',
                'actor_role' => $intent['actor_role'] ?? null,
                'actor_email' => $intent['actor_email'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(object $row): array
    {
        return [
            'id' => isset($row->id) ? (int) $row->id : null,
            'pesantren_id' => isset($row->pesantren_id) ? (int) $row->pesantren_id : null,
            'plan_id' => isset($row->plan_id) && $row->plan_id !== null ? (int) $row->plan_id : null,
            'status' => $row->status ?? null,
            'starts_at' => isset($row->starts_at) && $row->starts_at !== null ? substr((string) $row->starts_at, 0, 10) : null,
            'ends_at' => isset($row->ends_at) && $row->ends_at !== null ? substr((string) $row->ends_at, 0, 10) : null,
        ];
    }

    /**
     * Normalize values so an equivalent state never looks like a change.
     */
    private function comparable(mixed $value, string $column): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($column) {
            'plan_id' => (string) (int) $value,
            'status' => strtolower(trim((string) $value)),
            'starts_at', 'ends_at' => substr((string) $value, 0, 10),
            default => (string) $value,
        };
    }

    private function normalizeDate(mixed $value, string $field): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $raw = trim((string) $value);
        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            throw new ProvisioningException('Format tanggal '.$field.' tidak valid: "'.$raw.'".');
        }

        return date('Y-m-d', $timestamp);
    }

    /**
     * @param  array<string, mixed>  $desired
     */
    private function fingerprint(array $desired): string
    {
        ksort($desired);

        return hash('sha256', (string) json_encode($desired, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
