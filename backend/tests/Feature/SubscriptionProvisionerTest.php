<?php

namespace Tests\Feature;

use App\Services\SubscriptionProvisioner;
use App\Support\ProvisioningException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Provisioning is expressed as desired-state reconciliation, so the properties
 * worth testing are: a retry changes nothing, an ambiguous tenant is refused
 * instead of guessed, and every outcome leaves an audit record.
 */
class SubscriptionProvisionerTest extends TestCase
{
    use RefreshDatabase;

    private function provisioner(): SubscriptionProvisioner
    {
        return app(SubscriptionProvisioner::class);
    }

    private function tenantId(): int
    {
        return (int) DB::table('pesantrens')->orderBy('id')->value('id');
    }

    private function planId(): int
    {
        return (int) DB::table('plans')->orderBy('id')->value('id');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function intent(array $overrides = []): array
    {
        return array_merge([
            'tenant_id' => $this->tenantId(),
            'plan_id' => $this->planId(),
            'status' => 'active',
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-12-31',
            'reason' => 'TIKET-1234 aktivasi langganan',
            'source' => 'phpunit',
        ], $overrides);
    }

    private function clearSubscriptions(int $tenantId): void
    {
        DB::table('subscriptions')->where('pesantren_id', $tenantId)->delete();
    }

    public function test_provisioning_creates_a_subscription_when_the_tenant_has_none(): void
    {
        $this->seed();
        $tenantId = $this->tenantId();
        $this->clearSubscriptions($tenantId);

        $result = $this->provisioner()->provision($this->intent());

        $this->assertSame(SubscriptionProvisioner::OUTCOME_CREATED, $result['outcome']);
        $this->assertGreaterThan(0, $result['subscription_id']);
        $this->assertNull($result['before']);
        $this->assertSame(1, DB::table('subscriptions')->where('pesantren_id', $tenantId)->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'PROVISION SUBSCRIPTION CREATED',
            'module' => 'subscriptions',
            'row_id' => $result['subscription_id'],
        ]);
    }

    public function test_repeating_the_same_intent_changes_nothing(): void
    {
        $this->seed();
        $tenantId = $this->tenantId();
        $this->clearSubscriptions($tenantId);

        $first = $this->provisioner()->provision($this->intent());
        $second = $this->provisioner()->provision($this->intent());

        $this->assertSame(SubscriptionProvisioner::OUTCOME_UNCHANGED, $second['outcome']);
        $this->assertSame($first['subscription_id'], $second['subscription_id']);
        $this->assertSame([], $second['changes']);
        $this->assertSame(1, DB::table('subscriptions')->where('pesantren_id', $tenantId)->count());
        $this->assertSame($first['fingerprint'], $second['fingerprint']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'PROVISION SUBSCRIPTION UNCHANGED',
            'module' => 'subscriptions',
        ]);
    }

    public function test_a_real_difference_is_applied_and_reported_per_column(): void
    {
        $this->seed();
        $tenantId = $this->tenantId();
        $this->clearSubscriptions($tenantId);
        $created = $this->provisioner()->provision($this->intent());

        $result = $this->provisioner()->provision($this->intent(['ends_at' => '2027-06-30']));

        $this->assertSame(SubscriptionProvisioner::OUTCOME_UPDATED, $result['outcome']);
        $this->assertSame(['ends_at' => '2027-06-30'], $result['changes']);
        $this->assertSame($created['subscription_id'], $result['subscription_id']);
        $this->assertSame('2027-06-30', $result['after']['ends_at']);
        $this->assertSame(1, DB::table('subscriptions')->where('pesantren_id', $tenantId)->count());
    }

    public function test_equivalent_date_and_status_formats_do_not_count_as_a_change(): void
    {
        $this->seed();
        $tenantId = $this->tenantId();
        $this->clearSubscriptions($tenantId);
        $this->provisioner()->provision($this->intent());

        $result = $this->provisioner()->provision($this->intent([
            'status' => 'ACTIVE',
            'starts_at' => '2026-01-01 08:30:00',
        ]));

        $this->assertSame(SubscriptionProvisioner::OUTCOME_UNCHANGED, $result['outcome']);
    }

    public function test_plan_evaluates_the_intent_without_writing(): void
    {
        $this->seed();
        $tenantId = $this->tenantId();
        $this->clearSubscriptions($tenantId);

        $result = $this->provisioner()->plan($this->intent());

        $this->assertSame(SubscriptionProvisioner::OUTCOME_CREATED, $result['outcome']);
        $this->assertFalse($result['applied']);
        $this->assertSame(0, DB::table('subscriptions')->where('pesantren_id', $tenantId)->count());
        $this->assertDatabaseMissing('audit_logs', ['module' => 'subscriptions', 'action' => 'PROVISION SUBSCRIPTION CREATED']);
    }

    public function test_provisioning_without_a_reason_is_refused(): void
    {
        $this->seed();

        $this->expectException(ProvisioningException::class);
        $this->expectExceptionMessage('alasan');

        $this->provisioner()->provision($this->intent(['reason' => '   ']));
    }

    public function test_unknown_status_is_refused_and_lists_the_allowed_ones(): void
    {
        $this->seed();

        try {
            $this->provisioner()->provision($this->intent(['status' => 'gratisan']));
            $this->fail('an unknown status must be refused');
        } catch (ProvisioningException $exception) {
            foreach (SubscriptionProvisioner::ALLOWED_STATUSES as $status) {
                $this->assertStringContainsString($status, $exception->getMessage());
            }
        }
    }

    public function test_a_live_status_must_be_bound_to_a_plan(): void
    {
        $this->seed();

        $this->expectException(ProvisioningException::class);

        $this->provisioner()->provision($this->intent(['plan_id' => null, 'status' => 'trial']));
    }

    public function test_end_date_before_start_date_is_refused(): void
    {
        $this->seed();

        $this->expectException(ProvisioningException::class);

        $this->provisioner()->provision($this->intent([
            'starts_at' => '2026-05-01',
            'ends_at' => '2026-04-01',
        ]));
    }

    public function test_unknown_tenant_is_refused(): void
    {
        $this->seed();

        $this->expectException(ProvisioningException::class);

        $this->provisioner()->provision($this->intent(['tenant_id' => 999999]));
    }

    public function test_a_terminal_status_needs_an_existing_subscription(): void
    {
        $this->seed();
        $tenantId = $this->tenantId();
        $this->clearSubscriptions($tenantId);

        $this->expectException(ProvisioningException::class);

        $this->provisioner()->provision($this->intent([
            'plan_id' => null,
            'status' => 'cancelled',
        ]));
    }

    public function test_deactivation_mutates_the_last_row_instead_of_issuing_a_new_one(): void
    {
        $this->seed();
        $tenantId = $this->tenantId();
        $this->clearSubscriptions($tenantId);
        $created = $this->provisioner()->provision($this->intent());

        $result = $this->provisioner()->provision($this->intent([
            'plan_id' => null,
            'status' => 'cancelled',
        ]));

        $this->assertSame(SubscriptionProvisioner::OUTCOME_UPDATED, $result['outcome']);
        $this->assertSame($created['subscription_id'], $result['subscription_id']);
        $this->assertSame(1, DB::table('subscriptions')->where('pesantren_id', $tenantId)->count());
        $this->assertSame('cancelled', $result['after']['status']);
    }

    public function test_a_tenant_holding_two_live_subscriptions_is_refused_and_audited(): void
    {
        $this->seed();
        $tenantId = $this->tenantId();
        $this->clearSubscriptions($tenantId);
        $planId = $this->planId();

        foreach (['trial', 'active'] as $status) {
            DB::table('subscriptions')->insert([
                'pesantren_id' => $tenantId,
                'plan_id' => $planId,
                'status' => $status,
                'starts_at' => '2026-01-01',
                'ends_at' => '2026-12-31',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        try {
            $this->provisioner()->provision($this->intent());
            $this->fail('an ambiguous tenant must not be mutated automatically');
        } catch (ProvisioningException $exception) {
            $this->assertStringContainsString('Rekonsiliasi manual', $exception->getMessage());
        }

        $this->assertSame(2, DB::table('subscriptions')->where('pesantren_id', $tenantId)->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'PROVISION SUBSCRIPTION REJECTED',
            'module' => 'subscriptions',
        ]);
    }

    public function test_rejection_audit_survives_the_rolled_back_transaction(): void
    {
        $this->seed();
        $tenantId = $this->tenantId();
        $this->clearSubscriptions($tenantId);

        try {
            $this->provisioner()->provision($this->intent(['plan_id' => 999999]));
            $this->fail('an unknown plan must be refused');
        } catch (ProvisioningException $exception) {
            $this->assertStringContainsString('999999', $exception->getMessage());
        }

        // The reconciliation rolled back, yet the refusal must remain visible.
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'PROVISION SUBSCRIPTION REJECTED',
            'module' => 'subscriptions',
            'pesantren_id' => $tenantId,
        ]);
        $this->assertSame(0, DB::table('subscriptions')->where('pesantren_id', $tenantId)->count());
    }
}
