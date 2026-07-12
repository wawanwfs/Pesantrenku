<?php

namespace App\Console\Commands;

use App\Services\BackupCipher;
use App\Services\OperationStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DisasterRecoveryDrill extends Command
{
    protected $signature = 'pesantenku:dr-drill {--backup-id= : Backup record to verify} {--tenant= : Tenant ID for the latest backup}';

    protected $description = 'Verify backup integrity, object storage access, and point-in-time recovery readiness';

    public function handle(OperationStorage $storage, BackupCipher $cipher): int
    {
        $backup = DB::table('backups')
            ->when($this->option('backup-id'), fn ($query, $id) => $query->where('id', $id))
            ->when($this->option('tenant'), fn ($query, $tenant) => $query->where('pesantren_id', $tenant))
            ->latest('id')
            ->first();

        if (! $backup || ! $backup->file_path || ! $storage->exists($backup->file_path)) {
            $this->error('Backup yang dapat diverifikasi tidak ditemukan.');

            return self::FAILURE;
        }

        $contents = $storage->get($backup->file_path);
        $snapshot = json_decode($cipher->decrypt($contents), true);
        $ageHours = round(abs(now()->diffInHours($backup->completed_at ?: $backup->created_at)), 2);
        $maximumAge = (int) config('disaster-recovery.maximum_backup_age_hours', 24);
        $validSnapshot = is_array($snapshot) && isset($snapshot['tables']) && is_array($snapshot['tables']);
        $tenantMatches = ! $backup->pesantren_id || (int) ($snapshot['tenant_id'] ?? 0) === (int) $backup->pesantren_id;
        $pitr = $this->pitrStatus();
        $pitrRequired = app()->environment('production') && config('disaster-recovery.require_pitr_in_production', true);
        $passed = $validSnapshot && $tenantMatches && $ageHours <= $maximumAge && (! $pitrRequired || $pitr['enabled']);
        $rowCount = $validSnapshot
            ? collect($snapshot['tables'])->sum(fn ($rows) => is_countable($rows) ? count($rows) : 0)
            : 0;
        $report = [
            'status' => $passed ? 'passed' : 'failed',
            'checked_at' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'backup' => [
                'id' => $backup->id,
                'tenant_id' => $backup->pesantren_id,
                'path' => $backup->file_path,
                'disk' => $storage->diskName(),
                'age_hours' => $ageHours,
                'maximum_age_hours' => $maximumAge,
                'checksum_sha256' => hash('sha256', $contents),
                'snapshot_valid' => $validSnapshot,
                'tenant_matches' => $tenantMatches,
                'table_count' => $validSnapshot ? count($snapshot['tables']) : 0,
                'row_count' => $rowCount,
            ],
            'pitr' => $pitr,
            'notes' => 'This command validates restore inputs. Execute the documented isolated staging restore before a production release.',
        ];
        $reportPath = trim((string) config('disaster-recovery.report_prefix', 'dr-drills'), '/').'/'.now()->format('Ymd-His').'-backup-'.$backup->id.'.json';
        $storage->put($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->table(['Check', 'Result'], [
            ['Backup integrity', $validSnapshot ? 'OK' : 'FAILED'],
            ['Tenant scope', $tenantMatches ? 'OK' : 'FAILED'],
            ['Backup age', "{$ageHours}h / {$maximumAge}h"],
            ['Snapshot rows', (string) $rowCount],
            ['PITR/binlog', $pitr['enabled'] ? 'READY' : ($pitrRequired ? 'FAILED' : 'NOT REQUIRED')],
            ['Report', $reportPath],
        ]);

        return $passed ? self::SUCCESS : self::FAILURE;
    }

    private function pitrStatus(): array
    {
        if (DB::getDriverName() !== 'mysql') {
            return ['enabled' => false, 'driver' => DB::getDriverName(), 'log_bin' => null, 'format' => null];
        }

        $variables = collect(DB::select("SHOW VARIABLES WHERE Variable_name IN ('log_bin', 'binlog_format')"))
            ->mapWithKeys(fn ($row) => [$row->Variable_name => $row->Value]);

        return [
            'enabled' => strtoupper((string) $variables->get('log_bin')) === 'ON' && strtoupper((string) $variables->get('binlog_format')) === 'ROW',
            'driver' => 'mysql',
            'log_bin' => $variables->get('log_bin'),
            'format' => $variables->get('binlog_format'),
        ];
    }
}
