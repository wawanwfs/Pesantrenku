<?php

namespace App\Services;

use App\Support\TenantBackupManifest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TenantBackupService
{
    public function __construct(
        private readonly OperationStorage $storage,
        private readonly BackupCipher $cipher,
    ) {}

    public function process(int $backupId, int $actorUserId): int
    {
        $backup = DB::transaction(function () use ($backupId) {
            $row = DB::table('backups')->where('id', $backupId)->lockForUpdate()->first();
            if (! $row) {
                throw new \RuntimeException('Backup tidak ditemukan.');
            }
            if (! in_array($row->status, ['queued', 'failed'], true)) {
                throw new \RuntimeException('Backup tidak dapat diproses pada status saat ini.');
            }
            if (! $row->pesantren_id || ! $row->file_path) {
                throw new \RuntimeException('Metadata backup tenant tidak lengkap.');
            }

            DB::table('backups')->where('id', $row->id)->update([
                'status' => 'processing',
                'started_at' => now(),
                'completed_at' => null,
                'error' => null,
                'updated_at' => now(),
            ]);

            return DB::table('backups')->find($row->id);
        }, 3);

        try {
            $snapshot = $this->snapshot((int) $backup->pesantren_id, $actorUserId);
            $plaintext = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $maxPlaintextBytes = max(1, (int) config('pesantrenku.backup.max_plaintext_bytes', 52428800));
            if (strlen($plaintext) > $maxPlaintextBytes) {
                throw new \RuntimeException('Snapshot tenant melebihi batas backup aplikasi. Gunakan backup database infrastruktur.');
            }

            $encrypted = $this->cipher->encrypt($plaintext);
            if (! $this->storage->put($backup->file_path, $encrypted['contents'])) {
                throw new \RuntimeException('Backup tidak dapat disimpan ke object storage.');
            }

            try {
                DB::transaction(function () use ($backup, $actorUserId, $encrypted) {
                    $updated = DB::table('backups')
                        ->where('id', $backup->id)
                        ->where('status', 'processing')
                        ->update([
                            'status' => 'success',
                            'encryption_provider' => $encrypted['provider'],
                            'encryption_key_id' => $encrypted['key_id'],
                            'checksum_sha256' => $encrypted['checksum'],
                            'completed_at' => now(),
                            'error' => null,
                            'updated_at' => now(),
                        ]);
                    if ($updated !== 1) {
                        throw new \RuntimeException('Status backup berubah sebelum hasil dapat dicatat.');
                    }

                    $this->audit((int) $backup->pesantren_id, $actorUserId, 'Backup tenant selesai', [
                        'backup_id' => (int) $backup->id,
                        'name' => $backup->name,
                        'checksum_sha256' => $encrypted['checksum'],
                        'disk' => $this->storage->diskName(),
                        'encryption_provider' => $encrypted['provider'],
                        'key_id' => $encrypted['key_id'],
                    ]);
                }, 3);
            } catch (\Throwable $exception) {
                $this->storage->delete($backup->file_path);
                throw $exception;
            }

            return (int) $backup->id;
        } catch (\Throwable $exception) {
            if ($backup->file_path && $this->storage->exists($backup->file_path)) {
                $this->storage->delete($backup->file_path);
            }
            DB::table('backups')
                ->where('id', $backup->id)
                ->where('status', 'processing')
                ->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'error' => Str::limit($exception->getMessage(), 2000),
                    'updated_at' => now(),
                ]);
            $this->audit((int) $backup->pesantren_id, $actorUserId, 'Backup tenant gagal', [
                'backup_id' => (int) $backup->id,
                'error' => class_basename($exception),
            ]);

            throw $exception;
        }
    }

    public function assertWithinLimits(int $tenantId): void
    {
        if (! Schema::hasTable('pesantrens') || ! DB::table('pesantrens')->where('id', $tenantId)->whereNull('deleted_at')->exists()) {
            throw new \RuntimeException('Tenant backup tidak ditemukan.');
        }

        $maxRows = max(1, (int) config('pesantrenku.backup.max_rows', 25000));
        $totalRows = 0;
        foreach (TenantBackupManifest::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                throw new \RuntimeException("Tabel manifest backup {$table} tidak tersedia.");
            }
            if (! Schema::hasColumn($table, 'pesantren_id')) {
                throw new \RuntimeException("Tabel backup {$table} tidak memiliki boundary tenant.");
            }
            $totalRows += DB::table($table)->where('pesantren_id', $tenantId)->count();
            if ($totalRows > $maxRows) {
                throw new \RuntimeException('Jumlah data tenant melebihi batas backup aplikasi. Gunakan backup database infrastruktur.');
            }
        }
    }

    private function snapshot(int $tenantId, int $actorUserId): array
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }

        return DB::transaction(function () use ($tenantId, $actorUserId) {
            $tenant = DB::table('pesantrens')->where('id', $tenantId)->whereNull('deleted_at')->first();
            if (! $tenant) {
                throw new \RuntimeException('Tenant backup tidak ditemukan.');
            }

            $this->assertWithinLimits($tenantId);

            $data = [];
            $manifest = [];
            foreach (TenantBackupManifest::TABLES as $table) {
                $query = DB::table($table)->where('pesantren_id', $tenantId);
                if ($table === 'system_settings') {
                    foreach (TenantBackupManifest::PROTECTED_SETTING_PREFIXES as $prefix) {
                        $query->where('key', 'not like', $prefix.'%');
                    }
                }
                $rows = $query
                    ->orderBy('id')
                    ->get()
                    ->map(fn (object $row) => (array) $row)
                    ->all();
                $data[$table] = $rows;
                $manifest[$table] = [
                    'rows' => count($rows),
                    'checksum_sha256' => TenantBackupManifest::checksum($rows),
                ];
            }

            return [
                'version' => TenantBackupManifest::VERSION,
                'generated_at' => now()->toIso8601String(),
                'generated_by' => $actorUserId,
                'tenant_id' => $tenantId,
                'tenant' => collect((array) $tenant)->only(['id', 'name', 'city', 'address', 'phone', 'email', 'logo', 'status', 'metadata', 'created_at', 'updated_at'])->all(),
                'manifest' => $manifest,
                'tables' => $data,
            ];
        }, 3);
    }

    private function audit(int $tenantId, int $actorUserId, string $action, array $metadata): void
    {
        DB::table('audit_logs')->insert([
            'pesantren_id' => $tenantId,
            'user_id' => $actorUserId,
            'action' => $action,
            'module' => 'Backup',
            'ip_address' => null,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
