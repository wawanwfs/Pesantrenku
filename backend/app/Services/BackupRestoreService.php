<?php

namespace App\Services;

use App\Support\DomainRelations;
use App\Support\TenantBackupManifest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackupRestoreService
{
    public function __construct(
        private readonly OperationStorage $storage,
        private readonly BackupCipher $cipher,
        private readonly TenantContext $tenantContext,
    ) {}

    public function restore(object $backup, ?callable $afterMutation = null): int
    {
        if (! $backup->pesantren_id) {
            throw new \RuntimeException('Restore hanya mendukung backup tenant.');
        }
        if (! $backup->file_path || ! $this->storage->exists($backup->file_path)) {
            throw new \RuntimeException('File backup tidak ditemukan.');
        }

        $maxPlaintextBytes = max(1, (int) config('pesantrenku.backup.max_plaintext_bytes', 52428800));
        $maxEncryptedBytes = ($maxPlaintextBytes * 2) + 1048576;
        if ($this->storage->size($backup->file_path) > $maxEncryptedBytes) {
            throw new \RuntimeException('File backup melebihi batas restore aplikasi. Gunakan prosedur restore database infrastruktur.');
        }

        $contents = $this->storage->get($backup->file_path);
        if (! is_string($backup->checksum_sha256) || ! preg_match('/^[a-f0-9]{64}$/', $backup->checksum_sha256)) {
            throw new \RuntimeException('Metadata checksum backup tidak tersedia atau tidak valid.');
        }
        if (! in_array($backup->encryption_provider, ['local', 'kms'], true) || empty($backup->encryption_key_id)) {
            throw new \RuntimeException('Metadata enkripsi backup tidak lengkap.');
        }
        if (! hash_equals((string) $backup->checksum_sha256, hash('sha256', $contents))) {
            throw new \RuntimeException('Checksum file backup tidak cocok. File mungkin berubah atau rusak.');
        }

        $plaintext = $this->cipher->decrypt(
            $contents,
            $backup->encryption_provider ?: null,
            $backup->encryption_key_id ?: null,
        );
        if (strlen($plaintext) > $maxPlaintextBytes) {
            throw new \RuntimeException('Snapshot backup melebihi batas restore aplikasi.');
        }
        $snapshot = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
        $tenantId = (int) $backup->pesantren_id;
        $this->validateSnapshot($snapshot, $tenantId);
        $this->validateForeignReferences($snapshot, $tenantId);
        $this->validateIdCollisions($snapshot, $tenantId);

        $count = DB::transaction(function () use ($snapshot, $tenantId, $afterMutation) {
            $count = 0;

            foreach (array_reverse(TenantBackupManifest::TABLES) as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'pesantren_id')) {
                    $delete = DB::table($table)->where('pesantren_id', $tenantId);
                    if ($table === 'system_settings') {
                        $delete->where(function ($query) {
                            foreach (TenantBackupManifest::PROTECTED_SETTING_PREFIXES as $prefix) {
                                $query->where('key', 'not like', $prefix.'%');
                            }
                        });
                    }
                    $delete->delete();
                }
            }

            $tenant = collect((array) ($snapshot['tenant'] ?? []))
                ->only(['name', 'city', 'address', 'phone', 'email', 'logo', 'metadata', 'updated_at'])
                ->all();
            if ($tenant !== []) {
                DB::table('pesantrens')->where('id', $tenantId)->update($tenant);
            }

            foreach (TenantBackupManifest::TABLES as $table) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'pesantren_id')) {
                    continue;
                }

                $columns = Schema::getColumnListing($table);
                foreach ($snapshot['tables'][$table] ?? [] as $row) {
                    $data = collect((array) $row)->only($columns)->all();
                    if ((int) ($data['pesantren_id'] ?? 0) !== $tenantId || empty($data['id'])) {
                        throw new \RuntimeException("Data {$table} tidak konsisten dengan tenant backup.");
                    }
                    DB::table($table)->insert($data);
                    $count++;
                }
            }

            if ($afterMutation !== null) {
                $afterMutation($count);
            }

            return $count;
        }, 3);

        $this->tenantContext->invalidatePesantren($tenantId);

        return $count;
    }

    private function validateSnapshot(array $snapshot, int $tenantId): void
    {
        if (($snapshot['version'] ?? null) !== TenantBackupManifest::VERSION) {
            throw new \RuntimeException('Versi snapshot backup tidak didukung.');
        }
        if ((int) ($snapshot['tenant_id'] ?? 0) !== $tenantId) {
            throw new \RuntimeException('Snapshot backup tidak cocok dengan tenant aktif.');
        }
        if (! is_array($snapshot['tenant'] ?? null) || (int) ($snapshot['tenant']['id'] ?? 0) !== $tenantId) {
            throw new \RuntimeException('Metadata tenant pada snapshot tidak valid.');
        }
        if (! is_array($snapshot['manifest'] ?? null) || ! is_array($snapshot['tables'] ?? null)) {
            throw new \RuntimeException('Manifest snapshot backup tidak lengkap.');
        }

        $unknownTables = array_diff(array_keys($snapshot['tables']), TenantBackupManifest::TABLES);
        if ($unknownTables !== []) {
            throw new \RuntimeException('Snapshot berisi tabel yang tidak diizinkan: '.implode(', ', $unknownTables));
        }
        $unknownManifestTables = array_diff(array_keys($snapshot['manifest']), TenantBackupManifest::TABLES);
        if ($unknownManifestTables !== []) {
            throw new \RuntimeException('Manifest snapshot berisi tabel yang tidak diizinkan: '.implode(', ', $unknownManifestTables));
        }

        $maxRows = max(1, (int) config('pesantrenku.backup.max_rows', 25000));
        $totalRows = 0;
        foreach (TenantBackupManifest::TABLES as $table) {
            $rows = $snapshot['tables'][$table] ?? null;
            $entry = $snapshot['manifest'][$table] ?? null;
            if (! is_array($rows) || ! is_array($entry)) {
                throw new \RuntimeException("Manifest tabel {$table} tidak valid.");
            }
            if ((int) ($entry['rows'] ?? -1) !== count($rows)) {
                throw new \RuntimeException("Jumlah baris tabel {$table} tidak cocok dengan manifest.");
            }
            $totalRows += count($rows);
            if ($totalRows > $maxRows) {
                throw new \RuntimeException('Jumlah baris snapshot melebihi batas restore aplikasi.');
            }
            $checksum = (string) ($entry['checksum_sha256'] ?? '');
            if ($checksum === '' || ! hash_equals($checksum, TenantBackupManifest::checksum($rows))) {
                throw new \RuntimeException("Checksum tabel {$table} tidak cocok.");
            }
            foreach ($rows as $row) {
                if ($table === 'system_settings' && TenantBackupManifest::isProtectedSetting((string) ($row['key'] ?? ''))) {
                    throw new \RuntimeException('Snapshot data tidak boleh memuat setting keamanan atau permission.');
                }
                if (! is_array($row) || (int) ($row['pesantren_id'] ?? 0) !== $tenantId || empty($row['id'])) {
                    throw new \RuntimeException("Tabel {$table} mengandung data yang bukan milik tenant backup.");
                }
                if (Schema::hasTable($table)) {
                    $unknownColumns = array_diff(array_keys($row), Schema::getColumnListing($table));
                    if ($unknownColumns !== []) {
                        throw new \RuntimeException("Tabel {$table} memiliki kolom snapshot yang tidak dikenal: ".implode(', ', $unknownColumns));
                    }
                }
            }
        }
    }

    private function validateIdCollisions(array $snapshot, int $tenantId): void
    {
        foreach (TenantBackupManifest::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'pesantren_id')) {
                continue;
            }

            $ids = collect($snapshot['tables'][$table] ?? [])->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
            if ($ids === []) {
                continue;
            }

            $collision = DB::table($table)
                ->whereIn('id', $ids)
                ->where(function ($query) use ($tenantId) {
                    $query->whereNull('pesantren_id')->orWhere('pesantren_id', '!=', $tenantId);
                })
                ->value('id');

            if ($collision) {
                throw new \RuntimeException("ID {$collision} pada {$table} sudah digunakan tenant lain.");
            }
        }
    }

    private function validateForeignReferences(array $snapshot, int $tenantId): void
    {
        $snapshotIds = collect(TenantBackupManifest::TABLES)->mapWithKeys(function (string $table) use ($snapshot) {
            return [$table => collect($snapshot['tables'][$table] ?? [])->pluck('id')->filter()->map(fn ($id) => (int) $id)->flip()];
        });

        foreach (DomainRelations::all() as [$table, $column, $parent]) {
            if (! in_array($table, TenantBackupManifest::TABLES, true)) {
                continue;
            }

            foreach ($snapshot['tables'][$table] ?? [] as $row) {
                $parentId = $row[$column] ?? null;
                if ($parentId === null || $parentId === '') {
                    continue;
                }
                $parentId = (int) $parentId;

                if (in_array($parent, TenantBackupManifest::TABLES, true)) {
                    if (! $snapshotIds->get($parent, collect())->has($parentId)) {
                        throw new \RuntimeException("Relasi {$table}.{$column} mengarah ke {$parent} #{$parentId} yang tidak ada di snapshot.");
                    }

                    continue;
                }

                if ($parent === 'pesantrens') {
                    if ($parentId !== $tenantId) {
                        throw new \RuntimeException("Relasi {$table}.{$column} keluar dari tenant snapshot.");
                    }

                    continue;
                }

                if (! Schema::hasTable($parent) || ! DB::table($parent)->where('id', $parentId)->exists()) {
                    throw new \RuntimeException("Relasi eksternal {$table}.{$column} mengarah ke {$parent} #{$parentId} yang tidak tersedia.");
                }
            }
        }
    }
}
