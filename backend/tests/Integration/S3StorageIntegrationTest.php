<?php

namespace Tests\Integration;

use App\Services\OperationStorage;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class S3StorageIntegrationTest extends TestCase
{
    public function test_minio_signed_url_and_lifecycle_are_operational(): void
    {
        if (! env('RUN_S3_INTEGRATION')) {
            $this->markTestSkipped('Set RUN_S3_INTEGRATION=true to run MinIO integration tests.');
        }

        config()->set('filesystems.disks.s3', $this->diskConfig());
        config()->set('pesantrenku.operations.disk', 's3');
        Storage::forgetDisk('s3');
        $storage = app(OperationStorage::class);
        $path = 'imports/integration/'.uniqid('signed-', true).'.txt';

        $this->assertTrue($storage->put($path, 'pesantenku-minio-integration'));
        $this->assertSame('pesantenku-minio-integration', $storage->get($path));
        $signedUrl = $storage->temporaryUrl($path, 5);
        $this->assertNotNull($signedUrl);
        $this->assertSame('pesantenku-minio-integration', Http::get($signedUrl)->throw()->body());

        $lifecycle = $this->client()->getBucketLifecycleConfiguration(['Bucket' => 'pesantenku-integration']);
        $prefixes = collect($lifecycle->get('Rules'))->map(fn ($rule) => data_get($rule, 'Filter.Prefix'))->filter();
        $this->assertTrue($prefixes->contains('imports/'));
        $this->assertTrue($prefixes->contains('backups/'));

        $this->assertTrue($storage->delete($path));
    }

    private function diskConfig(): array
    {
        return [
            'driver' => 's3',
            'key' => 'pesantenku',
            'secret' => 'pesantenku-secret',
            'region' => 'us-east-1',
            'bucket' => 'pesantenku-integration',
            'endpoint' => 'http://127.0.0.1:9000',
            'use_path_style_endpoint' => true,
            'throw' => true,
        ];
    }

    private function client(): S3Client
    {
        return new S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'endpoint' => 'http://127.0.0.1:9000',
            'use_path_style_endpoint' => true,
            'credentials' => ['key' => 'pesantenku', 'secret' => 'pesantenku-secret'],
        ]);
    }
}
