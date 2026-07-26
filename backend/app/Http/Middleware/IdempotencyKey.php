<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyKey
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header('Idempotency-Key'));
        if ($key === '') {
            abort_if((bool) config('pesantrenku.security.require_idempotency_key'), 428, 'Idempotency-Key wajib untuk operasi ini.');

            return $next($request);
        }

        abort_unless(preg_match('/^[a-zA-Z0-9._:-]{8,128}$/', $key), 422, 'Format Idempotency-Key tidak valid.');
        $userId = (int) ($request->user()?->id ?? 0);
        $tenantId = $request->user() ? $this->tenantContext->activePesantrenId($request) : null;
        $tenantScope = $tenantId ? "tenant:{$tenantId}" : 'platform';
        $scope = $tenantScope.' '.$request->method().' '.$request->path();
        $requestHash = hash('sha256', json_encode([
            'method' => $request->method(),
            'path' => $request->path(),
            'query' => $request->query(),
            'body' => $request->except(['_token', 'file']),
            'files' => $this->fileFingerprints($request->allFiles()),
            'user_id' => $userId,
            'tenant_id' => $tenantId,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $identity = ['user_id' => $userId, 'scope' => $scope, 'key' => $key];
        DB::table('idempotency_keys')
            ->where($identity)
            ->where('expires_at', '<=', now())
            ->delete();

        $existing = DB::table('idempotency_keys')->where($identity)->first();
        if ($existing) {
            return $this->replay($existing, $requestHash);
        }

        try {
            $id = DB::table('idempotency_keys')->insertGetId([
                'user_id' => $userId,
                'scope' => $scope,
                'key' => $key,
                'request_hash' => $requestHash,
                'status' => 'processing',
                'expires_at' => now()->addMinutes(max(5, (int) config('pesantrenku.security.idempotency_processing_ttl_minutes', 30))),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $exception) {
            $existing = DB::table('idempotency_keys')->where($identity)->first();
            if (! $existing) {
                throw $exception;
            }

            return $this->replay($existing, $requestHash);
        }

        try {
            $response = $next($request);
            if ($response->getStatusCode() >= 500) {
                DB::table('idempotency_keys')->where('id', $id)->delete();

                return $response;
            }

            DB::table('idempotency_keys')->where('id', $id)->update([
                'status' => 'completed',
                'response_status' => $response->getStatusCode(),
                'response_body' => $this->protectResponseBody((string) $response->getContent()),
                'expires_at' => now()->addHours(max(1, (int) config('pesantrenku.security.idempotency_completed_ttl_hours', 24))),
                'updated_at' => now(),
            ]);

            return $response;
        } catch (\Throwable $exception) {
            DB::table('idempotency_keys')->where('id', $id)->delete();
            throw $exception;
        }
    }

    private function replay(?object $existing, string $requestHash): Response
    {
        abort_unless($existing, 409, 'Permintaan idempotent sedang diproses.');
        abort_if($existing->request_hash !== $requestHash, 409, 'Idempotency-Key telah digunakan untuk payload berbeda.');
        abort_if($existing->status !== 'completed', 409, 'Permintaan dengan Idempotency-Key ini sedang diproses.');

        return response($this->unprotectResponseBody((string) ($existing->response_body ?? '')), (int) ($existing->response_status ?: 200), [
            'Content-Type' => 'application/json',
            'Idempotency-Replayed' => 'true',
        ]);
    }

    private function protectResponseBody(string $body): string
    {
        return 'enc:'.Crypt::encryptString($body);
    }

    private function unprotectResponseBody(string $stored): string
    {
        if (! str_starts_with($stored, 'enc:')) {
            return $stored;
        }

        try {
            return Crypt::decryptString(substr($stored, 4));
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Respons idempotensi tersimpan gagal diverifikasi.', previous: $exception);
        }
    }

    private function fileFingerprints(array $files): array
    {
        $fingerprints = [];
        foreach ($files as $key => $file) {
            if (is_array($file)) {
                $fingerprints[$key] = $this->fileFingerprints($file);

                continue;
            }

            $path = method_exists($file, 'getRealPath') ? $file->getRealPath() : false;
            abort_unless($path && is_readable($path), 422, 'File upload tidak dapat dibaca untuk verifikasi idempotensi.');
            $fingerprints[$key] = [
                'name' => method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : null,
                'size' => method_exists($file, 'getSize') ? $file->getSize() : null,
                'mime' => method_exists($file, 'getClientMimeType') ? $file->getClientMimeType() : null,
                'sha256' => hash_file('sha256', $path),
            ];
        }

        ksort($fingerprints);

        return $fingerprints;
    }
}
