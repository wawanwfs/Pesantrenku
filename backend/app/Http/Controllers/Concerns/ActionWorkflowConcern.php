<?php

namespace App\Http\Controllers\Concerns;

use App\Events\NotificationChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait ActionWorkflowConcern
{
    public function transition(Request $request, string $resource, int $id, string $transition)
    {
        abort_unless(isset($this->workflowResources[$resource]), 404);
        abort_unless(isset($this->workflowTransitions[$resource][$transition]), 404);
        abort_unless($request->user(), 401);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $config = $this->workflowTransitions[$resource][$transition];
        $table = $this->workflowResources[$resource];
        $tenantId = $this->tenantContext->requireActivePesantrenId($request);
        $note = trim((string) ($validated['note'] ?? ''));
        abort_if(in_array($transition, ['reject', 'cancel'], true) && $note === '', 422, 'Catatan wajib diisi untuk aksi tolak atau batalkan.');

        $after = DB::transaction(function () use ($request, $table, $tenantId, $id, $config, $transition, $resource, $note) {
            // Re-evaluate resource and action permissions inside the mutation
            // transaction immediately before acquiring the workflow row lock.
            $this->resourceAuthorization->authorizeAction($request, $resource, $transition);
            $activeRole = (string) $this->tenantContext->activeRole($request);
            abort_unless($activeRole !== '', 403, 'Role tenant aktif tidak tersedia.');
            $row = DB::table($table)
                ->when(Schema::hasColumn($table, 'pesantren_id'), fn ($query) => $query->where('pesantren_id', $tenantId))
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            abort_unless($row, 404);
            $this->assertWorkflowScope($request, $table, $row);
            abort_unless(in_array($row->status, $config['from'], true), 422, "Status {$row->status} tidak bisa diproses dengan aksi {$transition}.");

            $invoiceStatus = null;
            if ($resource === 'payments' && $transition === 'verify' && ! empty($row->invoice_id)) {
                $invoice = DB::table('invoices')
                    ->where('id', $row->invoice_id)
                    ->where('pesantren_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();
                abort_unless($invoice, 422, 'Tagihan pembayaran tidak ditemukan.');
                abort_unless((int) $invoice->student_id === (int) $row->student_id, 422, 'Santri pembayaran tidak sama dengan pemilik tagihan.');

                $successfulAmount = DB::table('payments')
                    ->where('pesantren_id', $tenantId)
                    ->where('invoice_id', $invoice->id)
                    ->where('id', '!=', $row->id)
                    ->where('status', 'success')
                    ->sum('amount');
                $paidMinor = $this->moneyToMinor($successfulAmount) + $this->moneyToMinor($row->amount);
                $invoiceMinor = $this->moneyToMinor($invoice->amount);
                abort_if($paidMinor > $invoiceMinor, 422, 'Verifikasi ditolak karena total pembayaran melebihi nominal tagihan.');
                $invoiceStatus = $paidMinor === $invoiceMinor ? 'paid' : 'unpaid';
            }

            $updated = DB::table($table)->where('id', $row->id)->where('status', $row->status)->update([
                'status' => $config['to'],
                'updated_at' => now(),
            ]);
            abort_unless($updated === 1, 409, 'Status telah berubah oleh proses lain.');

            if ($invoiceStatus !== null) {
                DB::table('invoices')
                    ->where('id', $row->invoice_id)
                    ->where('pesantren_id', $tenantId)
                    ->update(['status' => $invoiceStatus, 'updated_at' => now()]);
            }

            $after = DB::table($table)->where('id', $row->id)->first();
            $this->auditWorkflow($request, strtoupper($transition).' '.$table, $table, $row, $after, [
                'transition' => $transition,
                'from_status' => $row->status,
                'to_status' => $config['to'],
                'note' => $note,
                'actor_role' => $activeRole,
            ]);
            $this->notifyWorkflowParticipants($resource, $transition, $after, $note);

            return $after;
        }, 3);

        return response()->json([
            'message' => 'Status berhasil diperbarui.',
            'resource' => $resource,
            'id' => $id,
            'status' => $after->status,
        ]);
    }

    private function assertWorkflowScope(Request $request, string $table, object $row): void
    {
        $user = $request->user()?->loadMissing('roles');
        if (! $user) {
            abort(401);
        }

        $activeRole = $this->tenantContext->activeRole($request);
        if (in_array($activeRole, ['santri', 'wali_murid'], true)) {
            $studentIds = $this->accessibleStudentIds($request);
            if (isset($row->student_id)) {
                abort_unless(in_array((int) $row->student_id, $studentIds, true), 403);
            }
        }

        if ($activeRole === 'musyrif' && isset($row->student_id)) {
            $studentIds = $this->dormStudentIds($request);
            abort_unless(in_array((int) $row->student_id, $studentIds, true), 403, 'Santri tidak berada dalam binaan musyrif.');
        }
    }

    private function accessibleStudentIds(Request $request): array
    {
        $user = $request->user()?->loadMissing('roles');
        $tenantId = $this->tenantContext->activePesantrenId($request);

        if ($this->tenantContext->activeRole($request) === 'santri') {
            return DB::table('students')->where('pesantren_id', $tenantId)->where('user_id', $user->id)->pluck('id')->all();
        }

        if ($this->tenantContext->activeRole($request) === 'wali_murid') {
            return DB::table('students')
                ->join('guardians', 'students.guardian_id', '=', 'guardians.id')
                ->where('students.pesantren_id', $tenantId)
                ->where('guardians.user_id', $user->id)
                ->pluck('students.id')
                ->all();
        }

        return [];
    }

    private function dormStudentIds(Request $request): array
    {
        $tenantId = $this->tenantContext->activePesantrenId($request);
        $assignedRoomIds = Schema::hasTable('musyrif_room_assignments')
            ? DB::table('musyrif_room_assignments')
                ->where('pesantren_id', $tenantId)
                ->where('user_id', $request->user()?->id)
                ->where('status', 'active')
                ->where(fn ($query) => $query->whereNull('assigned_at')->orWhere('assigned_at', '<=', today()))
                ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', today()))
                ->pluck('room_id')
                ->filter()
                ->unique()
                ->values()
                ->all()
            : [];

        if ($assignedRoomIds === [] || ! Schema::hasTable('room_assignments')) {
            return [];
        }

        return DB::table('room_assignments')
            ->where('pesantren_id', $tenantId)
            ->where('status', 'active')
            ->whereIn('room_id', $assignedRoomIds)
            ->where(fn ($query) => $query->whereNull('assigned_at')->orWhere('assigned_at', '<=', today()))
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', today()))
            ->pluck('student_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function moneyToMinor(mixed $value): int
    {
        $normalized = trim((string) $value);
        abort_unless(preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized), 422, 'Format nominal pembayaran tidak valid.');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        return ((int) $whole * 100) + (int) $fraction;
    }

    private function auditWorkflow(Request $request, string $action, string $module, object $before, object $after, array $metadata = []): void
    {
        DB::table('audit_logs')->insert([
            'pesantren_id' => $after->pesantren_id ?? $before->pesantren_id ?? null,
            'user_id' => $request->user()?->id,
            'action' => $action,
            'module' => $module,
            'row_id' => $after->id ?? $before->id ?? null,
            'ip_address' => $request->ip(),
            'metadata' => json_encode($metadata + [
                'row_id' => $after->id ?? $before->id,
                'before' => $before,
                'after' => $after,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function notifyWorkflowParticipants(string $resource, string $transition, object $row, string $note = ''): void
    {
        if (! in_array($resource, ['permits', 'payments'], true)) {
            return;
        }

        $studentId = $row->student_id ?? null;
        if (! $studentId && $resource === 'payments' && ! empty($row->invoice_id)) {
            $studentId = DB::table('invoices')->where('id', $row->invoice_id)->value('student_id');
        }

        if (! $studentId) {
            return;
        }

        $student = DB::table('students')->where('id', $studentId)->first();
        if (! $student) {
            return;
        }

        $guardianUserId = $student->guardian_id
            ? DB::table('guardians')->where('id', $student->guardian_id)->value('user_id')
            : null;

        $userIds = collect([$student->user_id ?? null, $guardianUserId])->filter()->unique()->values();
        if ($userIds->isEmpty()) {
            return;
        }

        $resourceLabel = $resource === 'permits' ? 'Perizinan' : 'Pembayaran';
        $statusLabel = [
            'approve' => 'disetujui',
            'verify' => 'diverifikasi',
            'reject' => 'ditolak',
            'cancel' => 'dibatalkan',
        ][$transition] ?? 'diperbarui';

        foreach ($userIds as $userId) {
            DB::table('notification_logs')->insert([
                'pesantren_id' => $row->pesantren_id ?? $student->pesantren_id ?? null,
                'user_id' => $userId,
                'title' => "{$resourceLabel} {$statusLabel}",
                'body' => trim("Status {$resourceLabel} {$student->name} {$statusLabel}. {$note}"),
                'type' => in_array($transition, ['reject', 'cancel'], true) ? 'warning' : 'success',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            NotificationChanged::dispatch($row->pesantren_id ?? $student->pesantren_id ?? null, (int) $userId, [
                'reason' => 'workflow',
                'title' => "{$resourceLabel} {$statusLabel}",
            ]);
        }
    }
}
