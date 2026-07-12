<?php

namespace App\Console\Commands;

use App\Services\BackupCipher;
use App\Services\OperationStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RotateBackupEncryption extends Command
{
    protected $signature = 'pesantenku:rotate-backup-keys {--tenant= : Limit rotation to one tenant}';

    protected $description = 'Re-encrypt backup envelopes with the currently configured KMS or local key';

    public function handle(OperationStorage $storage, BackupCipher $cipher): int
    {
        $backups = DB::table('backups')
            ->when($this->option('tenant'), fn ($query, $tenant) => $query->where('pesantren_id', $tenant))
            ->whereNotNull('file_path')
            ->get();
        $rotated = 0;

        foreach ($backups as $backup) {
            if (! $storage->exists($backup->file_path)) {
                $this->warn("Backup {$backup->id} dilewati karena file tidak ditemukan.");
                continue;
            }
            $encrypted = $cipher->encrypt($cipher->decrypt($storage->get($backup->file_path)));
            if (! $storage->put($backup->file_path, $encrypted['contents'])) {
                $this->error("Backup {$backup->id} gagal ditulis ulang.");
                continue;
            }
            DB::table('backups')->where('id', $backup->id)->update([
                'encryption_provider' => $encrypted['provider'],
                'encryption_key_id' => $encrypted['key_id'],
                'checksum_sha256' => $encrypted['checksum'],
                'updated_at' => now(),
            ]);
            $rotated++;
        }

        DB::table('secret_rotations')->insert([
            'secret_name' => 'backup-envelope-key',
            'new_key_id' => (string) config('backup-security.kms_key_id', 'app-key'),
            'status' => 'completed',
            'metadata' => json_encode(['rotated_backups' => $rotated, 'tenant_id' => $this->option('tenant')]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->info("{$rotated} backup berhasil dienkripsi ulang.");

        return self::SUCCESS;
    }
}
