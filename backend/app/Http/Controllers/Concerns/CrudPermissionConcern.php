<?php

namespace App\Http\Controllers\Concerns;

use App\Support\ResourceCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait CrudPermissionConcern
{
    private function visibleColumns(string $table): array
    {
        return collect($this->columns($table))
            ->reject(fn (string $column) => $table === 'users' && in_array($column, self::USER_SENSITIVE_COLUMNS, true))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $extra  Additional metadata merged into the audit entry, for
     *                                        example privileged-access reasons on sensitive account changes.
     */
    private function audit(Request $request, string $action, string $table, ?int $rowId = null, ?object $before = null, ?object $after = null, array $extra = []): void
    {
        $user = $request->user();
        if (! $user || $table === 'audit_logs') {
            return;
        }

        $tenantId = null;
        if ($this->hasColumn($table, 'pesantren_id')) {
            $resolvedTenantId = $after?->pesantren_id
                ?? $before?->pesantren_id
                ?? $this->tenantContext->activePesantrenId($request);
            $tenantId = $resolvedTenantId !== null ? (int) $resolvedTenantId : null;
        }

        DB::table('audit_logs')->insert([
            'pesantren_id' => $tenantId,
            'user_id' => $user->id,
            'action' => strtoupper($action).' '.$table,
            'module' => $table,
            'row_id' => $rowId,
            'ip_address' => $request->ip(),
            'metadata' => json_encode(array_merge([
                'row_id' => $rowId,
                'before' => $this->redactRow($table, $before),
                'after' => $this->redactRow($table, $after),
            ], $extra)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function effectiveRoleAccess(Request $request, string $role): array
    {
        return $this->resourceAuthorization->effectiveRoleAccess($request, $role);
    }

    private function permissionTenantId(Request $request): int
    {
        $user = $request->user()?->loadMissing('roles');
        abort_unless($user, 401);
        if (! $this->tenantContext->isSuperAdmin($request)) {
            $this->tenantContext->authorizeActiveRole($request, 'owner_pesantren', false, 'Hanya super admin dan owner tenant aktif yang dapat mengelola custom role.');

            return (int) $this->tenantContext->activePesantrenId($request, 'owner_pesantren');
        }

        $tenantId = $this->tenantContext->activePesantrenId($request);
        abort_unless($tenantId && DB::table('pesantrens')->where('id', $tenantId)->whereNull('deleted_at')->exists(), 422, 'Pilih tenant untuk mengelola custom role.');

        return (int) $tenantId;
    }

    private function validateCustomRole(Request $request, int $tenantId, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_\-]+$/', Rule::unique('tenant_roles', 'name')->where('pesantren_id', $tenantId)->ignore($ignoreId)],
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'read' => ['nullable', 'array'],
            'read.*' => ['string', Rule::in($this->delegableResources())],
            'write' => ['nullable', 'array'],
            'write.*' => ['string', Rule::in($this->delegableResources())],
            'actions' => ['nullable', 'array'],
            'actions.*' => ['string', Rule::in(ResourceCatalog::workflowAbilities())],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $validated['write'] = collect($validated['write'] ?? [])->unique()->values()->all();
        $validated['read'] = collect($validated['read'] ?? [])->merge($validated['write'])->unique()->values()->all();
        $validated['actions'] = collect($validated['actions'] ?? [])->unique()->values()->all();
        $invalidActionResources = collect($validated['actions'])
            ->map(fn (string $ability) => Str::beforeLast($ability, '.'))
            ->diff($validated['write'])
            ->values();
        abort_if($invalidActionResources->isNotEmpty(), 422, 'Aksi workflow memerlukan permission kelola untuk resource terkait: '.$invalidActionResources->implode(', '));
        $validated['status'] = $validated['status'] ?? 'active';

        return $validated;
    }

    private function tenantPermissionOverrides(Request $request, ?int $tenantId = null): array
    {
        return $this->resourceAuthorization->tenantPermissionOverrides($request, $tenantId);
    }

    private function saveTenantPermissionOverrides(int $tenantId, array $overrides): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['pesantren_id' => $tenantId, 'key' => 'permissions.matrix_override'],
            [
                'value' => json_encode($overrides, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function delegableResources(): array
    {
        return $this->resourceAuthorization->delegableResources();
    }

    private function assertGenericMutationAllowed(string $resource, string $operation): void
    {
        abort_if(
            ResourceCatalog::isServiceManaged($resource),
            405,
            'Resource ini hanya dapat diubah melalui layanan khusus.'
        );

        abort_if(
            ResourceCatalog::isWorkflow($resource) && in_array($operation, ['delete', 'bulk'], true),
            405,
            'Resource workflow tidak dapat dihapus secara generik. Gunakan transisi workflow.'
        );
    }

    private function assertWorkflowRecordEditable(string $resource, object $row): void
    {
        if (! ResourceCatalog::isWorkflow($resource)) {
            return;
        }

        abort_unless(
            in_array((string) ($row->status ?? ''), ['pending', 'waiting'], true),
            409,
            'Record workflow yang sudah diproses tidak dapat diubah melalui CRUD generik.',
        );
    }

    private function authorizeResourceAccess(Request $request, string $resource, string $ability): void
    {
        $this->resourceAuthorization->authorize($request, $resource, $ability);
    }
}
