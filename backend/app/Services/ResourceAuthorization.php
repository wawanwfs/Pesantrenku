<?php

namespace App\Services;

use App\Models\User;
use App\Support\ResourceCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResourceAuthorization
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EntitlementService $entitlements,
    ) {}

    public function authorize(Request $request, string $resource, string $ability): void
    {
        abort_unless(in_array($resource, ResourceCatalog::ALL, true), 404);
        abort_unless(in_array($ability, ['read', 'write'], true), 500, 'Ability resource tidak valid.');
        abort_unless($request->user(), 401);
        abort_unless($this->allows($request, $resource, $ability), 403, 'Role tidak memiliki akses ke resource ini.');
        $this->entitlements->assertResource($request, $resource);
    }

    public function authorizeAction(Request $request, string $resource, string $action): void
    {
        $ability = "{$resource}.{$action}";
        abort_unless(in_array($ability, ResourceCatalog::workflowAbilities(), true), 404);
        $this->authorize($request, $resource, 'write');

        $user = $request->user()?->loadMissing('roles');
        abort_unless($user, 401);
        if ($user->hasRole('super_admin')) {
            return;
        }

        $customAccess = $this->customRoleAccessForUser($request);
        if ($customAccess !== null) {
            abort_unless(in_array($ability, $customAccess['actions'] ?? [], true), 403, 'Custom role tidak memiliki izin untuk aksi workflow ini.');

            return;
        }

        $role = $this->tenantContext->activeRole($request);
        $access = $role ? $this->effectiveRoleAccess($request, $role) : ['actions' => []];
        abort_unless(in_array($ability, $access['actions'] ?? [], true), 403, 'Role tenant aktif tidak memiliki izin untuk aksi workflow ini.');
    }

    public function allows(Request $request, string $resource, string $ability): bool
    {
        if (! in_array($resource, ResourceCatalog::ALL, true) || ! in_array($ability, ['read', 'write'], true)) {
            return false;
        }

        $user = $request->user()?->loadMissing('roles');
        if (! $user) {
            return false;
        }
        if ($user->hasRole('super_admin')) {
            return true;
        }

        $customAccess = $this->customRoleAccessForUser($request);
        if ($customAccess !== null) {
            return $this->contains($customAccess, $resource, $ability);
        }

        $role = $this->tenantContext->activeRole($request);

        return $role !== null && $this->contains($this->effectiveRoleAccess($request, $role), $resource, $ability);
    }

    /**
     * Re-authorize an asynchronous actor against current database state.
     *
     * The queued actor context is only an integrity binding. It is never used
     * as the source of truth for current roles or permissions.
     */
    public function assertQueuedActor(
        int $userId,
        ?int $tenantId,
        string $resource,
        string $ability,
        array $actorContext,
    ): void {
        if (
            ! in_array($resource, ResourceCatalog::ALL, true)
            || ! in_array($ability, ['read', 'write'], true)
            || (int) ($actorContext['version'] ?? 0) !== 1
            || (int) ($actorContext['user_id'] ?? 0) !== $userId
        ) {
            throw new \RuntimeException('Binding otorisasi actor operasi antrean tidak valid.');
        }

        $user = User::query()
            ->with('roles')
            ->whereKey($userId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();
        if (! $user) {
            throw new \RuntimeException('Actor operasi antrean sudah tidak aktif.');
        }

        $contextRole = (string) ($actorContext['role'] ?? '');
        if ($contextRole === 'super_admin') {
            if (! $user->hasRole('super_admin')) {
                throw new \RuntimeException('Role platform actor operasi antrean sudah dicabut.');
            }
        } else {
            if (! $tenantId) {
                throw new \RuntimeException('Operasi antrean non-platform tidak memiliki tenant aktif.');
            }

            $membership = DB::table('pesantren_user')
                ->join('roles', 'pesantren_user.role_id', '=', 'roles.id')
                ->join('pesantrens', 'pesantren_user.pesantren_id', '=', 'pesantrens.id')
                ->leftJoin('tenant_roles', function ($join) {
                    $join->on('pesantren_user.tenant_role_id', '=', 'tenant_roles.id')
                        ->on('pesantren_user.pesantren_id', '=', 'tenant_roles.pesantren_id');
                })
                ->where('pesantren_user.id', (int) ($actorContext['membership_id'] ?? 0))
                ->where('pesantren_user.user_id', $userId)
                ->where('pesantren_user.pesantren_id', $tenantId)
                ->where('pesantren_user.status', 'active')
                ->where('pesantrens.status', 'active')
                ->whereNull('pesantrens.deleted_at')
                ->select([
                    'roles.name as role',
                    'pesantren_user.tenant_role_id',
                    'tenant_roles.status as tenant_role_status',
                ])
                ->first();
            $contextTenantRoleId = $actorContext['tenant_role_id'] ?? null;
            $currentTenantRoleId = $membership?->tenant_role_id ? (int) $membership->tenant_role_id : null;
            $expectedTenantRoleId = $contextTenantRoleId ? (int) $contextTenantRoleId : null;

            if (
                ! $membership
                || (string) $membership->role !== $contextRole
                || $currentTenantRoleId !== $expectedTenantRoleId
                || ($currentTenantRoleId !== null && $membership->tenant_role_status !== 'active')
            ) {
                throw new \RuntimeException('Membership atau custom role actor operasi antrean sudah dicabut.');
            }
        }

        $request = Request::create('/', 'GET');
        if ($tenantId) {
            $request->headers->set('X-Pesantren-Id', (string) $tenantId);
        }
        $request->setUserResolver(static fn () => $user);
        $this->tenantContext->invalidateMemberships($userId);

        if (! $this->allows($request, $resource, $ability)) {
            throw new \RuntimeException("Permission {$resource}.{$ability} actor operasi antrean sudah dicabut.");
        }
        if ($tenantId) {
            $this->entitlements->assertQueuedResource($tenantId, $resource);
        }
    }

    public function effectiveRoleAccess(Request $request, string $role): array
    {
        if ($role === 'super_admin') {
            return [
                'read' => ResourceCatalog::ALL,
                'write' => ResourceCatalog::ALL,
                'actions' => ResourceCatalog::workflowAbilities(),
            ];
        }

        $tenantId = $this->tenantContext->activePesantrenId($request);
        $overrides = $this->tenantPermissionOverrides($request, $tenantId);
        if (isset($overrides[$role])) {
            return $this->normalizeDelegableAccess($overrides[$role], $role);
        }

        return $this->globalRolePermissions($role) ?? ResourceCatalog::defaultAccess($role);
    }

    public function tenantPermissionOverrides(Request $request, ?int $tenantId = null): array
    {
        $tenantId ??= $this->tenantContext->activePesantrenId($request);
        if (! $tenantId) {
            return [];
        }

        $value = DB::table('system_settings')
            ->where('pesantren_id', $tenantId)
            ->where('key', 'permissions.matrix_override')
            ->value('value');
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function delegableResources(): array
    {
        return ResourceCatalog::delegable();
    }

    private function customRoleAccessForUser(Request $request): ?array
    {
        $user = $request->user();
        $activeRole = $this->tenantContext->activeRole($request);
        if (! $user || in_array($activeRole, ['super_admin', 'owner_pesantren'], true)) {
            return null;
        }

        $tenantId = $this->tenantContext->activePesantrenId($request);
        $membership = DB::table('pesantren_user')
            ->where('pesantren_user.pesantren_id', $tenantId)
            ->where('pesantren_user.user_id', $user->id)
            ->where('pesantren_user.status', 'active')
            ->orderBy('pesantren_user.id')
            ->select('pesantren_user.id', 'pesantren_user.tenant_role_id')
            ->first();
        if (! $membership?->tenant_role_id) {
            return null;
        }

        $value = DB::table('tenant_roles')
            ->where('tenant_roles.id', $membership->tenant_role_id)
            ->where('tenant_roles.pesantren_id', $tenantId)
            ->where('tenant_roles.status', 'active')
            ->value('tenant_roles.permissions');
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $this->normalizeDelegableAccess($decoded) : null;
    }

    private function globalRolePermissions(string $role): ?array
    {
        $permissions = DB::table('roles')
            ->join('permission_role', 'roles.id', '=', 'permission_role.role_id')
            ->join('permissions', 'permission_role.permission_id', '=', 'permissions.id')
            ->where('roles.name', $role)
            ->pluck('permissions.name')
            ->all();

        if (! $permissions) {
            return null;
        }

        $baseline = ResourceCatalog::defaultAccess($role);
        $readMaximum = collect($this->delegableResources())
            ->merge($baseline['read'])
            ->merge($baseline['write'])
            ->unique();
        $writeMaximum = collect($this->delegableResources())
            ->merge($baseline['write'])
            ->unique();

        $access = [
            'read' => collect($permissions)
                ->filter(fn ($name) => str_ends_with($name, '.read'))
                ->map(fn ($name) => Str::beforeLast($name, '.read'))
                ->intersect($readMaximum)
                ->values()
                ->all(),
            'write' => collect($permissions)
                ->filter(fn ($name) => str_ends_with($name, '.write'))
                ->map(fn ($name) => Str::beforeLast($name, '.write'))
                ->intersect($writeMaximum)
                ->values()
                ->all(),
            'actions' => ResourceCatalog::defaultWorkflowAbilities($role),
        ];

        return $this->normalizeDelegableAccess($access, $role);
    }

    private function normalizeDelegableAccess(array $access, ?string $fallbackRole = null): array
    {
        $write = array_values(array_intersect($access['write'] ?? [], $this->delegableResources()));
        $actions = array_key_exists('actions', $access)
            ? ($access['actions'] ?? [])
            : ($fallbackRole ? ResourceCatalog::defaultWorkflowAbilities($fallbackRole) : []);
        $actions = collect($actions)
            ->filter(fn ($ability) => is_string($ability) && in_array($ability, ResourceCatalog::workflowAbilities(), true))
            ->filter(fn (string $ability) => in_array(Str::beforeLast($ability, '.'), $write, true))
            ->unique()
            ->values()
            ->all();

        return [
            'read' => array_values(array_intersect($access['read'] ?? [], $this->delegableResources())),
            'write' => $write,
            'actions' => $actions,
        ];
    }

    private function contains(array $access, string $resource, string $ability): bool
    {
        $resources = $ability === 'read'
            ? array_unique(array_merge($access['read'] ?? [], $access['write'] ?? []))
            : ($access['write'] ?? []);

        return in_array($resource, $resources, true);
    }
}
