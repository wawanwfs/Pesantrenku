<?php

namespace App\Http\Controllers\Concerns;

use App\Events\NotificationChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ActionNotificationJobConcern
{
    public function readNotifications(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);

        $tenantId = $this->tenantContext->activePesantrenId($request);
        $ids = collect($request->input('ids', []))->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT))->map(fn ($id) => (int) $id)->unique()->values();

        $query = DB::table('notification_logs')
            ->where(function ($query) use ($user) {
                $query->whereNull('user_id')->orWhere('user_id', $user->id);
            });

        $tenantId
            ? $query->where('pesantren_id', $tenantId)
            : $query->whereNull('pesantren_id');

        if ($ids->isNotEmpty()) {
            $query->whereIn('id', $ids->all());
        }

        $directUpdated = (clone $query)
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);
        $broadcastIds = (clone $query)
            ->whereNull('user_id')
            ->whereNotExists(function ($receipt) use ($user) {
                $receipt->selectRaw('1')
                    ->from('notification_receipts')
                    ->whereColumn('notification_receipts.notification_id', 'notification_logs.id')
                    ->where('notification_receipts.user_id', $user->id);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
        if ($broadcastIds->isNotEmpty()) {
            $timestamp = now();
            DB::table('notification_receipts')->upsert(
                $broadcastIds->map(fn (int $notificationId) => [
                    'notification_id' => $notificationId,
                    'user_id' => (int) $user->id,
                    'read_at' => $timestamp,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ])->all(),
                ['notification_id', 'user_id'],
                ['read_at', 'updated_at'],
            );
        }

        $updated = $directUpdated + $broadcastIds->count();
        $remaining = $this->notificationQueryForUser($request)
            ->whereRaw('COALESCE(notification_logs.read_at, notification_receipts.read_at) IS NULL')
            ->count();
        NotificationChanged::dispatch($tenantId, $user->id, ['reason' => 'read', 'unread' => $remaining]);

        return response()->json(['message' => 'Notifikasi ditandai terbaca.', 'updated' => $updated, 'unread' => $remaining]);
    }

    public function generateQr(Request $request)
    {
        $validated = $request->validate([
            'purpose' => ['nullable', Rule::in(['identity', 'attendance', 'dorm-attendance'])],
        ]);
        $user = $request->user();
        abort_unless($user, 401);
        abort_if($this->tenantContext->isSuperAdmin($request), 422, 'QR operasional harus dibuat dalam membership tenant.');

        $activeRole = $this->tenantContext->activeRole($request);
        $purpose = $validated['purpose'] ?? 'identity';
        if (in_array($purpose, ['attendance', 'dorm-attendance'], true)) {
            abort_unless($activeRole === 'santri', 403, 'QR presensi hanya dapat dibuat oleh akun santri.');
        }

        $tenantId = $this->tenantContext->activePesantrenId($request);
        $code = Str::random(64);
        $codeHash = hash('sha256', $code);
        $expiresAt = now()->addMinutes(5);

        DB::transaction(function () use ($tenantId, $user, $purpose, $codeHash, $expiresAt, $request) {
            DB::table('qr_tokens')
                ->where('pesantren_id', $tenantId)
                ->where('user_id', $user->id)
                ->where('purpose', $purpose)
                ->where('status', 'active')
                ->update(['status' => 'revoked', 'updated_at' => now()]);

            DB::table('qr_tokens')->insert([
                'pesantren_id' => $tenantId,
                'user_id' => $user->id,
                'code' => $codeHash,
                'purpose' => $purpose,
                'status' => 'active',
                'expires_at' => $expiresAt,
                'metadata' => json_encode(['code_hash' => $codeHash, 'agent' => Str::limit((string) $request->userAgent(), 255)]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('audit_logs')->insert([
                'pesantren_id' => $tenantId,
                'user_id' => $user->id,
                'action' => 'QR generated',
                'module' => 'QR',
                'ip_address' => $request->ip(),
                'metadata' => json_encode(['code_hash' => $codeHash, 'purpose' => $purpose, 'expires_at' => $expiresAt->toIso8601String()]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return response()->json([
            'code' => $code,
            'purpose' => $purpose,
            'generated_at' => now()->toDateTimeString(),
            'expires_at' => $expiresAt->toDateTimeString(),
        ], 201);
    }

    public function registerQrScanner(Request $request)
    {
        return response()->json($this->qrScanner->registerDevice($request), 201);
    }

    public function qrScanChallenge(Request $request)
    {
        return response()->json($this->qrScanner->challenge($request), 201);
    }

    public function scanQr(Request $request)
    {
        $result = $this->qrScanner->scan($request);

        return response()->json(['message' => 'QR berhasil diproses.', 'status' => 'success'] + $result);
    }

    public function notifications(Request $request)
    {
        return response()->json($this->notificationPayload($request));
    }

    public function notificationStream(Request $request)
    {
        abort_unless((bool) config('pesantrenku.notifications.sse_enabled', true), 404);

        return response()->stream(function () use ($request) {
            ignore_user_abort(true);
            set_time_limit(30);
            $startedAt = microtime(true);
            $lastHash = null;
            $heartbeatAt = 0.0;
            echo "retry: 5000\n\n";

            $expectedAuthzVersion = (int) ($request->user()?->authz_version ?? 0);
            while (! connection_aborted() && microtime(true) - $startedAt < 25) {
                $currentAuthzVersion = (int) DB::table('users')->where('id', $request->user()?->id)->value('authz_version');
                if ($expectedAuthzVersion <= 0 || $currentAuthzVersion !== $expectedAuthzVersion) {
                    echo "event: authorization-revoked\n";
                    echo 'data: {"reason":"authorization_changed"}'."\n\n";
                    flush();
                    break;
                }
                $payload = $this->notificationPayload($request);
                $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $hash = hash('sha256', (string) $encoded);
                if ($hash !== $lastHash) {
                    echo "event: notifications\n";
                    echo 'id: '.now()->getTimestampMs()."\n";
                    echo 'data: '.$encoded."\n\n";
                    $lastHash = $hash;
                } elseif (microtime(true) - $heartbeatAt >= 10) {
                    echo ': heartbeat '.now()->toIso8601String()."\n\n";
                    $heartbeatAt = microtime(true);
                }

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                usleep(2_000_000);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
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
            ->map(fn ($job) => $this->formatJob($request, $job));

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

        return response()->json($this->formatJob($request, $job));
    }

    public function downloadJob(Request $request, string $uuid)
    {
        $job = $this->jobQueryForUser($request)->where('uuid', $uuid)->first();
        abort_unless($job, 404);
        $this->authorizeJobResource($request, $job);
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
        $this->authorizeJobResource($request, $job);
        abort_unless(
            in_array($job->status, ['completed', 'failed', 'cancelled', 'expired'], true),
            409,
            'Job yang masih aktif tidak dapat dihapus.'
        );

        $paths = array_unique(array_filter([$job->source_path, $job->output_path, $job->file_path]));
        foreach ($paths as $path) {
            if ($this->operationStorage->exists($path)) {
                abort_unless(
                    $this->operationStorage->delete($path) && ! $this->operationStorage->exists($path),
                    503,
                    'Object job gagal dihapus secara terverifikasi; riwayat dipertahankan untuk retry.'
                );
            }
        }

        DB::transaction(function () use ($request, $job, $paths) {
            $locked = DB::table('operation_jobs')->where('id', $job->id)->lockForUpdate()->first();
            abort_unless($locked, 404);
            DB::table('audit_logs')->insert([
                'pesantren_id' => $locked->pesantren_id,
                'user_id' => $request->user()?->id,
                'action' => 'delete operation job',
                'module' => 'operation_jobs',
                'row_id' => $locked->id,
                'ip_address' => $request->ip(),
                'metadata' => json_encode([
                    'row_id' => $locked->id,
                    'uuid' => $locked->uuid,
                    'resource' => $locked->resource,
                    'type' => $locked->type,
                    'paths' => array_values($paths),
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('operation_jobs')->where('id', $locked->id)->delete();
        }, 3);

        return response()->json(['message' => 'File dan riwayat job berhasil dihapus.']);
    }

    public function queueMonitor(Request $request)
    {
        $isSuperAdmin = $this->assertOwnerOrSuper($request);
        $operations = $this->jobQueryForUser($request)->get();
        $failed = ($isSuperAdmin ? DB::table('failed_jobs')->latest('id')->limit(25)->get() : collect())->map(function ($row) {
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

        $runtime = $this->queueMonitor->snapshot();

        return response()->json([
            'workers' => [
                ...$runtime['totals'],
                'failed' => $isSuperAdmin ? DB::table('failed_jobs')->count() : $operations->where('status', 'failed')->count(),
                'queues' => $runtime['queues'],
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
        $query = $this->notificationQueryForUser($request);
        $unread = (clone $query)
            ->whereRaw('COALESCE(notification_logs.read_at, notification_receipts.read_at) IS NULL')
            ->count();
        $items = $query
            ->select('notification_logs.*', DB::raw('COALESCE(notification_logs.read_at, notification_receipts.read_at) as read_at'))
            ->latest('notification_logs.id')
            ->limit(20)
            ->get();

        return [
            'data' => $items,
            'unread' => $unread,
            'polled_at' => now()->toDateTimeString(),
        ];
    }

    private function notificationQueryForUser(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);
        $tenantId = $this->tenantContext->activePesantrenId($request);

        return DB::table('notification_logs')
            ->leftJoin('notification_receipts', function ($join) use ($user) {
                $join->on('notification_receipts.notification_id', '=', 'notification_logs.id')
                    ->where('notification_receipts.user_id', '=', $user->id);
            })
            ->where(function ($query) use ($user) {
                $query->whereNull('notification_logs.user_id')->orWhere('notification_logs.user_id', $user->id);
            })
            ->when(
                $tenantId,
                fn ($query) => $query->where('notification_logs.pesantren_id', $tenantId),
                fn ($query) => $query->whereNull('notification_logs.pesantren_id'),
            );
    }

    private function formatJob(Request $request, object $job): array
    {
        $path = $job->output_path ?: $job->file_path;
        $downloadPrefix = $request->is('api/*') ? '/api/v1' : '/web-actions';
        $ability = $job->type === 'import' ? 'write' : 'read';
        $canDownload = $job->status === 'completed'
            && $path
            && is_string($job->resource)
            && $this->resourceAuthorization->allows($request, $job->resource, $ability);

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
            'download_url' => $canDownload ? "{$downloadPrefix}/jobs/{$job->uuid}/download" : null,
            'result' => json_decode((string) $job->result, true) ?: null,
            'error' => $job->error,
            'created_at' => $job->created_at,
            'started_at' => $job->started_at,
            'finished_at' => $job->finished_at,
            'expires_at' => $job->expires_at ?? null,
        ];
    }

    private function authorizeJobResource(Request $request, object $job): void
    {
        abort_unless(is_string($job->resource) && $job->resource !== '', 403, 'Job tidak memiliki binding resource yang dapat diotorisasi ulang.');
        $this->resourceAuthorization->authorize(
            $request,
            $job->resource,
            $job->type === 'import' ? 'write' : 'read',
        );
    }

    private function assertOwnerOrSuper(Request $request): bool
    {
        abort_unless($request->user(), 401);
        if ($this->tenantContext->isSuperAdmin($request)) {
            return true;
        }

        $this->tenantContext->authorizeActiveRole(
            $request,
            'owner_pesantren',
            false,
            'Hanya super admin dan owner membership tenant aktif yang memiliki akses.'
        );

        return false;
    }

    private function jobQueryForUser(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);
        $isSuperAdmin = $this->tenantContext->isSuperAdmin($request);
        $tenantId = $this->tenantContext->requireActivePesantrenId($request);
        $isOwner = ! $isSuperAdmin && $this->tenantContext->hasActiveRole($request, 'owner_pesantren');

        return DB::table('operation_jobs')
            ->where('pesantren_id', $tenantId)
            ->when(! $isSuperAdmin && ! $isOwner, fn ($query) => $query->where('user_id', $user->id));
    }

    private function assertQueueAdmin(Request $request, bool $platformOnly = false): void
    {
        $isSuperAdmin = $this->assertOwnerOrSuper($request);
        abort_if($platformOnly && ! $isSuperAdmin, 403, 'Failed queue global hanya dapat dikelola super admin.');
    }
}
