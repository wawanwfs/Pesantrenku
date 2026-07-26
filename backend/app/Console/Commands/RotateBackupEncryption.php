<?php

namespace App\Console\Commands;

use App\Services\BackupCipher;
use App\Services\OperationStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RotateBackupEncryption extends Command
{
    protected $signature = 'pesantenku:rotate-backup-keys {--tenant= : Limit rotation to one tenant}';

    protected $description = 'Re-encrypt backup envelopes using versioned objects and an atomic database pointer swap';

    public function handle(OperationStorage $storage, BackupCipher $cipher): int
    {
        $rotationId = DB::table('secret_rotations')->insertGetId([
            'secret_name' => 'backup-envelope-key',
            'new_key_id' => (string) config('backup-security.kms_key_id', 'app-key'),
            'status' => 'running',
            'metadata' => json_encode(['tenant_id' => $this->option('tenant')], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $backups = DB::table('backups')
                ->when($this->option('tenant'), fn ($query, $tenant) => $query->where('pesantren_id', $tenant))
                ->whereIn('status', ['success', 'restored'])
                ->whereNotNull('file_path')
                ->orderBy('id')
                ->get();
        } catch (\Throwable $exception) {
            DB::table('secret_rotations')->where('id', $rotationId)->update([
                'status' => 'failed',
                'metadata' => json_encode([
                    'tenant_id' => $this->option('tenant'),
                    'error' => class_basename($exception),
                    'message' => Str::limit($exception->getMessage(), 300),
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
            $this->error('Daftar backup gagal dibaca: '.$exception->getMessage());

            return self::FAILURE;
        }

        $rotated = 0;
        $failed = [];
        $orphanedOldObjects = [];
        $orphanedNewObjects = [];

        foreach ($backups as $candidate) {
            $lock = Cache::lock(
                "pesantenku:backup-key-rotation:{$candidate->id}",
                max(300, (int) config('pesantrenku.backup.rotation_lock_seconds', 1800)),
            );
            if (! $lock->get()) {
                $failed[(string) $candidate->id] = 'locked';

                continue;
            }

            try {
                $backup = DB::table('backups')->where('id', $candidate->id)->first();
                if (! $backup || ! in_array($backup->status, ['success', 'restored'], true) || ! $backup->file_path) {
                    throw new \RuntimeException('Status atau metadata backup berubah sebelum rotasi.');
                }
                if (! $storage->exists($backup->file_path)) {
                    throw new \RuntimeException('Object backup lama tidak ditemukan.');
                }

                $oldContents = $storage->get($backup->file_path);
                if (! hash_equals((string) $backup->checksum_sha256, hash('sha256', $oldContents))) {
                    throw new \RuntimeException('Checksum object lama tidak cocok dengan metadata database.');
                }
                $plaintext = $cipher->decrypt($oldContents, $backup->encryption_provider, $backup->encryption_key_id);
                $encrypted = $cipher->encrypt($plaintext);
                $newPath = $backup->file_path.'.rotation-'.Str::lower(Str::uuid()->toString());

                if (! $storage->put($newPath, $encrypted['contents'])) {
                    throw new \RuntimeException('Object versi baru gagal ditulis.');
                }

                try {
                    $stored = $storage->get($newPath);
                    if (! hash_equals($encrypted['checksum'], hash('sha256', $stored))) {
                        throw new \RuntimeException('Verifikasi checksum object versi baru gagal.');
                    }
                    $verifiedPlaintext = $cipher->decrypt($stored, $encrypted['provider'], $encrypted['key_id']);
                    if (! hash_equals(hash('sha256', $plaintext), hash('sha256', $verifiedPlaintext))) {
                        throw new \RuntimeException('Verifikasi decrypt object versi baru gagal.');
                    }

                    DB::transaction(function () use ($backup, $newPath, $encrypted) {
                        $current = DB::table('backups')->where('id', $backup->id)->lockForUpdate()->first();
                        if (! $current
                            || ! in_array($current->status, ['success', 'restored'], true)
                            || $current->file_path !== $backup->file_path
                            || ! hash_equals((string) $current->checksum_sha256, (string) $backup->checksum_sha256)) {
                            throw new \RuntimeException('Pointer backup berubah selama rotasi.');
                        }

                        DB::table('backups')->where('id', $backup->id)->update([
                            'file_path' => $newPath,
                            'encryption_provider' => $encrypted['provider'],
                            'encryption_key_id' => $encrypted['key_id'],
                            'checksum_sha256' => $encrypted['checksum'],
                            'updated_at' => now(),
                        ]);
                    }, 3);
                } catch (\Throwable $exception) {
                    try {
                        if (! $storage->delete($newPath) || $storage->exists($newPath)) {
                            throw new \RuntimeException('Object versi baru gagal dibersihkan setelah pointer swap dibatalkan.');
                        }
                    } catch (\Throwable $cleanupException) {
                        $orphanedNewObjects[(string) $backup->id] = [
                            'path' => $newPath,
                            'error' => Str::limit($cleanupException->getMessage(), 300),
                        ];
                    }
                    throw $exception;
                }

                // The old object remains recoverable until after the database commit.
                $rotated++;
                try {
                    if (! $storage->delete($backup->file_path) || $storage->exists($backup->file_path)) {
                        throw new \RuntimeException('Penghapusan object lama tidak dapat diverifikasi.');
                    }
                } catch (\Throwable $cleanupException) {
                    $orphanedOldObjects[(string) $backup->id] = [
                        'path' => $backup->file_path,
                        'error' => Str::limit($cleanupException->getMessage(), 300),
                    ];
                    $this->warn("Backup {$backup->id} berhasil dirotasi, tetapi object lama perlu cleanup: {$cleanupException->getMessage()}");
                }
            } catch (\Throwable $exception) {
                $failed[(string) $candidate->id] = Str::limit($exception->getMessage(), 300);
                $this->error("Backup {$candidate->id} gagal dirotasi: {$exception->getMessage()}");
            } finally {
                $lock->release();
            }
        }

        $hasPartialFailure = $failed !== [] || $orphanedOldObjects !== [] || $orphanedNewObjects !== [];
        $status = ! $hasPartialFailure ? 'completed' : ($rotated > 0 ? 'partial' : 'failed');
        DB::table('secret_rotations')->where('id', $rotationId)->update([
            'status' => $status,
            'metadata' => json_encode([
                'rotated_backups' => $rotated,
                'failed_backups' => $failed,
                'orphaned_old_objects' => $orphanedOldObjects,
                'orphaned_new_objects' => $orphanedNewObjects,
                'tenant_id' => $this->option('tenant'),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);

        $this->info(
            "Rotasi selesai: {$rotated} berhasil, ".count($failed).' gagal, '.count($orphanedOldObjects).' object lama dan '.count($orphanedNewObjects).' object baru perlu cleanup.'
        );

        return $hasPartialFailure ? self::FAILURE : self::SUCCESS;
    }
}
