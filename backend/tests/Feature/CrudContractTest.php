<?php

namespace Tests\Feature;

use App\Http\Controllers\CrudController;
use App\Models\User;
use App\Support\ResourceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

class CrudContractTest extends TestCase
{
    use RefreshDatabase;

    private const ROLE_USERS = [
        'owner_pesantren' => 'owner@pesantenku.test',
        'admin_keuangan' => 'keuangan@pesantenku.test',
        'admin_akademik' => 'akademik@pesantenku.test',
        'admin_tu' => 'tu@pesantenku.test',
        'santri' => 'santri@pesantenku.test',
        'wali_murid' => 'wali@pesantenku.test',
        'guru' => 'guru@pesantenku.test',
        'musyrif' => 'musyrif@pesantenku.test',
        'klinik' => 'klinik@pesantenku.test',
    ];

    public function test_every_role_resource_permission_and_read_contract(): void
    {
        $this->seed();
        $allowed = ResourceCatalog::ALL;
        $matrix = ResourceCatalog::DEFAULT_ROLE_ACCESS;

        foreach ($matrix as $role => $access) {
            $user = User::where('email', self::ROLE_USERS[$role])->firstOrFail();
            $readable = array_values(array_unique([...$access['read'], ...$access['write']]));

            foreach ($readable as $resource) {
                $this->actingAs($user)
                    ->getJson("/web-api/{$resource}?per_page=5")
                    ->assertOk();
            }

            $denied = collect($allowed)->first(fn (string $resource) => ! in_array($resource, $readable, true));
            $this->actingAs($user)->getJson("/web-api/{$denied}")->assertForbidden();
            $this->actingAs($user)->postJson("/web-api/{$denied}", [])->assertForbidden();
        }
    }

    public function test_every_writable_resource_rejects_empty_payload_without_server_error(): void
    {
        $this->seed();

        foreach (ResourceCatalog::DEFAULT_ROLE_ACCESS as $role => $access) {
            $user = User::where('email', self::ROLE_USERS[$role])->firstOrFail();
            foreach ($access['write'] as $resource) {
                if ($resource === 'pesantrens') {
                    continue;
                }
                $response = $this->actingAs($user)->postJson("/web-api/{$resource}", []);
                $this->assertSame(
                    422,
                    $response->getStatusCode(),
                    "Payload kosong diterima pada {$role}/{$resource}: {$response->getContent()}",
                );
            }
        }
    }

    public function test_relation_labels_and_tenant_isolation_hold_for_all_readable_resources(): void
    {
        $this->seed();
        $matrix = ResourceCatalog::DEFAULT_ROLE_ACCESS;
        $relations = $this->controllerMethod('relationTables');

        foreach ($matrix as $role => $access) {
            $user = User::where('email', self::ROLE_USERS[$role])->firstOrFail();
            $tenantId = DB::table('pesantren_user')->where('user_id', $user->id)->where('status', 'active')->value('pesantren_id');

            foreach (array_unique([...$access['read'], ...$access['write']]) as $resource) {
                $rows = $this->actingAs($user)->getJson("/web-api/{$resource}?per_page=5")->assertOk()->json('data') ?? [];
                foreach ($rows as $row) {
                    if (isset($row['pesantren_id']) && $tenantId !== null) {
                        $this->assertSame((int) $tenantId, (int) $row['pesantren_id'], "Tenant bocor pada {$role}/{$resource}.");
                    }
                    foreach ($relations as $column => $_relation) {
                        if (! empty($row[$column])) {
                            $label = preg_replace('/_id$/', '', $column).'_name';
                            $this->assertArrayHasKey($label, $row, "Label relasi {$label} hilang pada {$role}/{$resource}.");
                            $this->assertNotSame((string) $row[$column], (string) $row[$label]);
                        }
                    }
                }
            }
        }
    }

    public function test_seeded_writable_resources_support_create_read_update_and_delete(): void
    {
        $this->seed();
        $matrix = ResourceCatalog::DEFAULT_ROLE_ACCESS;
        $resourceActors = [];

        foreach ($matrix as $role => $access) {
            foreach ($access['write'] as $resource) {
                $resourceActors[$resource] ??= $role;
            }
        }

        $resourceActors = collect($resourceActors)
            ->except(['pesantrens', 'permits', 'payments', 'medical-queues'])
            ->all();
        $resourceActors['users'] = 'super_admin';

        foreach ($resourceActors as $resource => $role) {
            $user = $role === 'super_admin'
                ? User::where('email', 'superadmin@pesantenku.test')->firstOrFail()
                : User::where('email', self::ROLE_USERS[$role])->firstOrFail();
            $tenantId = DB::table('pesantren_user')->where('user_id', $user->id)->where('status', 'active')->value('pesantren_id')
                ?? DB::table('pesantrens')->whereNull('deleted_at')->value('id');
            $session = ['active_pesantren_id' => $tenantId];
            $seed = $this->actingAs($user)->withSession($session)
                ->getJson("/web-api/{$resource}?per_page=5")
                ->assertOk()
                ->json('data.0');

            $this->assertNotNull($seed, "Seeder belum menyediakan record kontrak untuk {$resource}.");
            $table = $this->controllerMethod('table', $resource);
            $payload = (array) DB::table($table)->where('id', $seed['id'])->first();
            $payload = collect($payload)->except(['id', 'created_at', 'updated_at', 'deleted_at', 'pesantren_id'])->all();
            $allowedFields = array_keys($this->controllerMethod(
                'validationRules',
                Request::create("/web-api/{$resource}", 'POST'),
                $table,
                true,
            ));
            $payload = collect($payload)->only($allowedFields)->all();
            foreach (['metadata', 'profile', 'settings', 'features', 'items'] as $jsonField) {
                if (isset($payload[$jsonField]) && is_string($payload[$jsonField])) {
                    $payload[$jsonField] = json_decode($payload[$jsonField], true, 512, JSON_THROW_ON_ERROR);
                }
            }
            $payload = $this->uniquePayload($payload, $resource);

            if ($resource === 'memberships') {
                $payload['user_id'] = DB::table('users')->insertGetId([
                    'name' => 'User Contract '.Str::random(6),
                    'email' => 'contract.'.Str::uuid().'@pesantenku.test',
                    'password' => bcrypt('password'),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if ($resource === 'users') {
                $payload['password'] = 'contract-password-123';
            }

            $createResponse = $this->actingAsWithStepUp($user)->withSession($session)
                ->postJson("/web-api/{$resource}", $payload);
            $this->assertTrue(
                $createResponse->isSuccessful(),
                "Create {$resource} gagal dengan {$createResponse->getStatusCode()}: {$createResponse->getContent()}",
            );
            $created = $createResponse->json();
            $id = (int) ($created['id'] ?? 0);
            $this->assertGreaterThan(0, $id, "Create {$resource} tidak mengembalikan ID.");

            $this->actingAs($user)->withSession($session)->getJson("/web-api/{$resource}/{$id}")->assertOk();
            $this->actingAs($user)->withSession($session)->patchJson("/web-api/{$resource}/{$id}", $payload)->assertOk();
            $this->actingAs($user)->withSession($session)->deleteJson("/web-api/{$resource}/{$id}")->assertOk();
            $this->actingAs($user)->withSession($session)->getJson("/web-api/{$resource}/{$id}")->assertNotFound();
        }
    }

    private function uniquePayload(array $payload, string $resource): array
    {
        $suffix = strtolower(Str::random(8));
        foreach (['name', 'title', 'subject', 'email', 'username', 'code', 'nis', 'invoice_number', 'number', 'key', 'module', 'queue_number', 'reference'] as $field) {
            if (! array_key_exists($field, $payload) || $payload[$field] === null) {
                continue;
            }
            $payload[$field] = $field === 'email'
                ? "contract.{$suffix}@pesantenku.test"
                : substr((string) $payload[$field], 0, 180)."-{$suffix}";
        }
        if ($resource === 'attendances' && isset($payload['session'])) {
            $payload['session'] = substr((string) $payload['session'], 0, 80)."-{$suffix}";
        }
        if ($resource === 'dorm-attendances' && isset($payload['activity'])) {
            $payload['activity'] = substr((string) $payload['activity'], 0, 220)."-{$suffix}";
        }

        return $payload;
    }

    private function controllerMethod(string $name, mixed ...$arguments): mixed
    {
        $method = (new ReflectionClass(CrudController::class))->getMethod($name);

        return $method->invoke(app(CrudController::class), ...$arguments);
    }
}
