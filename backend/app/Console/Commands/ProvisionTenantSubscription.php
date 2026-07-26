<?php

namespace App\Console\Commands;

use App\Services\SubscriptionProvisioner;
use App\Support\ProvisioningException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Production entry point for plan/subscription provisioning.
 *
 * Running the same command twice is safe: the second run reports `unchanged`
 * and writes an audit record instead of issuing a second subscription.
 */
class ProvisionTenantSubscription extends Command
{
    protected $signature = 'pesantrenku:provision-subscription
        {--pesantren-id= : ID tenant yang akan di-provision}
        {--plan-id= : ID plan tujuan}
        {--plan-code= : Kode plan tujuan, alternatif dari --plan-id}
        {--status=trial : Status tujuan (trial, active, suspended, expired, cancelled)}
        {--starts-at= : Tanggal mulai (YYYY-MM-DD)}
        {--ends-at= : Tanggal berakhir (YYYY-MM-DD)}
        {--reason= : Alasan atau nomor tiket, wajib untuk jejak audit}
        {--actor-email= : Email akun yang bertanggung jawab atas perubahan}
        {--dry-run : Tampilkan rencana perubahan tanpa menulis apa pun}';

    protected $description = 'Provision paket dan subscription tenant secara idempoten dan teraudit.';

    public function handle(SubscriptionProvisioner $provisioner): int
    {
        $tenantId = (int) $this->option('pesantren-id');
        if ($tenantId <= 0) {
            $this->error('Opsi --pesantren-id wajib diisi.');

            return self::FAILURE;
        }

        $planId = $this->resolvePlanId();
        if ($planId === false) {
            return self::FAILURE;
        }

        $actorEmail = trim((string) $this->option('actor-email'));
        $actorUserId = null;
        if ($actorEmail !== '') {
            $actorUserId = DB::table('users')->where('email', $actorEmail)->value('id');
            if (! $actorUserId) {
                $this->error('Akun dengan email '.$actorEmail.' tidak ditemukan.');

                return self::FAILURE;
            }
        }

        $intent = [
            'tenant_id' => $tenantId,
            'plan_id' => $planId,
            'status' => (string) $this->option('status'),
            'starts_at' => $this->option('starts-at'),
            'ends_at' => $this->option('ends-at'),
            'reason' => (string) $this->option('reason'),
            'actor_user_id' => $actorUserId ? (int) $actorUserId : null,
            'actor_email' => $actorEmail !== '' ? $actorEmail : null,
            'actor_role' => 'super_admin',
            'source' => 'artisan:pesantrenku:provision-subscription',
        ];

        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $dryRun ? $provisioner->plan($intent) : $provisioner->provision($intent);
        } catch (ProvisioningException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->report($result, $dryRun);

        return self::SUCCESS;
    }

    /**
     * @return int|null|false `false` menandakan input tidak valid.
     */
    private function resolvePlanId(): int|null|false
    {
        $planId = trim((string) $this->option('plan-id'));
        $planCode = trim((string) $this->option('plan-code'));

        if ($planId !== '' && $planCode !== '') {
            $this->error('Gunakan --plan-id atau --plan-code, jangan keduanya.');

            return false;
        }

        if ($planId !== '') {
            return (int) $planId;
        }

        if ($planCode === '') {
            return null;
        }

        $resolved = DB::table('plans')->where('code', $planCode)->value('id');
        if (! $resolved) {
            $this->error('Plan dengan kode '.$planCode.' tidak ditemukan.');

            return false;
        }

        return (int) $resolved;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function report(array $result, bool $dryRun): void
    {
        $outcome = (string) $result['outcome'];
        $label = [
            SubscriptionProvisioner::OUTCOME_CREATED => 'Subscription baru diterbitkan.',
            SubscriptionProvisioner::OUTCOME_UPDATED => 'Subscription yang ada diselaraskan.',
            SubscriptionProvisioner::OUTCOME_UNCHANGED => 'Tidak ada perubahan; state sudah sesuai.',
        ][$outcome] ?? $outcome;

        $this->line(($dryRun ? '[dry-run] ' : '').$label);

        $rows = [
            ['outcome', $outcome],
            ['subscription_id', (string) ($result['subscription_id'] ?: '-')],
            ['fingerprint', substr((string) $result['fingerprint'], 0, 16)],
        ];

        foreach ((array) ($result['changes'] ?? []) as $column => $value) {
            $rows[] = ['change:'.$column, $value === null ? 'null' : (string) $value];
        }

        $this->table(['field', 'value'], $rows);

        if ($dryRun) {
            $this->warn('Tidak ada perubahan yang ditulis dan tidak ada catatan audit yang dibuat.');
        }
    }
}
