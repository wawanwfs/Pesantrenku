<?php

namespace App\Http\Controllers;

use App\Events\NotificationChanged;
use App\Services\OperationStorage;
use App\Services\BackupCipher;
use App\Services\BackupRestoreService;
use App\Services\RestoreApprovalService;
use App\Services\TotpService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ActionController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly OperationStorage $operationStorage,
        private readonly BackupCipher $backupCipher,
        private readonly BackupRestoreService $backupRestore,
        private readonly RestoreApprovalService $restoreApprovals,
        private readonly TotpService $totp,
    ) {}

    private array $workflowResources = [
        'permits' => 'permits',
        'payments' => 'payments',
        'medical-queues' => 'medical_queues',
    ];

    private array $workflowTransitions = [
        'permits' => [
            'approve' => ['from' => ['pending', 'waiting'], 'to' => 'approved', 'roles' => ['musyrif', 'admin_tu', 'owner_pesantren']],
            'reject' => ['from' => ['pending', 'waiting'], 'to' => 'rejected', 'roles' => ['musyrif', 'admin_tu', 'owner_pesantren']],
            'cancel' => ['from' => ['pending', 'approved'], 'to' => 'cancelled', 'roles' => ['santri', 'wali_murid', 'musyrif', 'admin_tu', 'owner_pesantren']],
        ],
        'payments' => [
            'verify' => ['from' => ['pending', 'waiting', 'paid'], 'to' => 'success', 'roles' => ['admin_keuangan', 'owner_pesantren']],
            'reject' => ['from' => ['pending', 'waiting', 'paid'], 'to' => 'rejected', 'roles' => ['admin_keuangan', 'owner_pesantren']],
        ],
        'medical-queues' => [
            'start' => ['from' => ['waiting', 'pending'], 'to' => 'in_progress', 'roles' => ['klinik']],
            'finish' => ['from' => ['waiting', 'pending', 'in_progress'], 'to' => 'done', 'roles' => ['klinik']],
            'cancel' => ['from' => ['waiting', 'pending', 'in_progress'], 'to' => 'cancelled', 'roles' => ['klinik']],
        ],
    ];

    public function readNotifications(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);

        $tenantId = $user->hasRole('super_admin') ? null : $this->tenantContext->activePesantrenId($request);
        $ids = collect($request->input('ids', []))->filter()->values();

        $query = DB::table('notification_logs')
            ->whereNull('read_at')
            ->where(function ($query) use ($user) {
                $query->whereNull('user_id')->orWhere('user_id', $user->id);
            });

        $tenantId
            ? $query->where('pesantren_id', $tenantId)
            : $query->whereNull('pesantren_id');

        if ($ids->isNotEmpty()) {
            $query->whereIn('id', $ids->all());
        }

        $updated = $query->update(['read_at' => now(), 'updated_at' => now()]);
        NotificationChanged::dispatch($tenantId, $user->id, ['reason' => 'read', 'unread' => 0]);

        return response()->json(['message' => 'Notifikasi ditandai terbaca.', 'updated' => $updated]);
    }

    public function generateQr(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);

        $tenantId = $user->hasRole('super_admin') ? null : $this->tenantContext->activePesantrenId($request);
        $code = 'PESANTENKU-'.$user->id.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
        $expiresAt = now()->addHours(12);

        DB::table('qr_tokens')->insert([
            'pesantren_id' => $tenantId,
            'user_id' => $user->id,
            'code' => $code,
            'purpose' => $request->input('purpose', 'identity'),
            'status' => 'active',
            'expires_at' => $expiresAt,
            'metadata' => json_encode(['agent' => $request->userAgent()]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('audit_logs')->insert([
            'pesantren_id' => $tenantId,
            'user_id' => $user->id,
            'action' => 'QR generated',
            'module' => 'QR',
            'ip_address' => $request->ip(),
            'metadata' => json_encode(['code' => $code]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'code' => $code,
            'generated_at' => now()->toDateTimeString(),
            'expires_at' => $expiresAt->toDateTimeString(),
        ]);
    }

    public function scanQr(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:120'],
        ]);

        $user = $request->user();
        abort_unless($user, 401);

        $tenantId = $user->hasRole('super_admin') ? null : $this->tenantContext->activePesantrenId($request);
        $token = DB::table('qr_tokens')
            ->where('code', $validated['code'])
            ->when($tenantId, fn ($query) => $query->where('pesantren_id', $tenantId), fn ($query) => $query->whereNull('pesantren_id'))
            ->first();

        abort_unless($token, 404, 'QR tidak ditemukan.');
        abort_unless($token->status === 'active', 422, 'QR sudah tidak aktif.');
        abort_if($token->expires_at && now()->greaterThan($token->expires_at), 422, 'QR sudah kedaluwarsa.');

        DB::table('qr_tokens')->where('id', $token->id)->update([
            'status' => 'used',
            'scanned_at' => now(),
            'scanned_by' => $user->id,
            'updated_at' => now(),
        ]);

        DB::table('audit_logs')->insert([
            'pesantren_id' => $tenantId,
            'user_id' => $user->id,
            'action' => 'QR scanned',
            'module' => 'QR',
            'ip_address' => $request->ip(),
            'metadata' => json_encode(['code' => $validated['code'], 'status' => 'success']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'QR berhasil diproses.', 'status' => 'success', 'token_id' => $token->id]);
    }

    public function transition(Request $request, string $resource, int $id, string $transition)
    {
        abort_unless(isset($this->workflowResources[$resource]), 404);
        abort_unless(isset($this->workflowTransitions[$resource][$transition]), 404);

        $user = $request->user()?->loadMissing('roles');
        abort_unless($user, 401);

        $config = $this->workflowTransitions[$resource][$transition];
        abort_unless($user->hasRole($config['roles']), 403, 'Role tidak memiliki akses workflow ini.');

        $table = $this->workflowResources[$resource];
        $tenantId = $user->hasRole('super_admin') ? null : $this->tenantContext->activePesantrenId($request);
        $row = DB::table($table)
            ->when($tenantId && Schema::hasColumn($table, 'pesantren_id'), fn ($query) => $query->where('pesantren_id', $tenantId))
            ->where('id', $id)
            ->first();

        abort_unless($row, 404);
        $this->assertWorkflowScope($request, $table, $row);
        abort_unless(in_array($row->status, $config['from'], true), 422, "Status {$row->status} tidak bisa diproses dengan aksi {$transition}.");
        $note = trim((string) $request->input('note', ''));
        abort_if(in_array($transition, ['reject', 'cancel'], true) && $note === '', 422, 'Catatan wajib diisi untuk aksi tolak atau batalkan.');

        DB::transaction(function () use ($request, $table, $row, $config, $transition, $resource, $user, $note) {
            DB::table($table)->where('id', $row->id)->update([
                'status' => $config['to'],
                'updated_at' => now(),
            ]);

            if ($resource === 'payments' && $transition === 'verify' && ! empty($row->invoice_id)) {
                DB::table('invoices')->where('id', $row->invoice_id)->update([
                    'status' => 'paid',
                    'updated_at' => now(),
                ]);
            }

            $after = DB::table($table)->where('id', $row->id)->first();
            $this->auditWorkflow($request, strtoupper($transition).' '.$table, $table, $row, $after, [
                'transition' => $transition,
                'from_status' => $row->status,
                'to_status' => $config['to'],
                'note' => $note,
                'actor_role' => $user->role_names,
            ]);
        $this->notifyWorkflowParticipants($resource, $transition, $after, $note);
        });

        return response()->json([
            'message' => 'Status berhasil diperbarui.',
            'resource' => $resource,
            'id' => $id,
            'status' => $config['to'],
        ]);
    }

    public function notifications(Request $request)
    {
        return response()->json($this->notificationPayload($request));
    }

    public function notificationStream(Request $request)
    {
        return response()->stream(function () use ($request) {
            $payload = $this->notificationPayload($request);
            echo "event: notifications\n";
            echo 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE)."\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function jobs(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);

        $jobs = $this->jobQueryForUser($request)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->query('type')))
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn ($job) => $this->formatJob($job));

        return response()->json([
            'data' => $jobs,
            'summary' => [
                'queued' => $jobs->where('status', 'queued')->count(),
                'processing' => $jobs->where('status', 'processing')->count(),
                'completed' => $jobs->where('status', 'completed')->count(),
                'failed' => $jobs->where('status', 'failed')->count(),
            ],
        ]);
    }

    public function jobStatus(Request $request, string $uuid)
    {
        $user = $request->user();
        abort_unless($user, 401);

        $job = $this->jobQueryForUser($request)->where('uuid', $uuid)->first();

        abort_unless($job, 404);

        return response()->json($this->formatJob($job));
    }

    public function downloadJob(Request $request, string $uuid)
    {
        $job = $this->jobQueryForUser($request)->where('uuid', $uuid)->first();
        abort_unless($job, 404);
        abort_unless($job->status === 'completed', 422, 'File belum siap diunduh.');
        abort_if($job->expires_at && now()->greaterThan($job->expires_at), 410, 'File job sudah kedaluwarsa.');
        $path = $job->output_path ?: $job->file_path;
        abort_unless($path && $this->operationStorage->exists($path), 404, 'File hasil job tidak ditemukan.');

        if ($this->operationStorage->diskName() !== 'local' && $url = $this->operationStorage->temporaryUrl($path, (int) config('pesantrenku.operations.signed_url_minutes', 10))) {
            return redirect()->away($url);
        }

        return $this->operationStorage->download($path, basename($path));
    }

    public function deleteJob(Request $request, string $uuid)
    {
        $job = $this->jobQueryForUser($request)->where('uuid', $uuid)->first();
        abort_unless($job, 404);
        foreach (array_unique(array_filter([$job->source_path, $job->output_path, $job->file_path])) as $path) {
            $this->operationStorage->delete($path);
        }
        DB::table('operation_jobs')->where('id', $job->id)->delete();

        return response()->json(['message' => 'File dan riwayat job berhasil dihapus.']);
    }

    public function queueMonitor(Request $request)
    {
        $user = $request->user()?->loadMissing('roles');
        abort_unless($user && $user->hasRole(['super_admin', 'owner_pesantren']), 403);
        $operations = $this->jobQueryForUser($request)->get();
        $failed = ($user->hasRole('super_admin') ? DB::table('failed_jobs')->latest('id')->limit(25)->get() : collect())->map(function ($row) {
            $payload = json_decode((string) $row->payload, true) ?: [];
            return [
                'id' => $row->id,
                'uuid' => $row->uuid,
                'queue' => $row->queue,
                'job' => data_get($payload, 'displayName', 'Unknown Job'),
                'failed_at' => $row->failed_at,
                'exception' => Str::limit((string) $row->exception, 500),
            ];
        });

        return response()->json([
            'workers' => [
                'pending' => DB::table('jobs')->whereNull('reserved_at')->count(),
                'reserved' => DB::table('jobs')->whereNotNull('reserved_at')->count(),
                'failed' => $user->hasRole('super_admin') ? DB::table('failed_jobs')->count() : $operations->where('status', 'failed')->count(),
            ],
            'operations' => [
                'queued' => $operations->where('status', 'queued')->count(),
                'processing' => $operations->where('status', 'processing')->count(),
                'completed' => $operations->where('status', 'completed')->count(),
                'failed' => $operations->where('status', 'failed')->count(),
            ],
            'failed_jobs' => $failed,
        ]);
    }

    public function retryFailedJob(Request $request, string $uuid)
    {
        $this->assertQueueAdmin($request, true);
        abort_unless(DB::table('failed_jobs')->where('uuid', $uuid)->exists(), 404);
        Artisan::call('queue:retry', ['id' => [$uuid]]);

        return response()->json(['message' => 'Failed job dikirim kembali ke queue.']);
    }

    public function forgetFailedJob(Request $request, string $uuid)
    {
        $this->assertQueueAdmin($request, true);
        $deleted = DB::table('failed_jobs')->where('uuid', $uuid)->delete();
        abort_unless($deleted, 404);

        return response()->json(['message' => 'Failed job dihapus dari monitoring.']);
    }

    private function notificationPayload(Request $request): array
    {
        $user = $request->user();
        abort_unless($user, 401);

        $tenantId = $user->hasRole('super_admin') ? null : $this->tenantContext->activePesantrenId($request);
        $query = DB::table('notification_logs')
            ->where(function ($query) use ($user) {
                $query->whereNull('user_id')->orWhere('user_id', $user->id);
            });

        $tenantId
            ? $query->where('pesantren_id', $tenantId)
            : $query->whereNull('pesantren_id');

        $unread = (clone $query)->whereNull('read_at')->count();
        $items = $query->latest('id')->limit(20)->get();

        return [
            'data' => $items,
            'unread' => $unread,
            'polled_at' => now()->toDateTimeString(),
        ];
    }

    private function formatJob(object $job): array
    {
        $path = $job->output_path ?: $job->file_path;
        return [
            'uuid' => $job->uuid,
            'type' => $job->type,
            'resource' => $job->resource,
            'status' => $job->status,
            'progress' => (int) ($job->progress ?? 0),
            'total_rows' => (int) ($job->total_rows ?? 0),
            'processed_rows' => (int) ($job->processed_rows ?? 0),
            'queue_name' => $job->queue_name,
            'file_path' => $job->file_path,
            'file_name' => $path ? basename($path) : null,
            'file_size' => $path && $this->operationStorage->exists($path) ? $this->operationStorage->size($path) : null,
            'download_url' => $job->status === 'completed' && $path ? "/web-actions/jobs/{$job->uuid}/download" : null,
            'signed_url' => $job->status === 'completed' && $path
                ? $this->operationStorage->temporaryUrl($path, (int) config('pesantrenku.operations.signed_url_minutes', 10))
                : null,
            'result' => json_decode((string) $job->result, true) ?: null,
            'error' => $job->error,
            'created_at' => $job->created_at,
            'started_at' => $job->started_at,
            'finished_at' => $job->finished_at,
            'expires_at' => $job->expires_at ?? null,
        ];
    }

    private function jobQueryForUser(Request $request)
    {
        $user = $request->user()?->loadMissing('roles');
        abort_unless($user, 401);
        $tenantId = $user->hasRole('super_admin') ? null : $this->tenantContext->activePesantrenId($request);

        return DB::table('operation_jobs')
            ->when($tenantId, fn ($query) => $query->where('pesantren_id', $tenantId))
            ->when(! $tenantId && ! $user->hasRole('super_admin'), fn ($query) => $query->where('user_id', $user->id))
            ->when(! $user->hasRole(['super_admin', 'owner_pesantren']), fn ($query) => $query->where('user_id', $user->id));
    }

    private function assertQueueAdmin(Request $request, bool $platformOnly = false): void
    {
        $user = $request->user()?->loadMissing('roles');
        abort_unless($user && $user->hasRole(['super_admin', 'owner_pesantren']), 403);
        abort_if($platformOnly && ! $user->hasRole('super_admin'), 403, 'Failed queue global hanya dapat dikelola super admin.');
    }

    public function runBackup(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless($user->hasRole('super_admin') || $user->hasRole('owner_pesantren'), 403);

        $tenantId = $user->hasRole('super_admin') ? null : $this->tenantContext->activePesantrenId($request, 'owner_pesantren');
        $name = ($tenantId ? "tenant-{$tenantId}" : 'platform').'-backup-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(8)).'.backup.enc';
        $filePath = 'backups/'.$name;
        $snapshot = $this->backupSnapshot($tenantId, $user->id);
        $plaintext = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $encrypted = $this->backupCipher->encrypt($plaintext);
        abort_unless($this->operationStorage->put($filePath, $encrypted['contents']), 500, 'Backup tidak dapat disimpan.');

        $id = DB::table('backups')->insertGetId([
            'pesantren_id' => $tenantId,
            'name' => $name,
            'status' => 'success',
            'file_path' => $filePath,
            'encryption_provider' => $encrypted['provider'],
            'encryption_key_id' => $encrypted['key_id'],
            'checksum_sha256' => $encrypted['checksum'],
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('audit_logs')->insert([
            'pesantren_id' => $tenantId,
            'user_id' => $user->id,
            'action' => 'Backup dijalankan',
            'module' => 'Backup',
            'ip_address' => $request->ip(),
            'metadata' => json_encode(['backup_id' => $id, 'name' => $name, 'checksum_sha256' => $encrypted['checksum'], 'disk' => $this->operationStorage->diskName(), 'encryption_provider' => $encrypted['provider'], 'key_id' => $encrypted['key_id']]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Backup berhasil dijalankan.', 'status' => 'success', 'backup_id' => $id]);
    }

    public function downloadBackup(Request $request, int $id)
    {
        $backup = $this->scopedBackup($request, $id);
        abort_unless($backup->file_path && $this->operationStorage->exists($backup->file_path), 404, 'File backup tidak ditemukan.');

        if ($this->operationStorage->diskName() !== 'local' && $url = $this->operationStorage->temporaryUrl($backup->file_path, (int) config('pesantrenku.operations.signed_url_minutes', 10))) {
            return redirect()->away($url);
        }

        return $this->operationStorage->download($backup->file_path, $backup->name);
    }

    public function restoreBackup(Request $request, int $id)
    {
        $validated = $request->validate([
            'confirmation' => ['required', 'in:RESTORE'],
            'mfa_code' => ['required', 'digits:6'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $backup = $this->scopedBackup($request, $id);
        abort_unless($backup->pesantren_id, 422, 'Restore saat ini hanya mendukung backup tenant.');
        $user = $request->user()?->loadMissing('roles');
        abort_unless($user && $this->totp->verify($user, $validated['mfa_code']), 422, 'Kode MFA tidak valid atau sudah kedaluwarsa.');
        $restoreRequest = $this->restoreApprovals->create($backup, $user, $validated['reason']);
        $this->auditRestore($request, 'Permintaan restore dibuat', $restoreRequest, ['backup_id' => $id]);

        return response()->json([
            'message' => 'Permintaan restore dibuat dan menunggu persetujuan berlapis.',
            'request' => $this->formatRestoreRequest($restoreRequest),
        ], 202);
    }

    public function restoreRequests(Request $request)
    {
        $user = $request->user()?->loadMissing('roles');
        abort_unless($user && $user->hasRole(['super_admin', 'owner_pesantren']), 403);
        $tenantId = $user->hasRole('super_admin') ? null : $this->tenantContext->activePesantrenId($request, 'owner_pesantren');
        $rows = DB::table('backup_restore_requests as restore_requests')
            ->join('backups', 'backups.id', '=', 'restore_requests.backup_id')
            ->join('users', 'users.id', '=', 'restore_requests.requested_by')
            ->when($tenantId, fn ($query) => $query->where('restore_requests.pesantren_id', $tenantId))
            ->select('restore_requests.*', 'backups.name as backup_name', 'users.name as requester_name')
            ->latest('restore_requests.id')
            ->limit(100)
            ->get()
            ->map(fn ($row) => $this->formatRestoreRequest($row));

        return response()->json(['data' => $rows]);
    }

    public function approveRestore(Request $request, int $id)
    {
        $validated = $request->validate(['mfa_code' => ['required', 'digits:6']]);
        $user = $request->user()?->loadMissing('roles');
        abort_unless($user && $user->hasRole(['super_admin', 'owner_pesantren']), 403);
        abort_unless($this->totp->verify($user, $validated['mfa_code']), 422, 'Kode MFA tidak valid atau sudah kedaluwarsa.');
        $row = DB::table('backup_restore_requests')->find($id);
        abort_unless($row, 404);
        if (! $user->hasRole('super_admin')) {
            abort_unless((int) $row->pesantren_id === (int) $this->tenantContext->activePesantrenId($request, 'owner_pesantren'), 403);
        }
        try {
            $updated = $this->restoreApprovals->approve($row, $user);
        } catch (\RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }
        $this->auditRestore($request, 'Permintaan restore disetujui', $updated);

        return response()->json(['message' => 'Persetujuan restore dicatat.', 'request' => $this->formatRestoreRequest($updated)]);
    }

    public function executeRestore(Request $request, int $id)
    {
        $validated = $request->validate(['mfa_code' => ['required', 'digits:6']]);
        $user = $request->user()?->loadMissing('roles');
        abort_unless($user && $user->hasRole('super_admin'), 403, 'Eksekusi restore hanya dapat dilakukan super admin.');
        abort_unless($this->totp->verify($user, $validated['mfa_code']), 422, 'Kode MFA tidak valid atau sudah kedaluwarsa.');
        $row = DB::table('backup_restore_requests')->find($id);
        abort_unless($row && $row->status === 'approved', 422, 'Permintaan restore belum mendapat seluruh persetujuan.');
        $backup = DB::table('backups')->find($row->backup_id);
        abort_unless($backup && $backup->pesantren_id, 404);
        DB::table('backup_restore_requests')->where('id', $id)->update(['status' => 'executing', 'updated_at' => now()]);
        DB::table('backups')->where('id', $backup->id)->update(['status' => 'restoring', 'updated_at' => now()]);
        try {
            $restored = $this->backupRestore->restore($backup);
            DB::table('backups')->where('id', $backup->id)->update(['status' => 'restored', 'completed_at' => now(), 'updated_at' => now()]);
            DB::table('backup_restore_requests')->where('id', $id)->update([
                'status' => 'executed',
                'executed_at' => now(),
                'executed_by' => $user->id,
                'result' => json_encode(['restored_rows' => $restored]),
                'updated_at' => now(),
            ]);
            $executed = DB::table('backup_restore_requests')->find($id);
            $this->auditRestore($request, 'Backup dipulihkan setelah persetujuan berlapis', $executed, ['restored_rows' => $restored]);

            return response()->json(['message' => 'Backup tenant berhasil dipulihkan.', 'restored_rows' => $restored, 'request' => $this->formatRestoreRequest($executed)]);
        } catch (\Throwable $exception) {
            DB::table('backups')->where('id', $backup->id)->update(['status' => 'restore_failed', 'updated_at' => now()]);
            DB::table('backup_restore_requests')->where('id', $id)->update(['status' => 'failed', 'result' => $exception->getMessage(), 'updated_at' => now()]);
            throw $exception;
        }
    }

    private function backupSnapshot(?int $tenantId, int $userId): array
    {
        $tables = [
            'pesantrens', 'subscriptions', 'tenant_modules', 'pesantren_user',
            'academic_years', 'programs', 'classes', 'subjects', 'teacher_assignments', 'guardians', 'teachers', 'staff', 'students',
            'rooms', 'room_assignments', 'musyrif_room_assignments', 'attendances', 'dorm_attendances', 'grades', 'report_cards',
            'memorization_records', 'schedules', 'assignments', 'materials', 'announcements', 'violations',
            'permits', 'daily_notes', 'payment_methods', 'bank_accounts', 'invoices', 'invoice_items',
            'payments', 'expense_categories', 'expenses', 'cashflow_entries', 'discounts', 'letters',
            'room_mutations', 'alumni', 'clinics', 'doctors', 'medicines', 'medicine_stocks',
            'medical_queues', 'medical_records', 'medical_examinations', 'prescriptions', 'sick_letters',
            'referrals', 'notification_logs', 'audit_logs', 'system_settings', 'backups', 'qr_tokens',
            'operation_jobs',
            'tenant_roles',
        ];

        $data = [];
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $query = DB::table($table);
            if ($tenantId && Schema::hasColumn($table, 'pesantren_id')) {
                $query->where('pesantren_id', $tenantId);
            }

            $data[$table] = $query->limit(1000)->get();
        }

        return [
            'generated_at' => now()->toDateTimeString(),
            'generated_by' => $userId,
            'tenant_id' => $tenantId,
            'tables' => $data,
        ];
    }

    private function scopedBackup(Request $request, int $id): object
    {
        $user = $request->user()?->loadMissing('roles');
        abort_unless($user && $user->hasRole(['super_admin', 'owner_pesantren']), 403);
        $tenantId = $user->hasRole('super_admin') ? null : $this->tenantContext->activePesantrenId($request, 'owner_pesantren');
        $backup = DB::table('backups')->where('id', $id)->when($tenantId, fn ($query) => $query->where('pesantren_id', $tenantId))->first();
        abort_unless($backup, 404);

        return $backup;
    }

    private function restorableTenantTables(): array
    {
        return [
            'subscriptions', 'tenant_modules', 'academic_years', 'programs', 'classes', 'subjects',
            'teacher_assignments', 'guardians', 'teachers', 'staff', 'students', 'rooms', 'room_assignments',
            'musyrif_room_assignments', 'attendances', 'dorm_attendances', 'grades', 'report_cards',
            'memorization_records', 'schedules', 'assignments', 'materials', 'announcements', 'violations',
            'permits', 'daily_notes', 'payment_methods', 'bank_accounts', 'invoices', 'invoice_items',
            'payments', 'expense_categories', 'expenses', 'cashflow_entries', 'discounts', 'letters',
            'room_mutations', 'alumni', 'clinics', 'doctors', 'medicines', 'medicine_stocks',
            'medical_queues', 'medical_records', 'medical_examinations', 'prescriptions', 'sick_letters',
            'referrals', 'system_settings', 'tenant_roles', 'pesantren_user',
        ];
    }

    private function formatRestoreRequest(object $row): array
    {
        return [
            'id' => $row->id,
            'backup_id' => $row->backup_id,
            'backup_name' => $row->backup_name ?? null,
            'pesantren_id' => $row->pesantren_id,
            'requested_by' => $row->requested_by,
            'requester_name' => $row->requester_name ?? null,
            'status' => $row->status,
            'reason' => $row->reason,
            'approvals' => json_decode((string) $row->approvals, true) ?: [],
            'expires_at' => $row->expires_at,
            'approved_at' => $row->approved_at,
            'executed_at' => $row->executed_at,
            'result' => is_string($row->result ?? null) ? (json_decode($row->result, true) ?: $row->result) : ($row->result ?? null),
            'created_at' => $row->created_at,
        ];
    }

    private function auditRestore(Request $request, string $action, object $restoreRequest, array $metadata = []): void
    {
        DB::table('audit_logs')->insert([
            'pesantren_id' => $restoreRequest->pesantren_id,
            'user_id' => $request->user()?->id,
            'action' => $action,
            'module' => 'Backup Restore Approval',
            'ip_address' => $request->ip(),
            'metadata' => json_encode($metadata + [
                'restore_request_id' => $restoreRequest->id,
                'status' => $restoreRequest->status,
                'approvals' => json_decode((string) $restoreRequest->approvals, true) ?: [],
                'correlation_id' => $request->attributes->get('correlation_id'),
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertWorkflowScope(Request $request, string $table, object $row): void
    {
        $user = $request->user()?->loadMissing('roles');
        if (! $user) {
            abort(401);
        }

        if ($user->hasRole(['santri', 'wali_murid'])) {
            $studentIds = $this->accessibleStudentIds($request);
            if (isset($row->student_id)) {
                abort_unless(in_array((int) $row->student_id, $studentIds, true), 403);
            }
        }

        if ($user->hasRole('musyrif') && isset($row->student_id)) {
            $studentIds = $this->dormStudentIds($request);
            abort_unless(in_array((int) $row->student_id, $studentIds, true), 403, 'Santri tidak berada dalam binaan musyrif.');
        }
    }

    private function accessibleStudentIds(Request $request): array
    {
        $user = $request->user()?->loadMissing('roles');
        $tenantId = $this->tenantContext->activePesantrenId($request);

        if ($user?->hasRole('santri')) {
            return DB::table('students')->where('pesantren_id', $tenantId)->where('user_id', $user->id)->pluck('id')->all();
        }

        if ($user?->hasRole('wali_murid')) {
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
                ->pluck('room_id')
                ->filter()
                ->values()
                ->all()
            : [];

        $roomNames = DB::table('rooms')
            ->where('pesantren_id', $tenantId)
            ->when($assignedRoomIds, fn ($query) => $query->whereIn('id', $assignedRoomIds), function ($query) {
                $query->where(function ($query) {
                    $query->where('block', 'like', '%Putri%')
                        ->orWhere('name', 'like', 'C-%');
                });
            })
            ->pluck('name')
            ->all();

        return DB::table('students')
            ->where('pesantren_id', $tenantId)
            ->whereIn('room', $roomNames ?: ['__none__'])
            ->pluck('id')
            ->all();
    }

    private function auditWorkflow(Request $request, string $action, string $module, object $before, object $after, array $metadata = []): void
    {
        DB::table('audit_logs')->insert([
            'pesantren_id' => $after->pesantren_id ?? $before->pesantren_id ?? null,
            'user_id' => $request->user()?->id,
            'action' => $action,
            'module' => $module,
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
