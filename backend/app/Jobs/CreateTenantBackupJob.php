<?php

namespace App\Jobs;

use App\Services\TenantBackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTenantBackupJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 900;

    public bool $failOnTimeout = true;

    public function __construct(
        private readonly int $backupId,
        private readonly int $actorUserId,
    ) {}

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(TenantBackupService $service): void
    {
        $service->process($this->backupId, $this->actorUserId);
    }

    public function failed(?\Throwable $exception): void
    {
        DB::table('backups')
            ->where('id', $this->backupId)
            ->whereIn('status', ['queued', 'processing'])
            ->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error' => $exception ? Str::limit($exception->getMessage(), 2000) : 'Worker backup berhenti tanpa menyelesaikan proses.',
                'updated_at' => now(),
            ]);
    }
}
