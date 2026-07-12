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
                ->get());

        $memberships = collect($memberships);
        $request->attributes->set($attribute, $memberships);

        return $memberships;
    }

    public function activePesantrenId(Request $request, string|array|null $requiredRoles = null): ?int
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $requested = $this->requestedPesantrenId($request);

        if ($user->hasRole('super_admin')) {
            if (! $requested) {
                return null;
            }

            abort_unless(DB::table('pesantrens')->where('id', $requested)->whereNull('deleted_at')->exists(), 404);

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

        $tenant = Cache::remember("pesantenku:tenant:{$id}", now()->addSeconds(30), fn () => DB::table('pesantrens')
                ->select('id', 'name', 'city', 'address', 'phone', 'email', 'status', 'metadata')
                ->where('id', $id)
                ->whereNull('deleted_at')
                ->first());
        $request->attributes->set($attribute, $tenant);

        return $tenant;
    }

    public function setActive(Request $request, int $pesantrenId): object
    {
        $user = $request->user();
        abort_unless($user, 401);

        if (! $user->hasRole('super_admin')) {
            abort_unless($this->memberships($request)->contains('id', $pesantrenId), 403);
        }

        abort_unless(DB::table('pesantrens')->where('id', $pesantrenId)->whereNull('deleted_at')->exists(), 404);

        if ($request->hasSession()) {
            $request->session()->put('active_pesantren_id', $pesantrenId);
        }

        return $this->activePesantren($request) ?? DB::table('pesantrens')->find($pesantrenId);
    }

    public function applyScope(Request $request, Builder $query, string $table, string|array|null $requiredRoles = null): Builder
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ($table === 'pesantrens' && ! $user->hasRole('super_admin')) {
            $ids = $this->memberships($request)->pluck('id')->all();

            return $query->whereIn('pesantrens.id', $ids ?: [0]);
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

        if (! $this->hasColumn($request, $table, 'pesantren_id')) {
            return $query;
        }

        $tenantId = $this->activePesantrenId($request, $requiredRoles);

        if (! $tenantId) {
            return $query;
        }

        return $query->where("{$table}.pesantren_id", $tenantId);
    }

    public function withTenantPayload(Request $request, string $table, array $data): array
    {
        if (! $this->hasColumn($request, $table, 'pesantren_id')) {
            return $data;
        }

        $tenantId = $this->activePesantrenId($request);

        if ($tenantId) {
            $data['pesantren_id'] = $tenantId;
        }

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

    private function requestedPesantrenId(Request $request): ?int
    {
        $sessionTenantId = $request->hasSession() ? $request->session()->get('active_pesantren_id') : null;

        $value = $request->query('pesantren_id')
            ?? $request->header('X-Pesantren-Id')
            ?? $request->input('pesantren_id')
            ?? $sessionTenantId;

        return $value ? (int) $value : null;
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
