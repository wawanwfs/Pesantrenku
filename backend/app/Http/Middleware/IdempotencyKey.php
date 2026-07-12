<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header('Idempotency-Key'));
        if ($key === '') {
            abort_if(config('pesantrenku.security.require_idempotency_key') && $this->isCritical($request), 428, 'Idempotency-Key wajib untuk operasi ini.');

            return $next($request);
        }

        abort_unless(preg_match('/^[a-zA-Z0-9._:-]{8,128}$/', $key), 422, 'Format Idempotency-Key tidak valid.');
        $userId = (int) ($request->user()?->id ?? 0);
        $scope = $request->method().' '.$request->path();
        $requestHash = hash('sha256', json_encode([
            'method' => $request->method(),
            'path' => $request->path(),
            'query' => $request->query(),
            'body' => $request->except(['_token']),
            'user_id' => $userId,
        ]));

        $identity = ['user_id' => $userId, 'scope' => $scope, 'key' => $key];
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
                'expires_at' => now()->addDay(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException) {
            $existing = DB::table('idempotency_keys')->where($identity)->first();

            return $this->replay($existing, $requestHash);
        }

        try {
            $response = $next($request);
            DB::table('idempotency_keys')->where('id', $id)->update([
                'status' => 'completed',
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
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

        return response($existing->response_body ?? '', (int) ($existing->response_status ?: 200), [
            'Content-Type' => 'application/json',
            'Idempotency-Replayed' => 'true',
        ]);
    }

    private function isCritical(Request $request): bool
    {
        return str_contains($request->path(), '/workflow/')
            || str_contains($request->path(), '/backup/') && str_ends_with($request->path(), '/restore')
            || str_contains($request->path(), '/backup/restore-requests/')
            || $request->route('resource') === 'payments';
    }
}
