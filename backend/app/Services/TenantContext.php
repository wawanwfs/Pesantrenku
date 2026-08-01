<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantContext
{
    /**
     * Tables intentionally available without an active tenant context.
     *
     * Audited 2026-08-01 (backlog item 5: "GLOBAL_TABLES melewati
     * penyaringan tenant"). Every consumer of this list
     * (`isTenantBoundTable()`, reached only from `applyScope()`,
     * `tenantIdForTable()`, and `withTenantPayload()`) is always invoked with
     * a `$table` value that already passed
     * `CrudAuthorizationConcern::table()`'s `ResourceCatalog::ALL` gate, so
     * this list can only ever be consulted for tables reachable through the
     * generic CRUD resource route. Per-entry rationale:
     * - 'users', 'pesantrens': NOT actually unscoped. Both have earlier,
     *   stricter special-case branches in `applyScope()`/`tenantIdForTable()`
     *   below that enforce tenant membership before this list is ever
     *   consulted; their presence here only controls `withTenantPayload()`
     *   (correctly skips injecting `pesantren_id`, a column neither table
     *   has).
     * - 'roles', 'plans': genuinely global platform catalog data with no
     *   `pesantren_id` column. Write access is restricted to `super_admin`
     *   and cannot be delegated to any tenant role or custom role: both are
     *   in `ResourceCatalog::NON_DELEGABLE`, which
     *   `ResourceAuthorization::normalizeDelegableAccess()` strips from every
     *   grant path (default matrix, DB-provisioned role permissions, tenant
     *   `permissions.matrix_override`, and tenant custom roles), even if the
     *   stored override/permissions JSON already contains them. See
     *   `TenantSecurityTest::test_platform_catalog_tables_cannot_be_delegated_via_override_or_custom_role()`.
     * - 'permissions', 'permission_role', 'role_user': not present in
     *   `ResourceCatalog::ALL`, so `CrudAuthorizationConcern::table()` 404s
     *   before a request ever reaches this service. Currently unreachable
     *   (defensive/dead) via the generic CRUD path. See
     *   `TenantSecurityTest::test_dead_global_table_aliases_are_unreachable_through_generic_crud()`.
     */
    private const GLOBAL_TABLES = ['users', 'roles', 'permissions', 'permission_role', 'role_user', 'pesantrens', 'plans'];

    public function memberships(Request $request): Collection
    {
        $user = $request->user();

        if (! $user) {
            return collect();
        }

        $attribute = "pesantenku.memberships.{$user->id}";
        if ($request->attributes->has($attribute)) {
            return $request->attributes->get($attribute);
        }

        $memberships = Cache::remember($this->membershipCacheKey($user->id), now()->addSeconds(30), fn () => DB::table('pesantren_user')
            ->join('pesantrens', 'pesantren_user.pesantren_id', '=', 'pesantrens.id')
            ->join('roles', 'pesantren_user.role_id', '=', 'roles.id')
            ->leftJoin('tenant_roles', 'pesantren_user.tenant_role_id', '=', 'tenant_roles.id')
            ->where('pesantren_user.user_id', $user->id)
            ->where('pesantren_user.status', 'active')
            ->where('pesantrens.status', 'active')
            ->whereNull('pesantrens.deleted_at')
            ->select([
                'pesantrens.id',
                'pesantrens.name',
                'pesantrens.city',
                'pesantrens.status',
                'roles.name as role',
                'roles.label as role_label',
                'tenant_roles.id as tenant_role_id',
                'tenant_roles.name as tenant_role',
                'tenant_roles.label as tenant_role_label',
            ])
            ->orderBy('pesantren_user.id')
            ->get()
            ->map(fn (object $membership) => (array) $membership)
            ->all());

        $memberships = collect($memberships)->map(fn (array $membership) => (object) $membership);
        $request->attributes->set($attribute, $memberships);

        return $memberships;
    }

    public function activePesantrenId(Request $request, string|array|null $requiredRoles = null): ?int
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $requested = $this->requestedPesantrenId($request, ! $user->hasRole('super_admin'));

        if ($user->hasRole('super_admin')) {
            if (! $requested) {
                return null;
            }

            abort_unless(DB::table('pesantrens')->where('id', $requested)->where('status', 'active')->whereNull('deleted_at')->exists(), 404);

            return $requested;
        }

        $memberships = $this->memberships($request);

        if ($requiredRoles !== null) {
            $roles = is_array($requiredRoles) ? $requiredRoles : [$requiredRoles];
            $memberships = $memberships->whereIn('role', $roles);
        }

        abort_if($memberships->isEmpty(), 403, 'Akun tidak memiliki akses tenant untuk role ini.');

        if ($requested) {
            abort_unless($memberships->contains('id', $requested), 403, 'Akun tidak memiliki akses ke pesantren tersebut.');

            return $requested;
        }

        return (int) $memberships->first()->id;
    }

    public function requireActivePesantrenId(Request $request, string|array|null $requiredRoles = null): int
    {
        $tenantId = $this->activePesantrenId($request, $requiredRoles);
        abort_unless($tenantId, 422, 'Pilih tenant aktif melalui header X-Pesantren-Id sebelum mengakses resource tenant.');

        return $tenantId;
    }

    public function tenantIdForTable(Request $request, string $table, string|array|null $requiredRoles = null): ?int
    {
        if ($table === 'pesantrens' && ! $this->isSuperAdmin($request)) {
            return $this->requireActivePesantrenId($request, $requiredRoles);
        }

        if (! $this->isTenantBoundTable($request, $table)) {
            return null;
        }

        return $this->requireActivePesantrenId($request, $requiredRoles);
    }

    public function activePesantren(Request $request, string|array|null $requiredRoles = null): ?object
    {
        $id = $this->activePesantrenId($request, $requiredRoles);

        if (! $id) {
            return null;
        }

        $attribute = "pesantenku.active-pesantren.{$id}";
        if ($request->attributes->has($attribute)) {
            return $request->attributes->get($attribute);
        }

        $tenant = Cache::remember("pesantenku:tenant:{$id}", now()->addSeconds(30), fn () => (array) DB::table('pesantrens')
            ->select('id', 'name', 'city', 'address', 'phone', 'email', 'status', 'metadata')
            ->where('id', $id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first());
        $tenant = $tenant ? (object) $tenant : null;
        $request->attributes->set($attribute, $tenant);

        return $tenant;
    }

    public function isSuperAdmin(Request $request): bool
    {
        $user = $request->user()?->loadMissing('roles');

        return (bool) $user?->hasRole('super_admin');
    }

    public function activeMembership(Request $request): ?object
    {
        if ($this->isSuperAdmin($request)) {
            return null;
        }

        $tenantId = $this->activePesantrenId($request);

        return $this->memberships($request)->firstWhere('id', $tenantId);
    }

    public function activeRole(Request $request): ?string
    {
        if (! $request->user()) {
            return null;
        }

        if ($this->isSuperAdmin($request)) {
            return 'super_admin';
        }

        return $this->activeMembership($request)?->role;
    }

    public function hasActiveRole(Request $request, string|array $roles, bool $allowSuperAdmin = false): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];
        $activeRole = $this->activeRole($request);

        if ($activeRole === 'super_admin') {
            return $allowSuperAdmin || in_array('super_admin', $roles, true);
        }

        return $activeRole !== null && in_array($activeRole, $roles, true);
    }

    public function authorizeActiveRole(Request $request, string|array $roles, bool $allowSuperAdmin = false, string $message = 'Role tenant aktif tidak memiliki akses.'): string
    {
        abort_unless($request->user(), 401);
        abort_unless($this->hasActiveRole($request, $roles, $allowSuperAdmin), 403, $message);

        return (string) $this->activeRole($request);
    }

    public function setActive(Request $request, int $pesantrenId): object
    {
        $user = $request->user();
        abort_unless($user, 401);

        if (! $user->hasRole('super_admin')) {
            abort_unless($this->memberships($request)->contains('id', $pesantrenId), 403);
        }

        abort_unless(DB::table('pesantrens')->where('id', $pesantrenId)->where('status', 'active')->whereNull('deleted_at')->exists(), 404);

        if ($request->hasSession()) {
            $request->session()->put('active_pesantren_id', $pesantrenId);
        }

        // An API tenant switch commonly arrives with the previous tenant in
        // X-Pesantren-Id. Keep the newly selected tenant authoritative for the
        // remainder of this request without trusting mutable client input.
        $request->attributes->set('pesantenku.requested-pesantren-id', $pesantrenId);

        return $this->activePesantren($request) ?? DB::table('pesantrens')->find($pesantrenId);
    }

    public function applyScope(Request $request, Builder $query, string $table, string|array|null $requiredRoles = null): Builder
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ($table === 'pesantrens' && ! $user->hasRole('super_admin')) {
            $tenantId = $this->requireActivePesantrenId($request, $requiredRoles);

            return $query->where('pesantrens.id', $tenantId);
        }

        if ($table === 'users' && ! $user->hasRole('super_admin')) {
            $tenantId = $this->activePesantrenId($request, $requiredRoles);

            return $query->whereExists(function ($subQuery) use ($tenantId) {
                $subQuery->selectRaw('1')
                    ->from('pesantren_user')
                    ->whereColumn('pesantren_user.user_id', 'users.id')
                    ->where('pesantren_user.pesantren_id', $tenantId)
                    ->where('pesantren_user.status', 'active');
            });
        }

        if (! $this->isTenantBoundTable($request, $table)) {
            return $query;
        }

        $tenantId = $this->requireActivePesantrenId($request, $requiredRoles);

        return $query->where("{$table}.pesantren_id", $tenantId);
    }

    public function withTenantPayload(Request $request, string $table, array $data): array
    {
        if (! $this->isTenantBoundTable($request, $table)) {
            return $data;
        }

        $data['pesantren_id'] = $this->requireActivePesantrenId($request);

        return $data;
    }

    public function invalidateMemberships(?int $userId): void
    {
        if ($userId) {
            Cache::forget($this->membershipCacheKey($userId));
        }
    }

    public function invalidatePesantren(?int $pesantrenId): void
    {
        if ($pesantrenId) {
            Cache::forget("pesantenku:tenant:{$pesantrenId}");
        }
    }

    private function requestedPesantrenId(Request $request, bool $allowRequestParameters = true): ?int
    {
        $internalTenantId = $request->attributes->get('pesantenku.requested-pesantren-id');
        if ($internalTenantId) {
            return (int) $internalTenantId;
        }

        $sessionTenantId = $request->hasSession() ? $request->session()->get('active_pesantren_id') : null;

        $value = $request->header('X-Pesantren-Id')
            ?? $sessionTenantId;
        if (($value === null || $value === '') && $allowRequestParameters) {
            $value = $request->input('pesantren_id')
                ?? $request->query('pesantren_id');
        }

        if ($value === null || $value === '') {
            return null;
        }

        $validated = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        abort_if($validated === false, 422, 'Identitas pesantren harus berupa bilangan bulat positif.');

        return (int) $validated;
    }

    private function isTenantBoundTable(Request $request, string $table): bool
    {
        if (in_array($table, self::GLOBAL_TABLES, true)) {
            return false;
        }

        return $this->hasColumn($request, $table, 'pesantren_id');
    }

    private function hasColumn(Request $request, string $table, string $column): bool
    {
        $attribute = "pesantenku.schema-columns.{$table}";
        if (! $request->attributes->has($attribute)) {
            $request->attributes->set($attribute, Schema::getColumnListing($table));
        }

        return in_array($column, $request->attributes->get($attribute, []), true);
    }

    private function membershipCacheKey(int $userId): string
    {
        return "pesantenku:tenant-memberships:user:{$userId}";
    }
}
