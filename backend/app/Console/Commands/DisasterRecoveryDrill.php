<?php

namespace App\Console\Commands;

use App\Services\BackupCipher;
use App\Services\OperationStorage;
use App\Support\TenantBackupManifest;
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
        $hasValidMetadata = is_string($backup->checksum_sha256)
            && preg_match('/^[a-f0-9]{64}$/', $backup->checksum_sha256)
            && in_array($backup->encryption_provider, ['local', 'kms'], true)
            && ! empty($backup->encryption_key_id);
        $fileChecksumMatches = $hasValidMetadata
            && hash_equals((string) $backup->checksum_sha256, hash('sha256', $contents));
        $snapshot = null;
        $integrityErrors = [];
        try {
            if (! $hasValidMetadata) {
                throw new \RuntimeException('Metadata checksum/enkripsi backup tidak lengkap.');
            }
            $snapshot = json_decode($cipher->decrypt(
                $contents,
                (string) $backup->encryption_provider,
                (string) $backup->encryption_key_id,
            ), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($snapshot)) {
                throw new \RuntimeException('Snapshot bukan objek JSON.');
            }
        } catch (\Throwable $exception) {
            $integrityErrors[] = 'Dekripsi/JSON gagal: '.$exception->getMessage();
        }

        $ageHours = round(abs(now()->diffInHours($backup->completed_at ?: $backup->created_at)), 2);
        $maximumAge = (int) config('disaster-recovery.maximum_backup_age_hours', 24);
        $tenantMatches = is_array($snapshot) && (int) ($snapshot['tenant_id'] ?? 0) === (int) $backup->pesantren_id;
        $manifestValid = $this->validateManifest($snapshot, (int) $backup->pesantren_id, $integrityErrors);
        $validSnapshot = $fileChecksumMatches && $tenantMatches && $manifestValid;
        if (! $fileChecksumMatches) {
            $integrityErrors[] = 'Checksum file terenkripsi tidak cocok dengan record backup.';
        }
        if (! $tenantMatches) {
            $integrityErrors[] = 'Tenant snapshot tidak cocok dengan record backup.';
        }

        $pitr = $this->pitrStatus();
        $pitrRequired = app()->environment('production') && config('disaster-recovery.require_pitr_in_production', true);
        $passed = $validSnapshot && $ageHours <= $maximumAge && (! $pitrRequired || $pitr['enabled']);
        $rowCount = $manifestValid
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
                'file_checksum_matches' => $fileChecksumMatches,
                'tenant_matches' => $tenantMatches,
                'manifest_valid' => $manifestValid,
                'integrity_errors' => $integrityErrors,
                'table_count' => $manifestValid ? count($snapshot['tables']) : 0,
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
            ['Manifest checksums', $manifestValid ? 'OK' : 'FAILED'],
            ['Backup age', "{$ageHours}h / {$maximumAge}h"],
            ['Snapshot rows', (string) $rowCount],
            ['PITR/binlog', $pitr['enabled'] ? 'READY' : ($pitrRequired ? 'FAILED' : 'NOT REQUIRED')],
            ['Report', $reportPath],
        ]);

        return $passed ? self::SUCCESS : self::FAILURE;
    }

    private function validateManifest(?array $snapshot, int $tenantId, array &$errors): bool
    {
        if (! is_array($snapshot)) {
            return false;
        }
        if (($snapshot['version'] ?? null) !== TenantBackupManifest::VERSION) {
            $errors[] = 'Versi snapshot tidak didukung.';
        }
        if (! is_array($snapshot['tables'] ?? null) || ! is_array($snapshot['manifest'] ?? null)) {
            $errors[] = 'Manifest atau tabel snapshot tidak lengkap.';

            return false;
        }

        $unknown = array_diff(array_keys($snapshot['tables']), TenantBackupManifest::TABLES);
        if ($unknown !== []) {
            $errors[] = 'Tabel asing pada snapshot: '.implode(', ', $unknown);
        }
        $unknownManifest = array_diff(array_keys($snapshot['manifest']), TenantBackupManifest::TABLES);
        if ($unknownManifest !== []) {
            $errors[] = 'Tabel asing pada manifest: '.implode(', ', $unknownManifest);
        }

        foreach (TenantBackupManifest::TABLES as $table) {
            $rows = $snapshot['tables'][$table] ?? null;
            $entry = $snapshot['manifest'][$table] ?? null;
            if (! is_array($rows) || ! is_array($entry)) {
                $errors[] = "Manifest {$table} hilang atau tidak valid.";

                continue;
            }
            if ((int) ($entry['rows'] ?? -1) !== count($rows)) {
                $errors[] = "Jumlah baris {$table} tidak cocok.";
            }
            $checksum = (string) ($entry['checksum_sha256'] ?? '');
            if ($checksum === '' || ! hash_equals($checksum, TenantBackupManifest::checksum($rows))) {
                $errors[] = "Checksum tabel {$table} tidak cocok.";
            }
            foreach ($rows as $row) {
                if ((int) ($row['pesantren_id'] ?? 0) !== $tenantId) {
                    $errors[] = "Tabel {$table} mengandung data tenant lain.";
                    break;
                }
            }
        }

        return $errors === [];
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
