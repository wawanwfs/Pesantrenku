<?php

namespace App\Services;

use Aws\S3\S3Client;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationStorage
{
    public function diskName(): string
    {
        return (string) config('pesantrenku.operations.disk', 'local');
    }

    public function disk(): FilesystemAdapter
    {
        return Storage::disk($this->diskName());
    }

    public function put(string $path, mixed $contents): bool
    {
        return (bool) $this->disk()->put($path, $contents, ['visibility' => 'private']);
    }

    public function readStream(string $path)
    {
        return $this->disk()->readStream($path);
    }

    public function exists(?string $path): bool
    {
        return $path !== null && $path !== '' && $this->disk()->exists($path);
    }

    public function get(string $path): string
    {
        return $this->disk()->get($path);
    }

    public function size(string $path): int
    {
        return (int) $this->disk()->size($path);
    }

    public function delete(string $path): bool
    {
        return (bool) $this->disk()->delete($path);
    }

    public function download(string $path, ?string $name = null): StreamedResponse
    {
        return $this->disk()->download($path, $name);
    }

    public function temporaryUrl(string $path, int $minutes = 10): ?string
    {
        try {
            if ($this->diskName() === 's3' && config('pesantrenku.operations.public_endpoint')) {
                $disk = config('filesystems.disks.s3');
                $client = new S3Client([
                    'version' => 'latest',
                    'region' => $disk['region'],
                    'endpoint' => config('pesantrenku.operations.public_endpoint'),
                    'use_path_style_endpoint' => (bool) $disk['use_path_style_endpoint'],
                    'credentials' => [
                        'key' => $disk['key'],
                        'secret' => $disk['secret'],
                    ],
                ]);
                $command = $client->getCommand('GetObject', [
                    'Bucket' => $disk['bucket'],
                    'Key' => $path,
                ]);

                return (string) $client->createPresignedRequest($command, "+{$minutes} minutes")->getUri();
            }

            return $this->disk()->temporaryUrl($path, now()->addMinutes($minutes));
        } catch (\Throwable) {
            return null;
        }
    }
}
