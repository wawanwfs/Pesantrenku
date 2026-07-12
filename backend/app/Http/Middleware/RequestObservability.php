<?php

namespace App\Http\Middleware;

use App\Services\MetricRegistry;
use App\Services\RequestTelemetry;
use App\Services\TelemetryService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestObservability
{
    public function __construct(
        private readonly MetricRegistry $metrics,
        private readonly RequestTelemetry $telemetry,
        private readonly TelemetryService $tracing,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $spanState = $this->tracing->startHttpSpan($request);
        $correlationId = $this->correlationId($request);
        $traceId = $this->tracing->traceId() ?: $this->traceId($request);
        $this->telemetry->reset();
        $request->attributes->set('correlation_id', $correlationId);
        $request->attributes->set('trace_id', $traceId);

        Log::withContext([
            'correlation_id' => $correlationId,
            'trace_id' => $traceId,
            'request_method' => $request->method(),
            'request_path' => '/'.ltrim($request->path(), '/'),
        ]);

        try {
            $response = $next($request);
        } catch (\Throwable $exception) {
            $this->record($request, 500, $startedAt, $correlationId, $traceId, $exception);
            $this->tracing->endHttpSpan($spanState, $exception);
            throw $exception;
        }

        $response->headers->set('X-Correlation-ID', $correlationId);
        $response->headers->set('traceparent', $this->tracing->traceparent() ?: "00-{$traceId}-".substr(hash('sha256', $correlationId), 0, 16).'-01');
        $this->record($request, $response->getStatusCode(), $startedAt, $correlationId, $traceId);
        if (config('app.debug') || app()->environment('testing')) {
            $durationMs = (hrtime(true) - $startedAt) / 1_000_000;
            $response->headers->set('X-DB-Query-Count', (string) $this->telemetry->queryCount());
            $response->headers->set('X-DB-Slow-Query-Count', (string) $this->telemetry->slowQueryCount());
            $response->headers->set('Server-Timing', sprintf(
                'app;dur=%.3f, db;dur=%.3f, dbslow;desc="%d slow queries"',
                $durationMs,
                $this->telemetry->queryDurationMs(),
                $this->telemetry->slowQueryCount(),
            ));
        }
        $this->tracing->endHttpSpan($spanState);

        return $response;
    }

    private function record(Request $request, int $status, int $startedAt, string $correlationId, string $traceId, ?\Throwable $exception = null): void
    {
        $durationMs = (hrtime(true) - $startedAt) / 1_000_000;
        $route = $request->route()?->getName() ?: $request->route()?->uri() ?: '/'.ltrim($request->path(), '/');
        $labels = [
            'method' => $request->method(),
            'route' => Str::limit((string) $route, 120, ''),
            'status' => (string) $status,
        ];
        $queryCount = $this->telemetry->queryCount();
        $queryDurationMs = $this->telemetry->queryDurationMs();
        $slowQueryCount = $this->telemetry->slowQueryCount();
        $slowestQueryMs = $this->telemetry->slowestQueryMs();
        $this->metrics->increment('pesantenku_http_requests_total', $labels);
        $this->metrics->observeMilliseconds('pesantenku_http_request_duration', $durationMs, $labels);
        $this->metrics->increment('pesantenku_database_queries_total', ['route' => $labels['route']], $queryCount);
        $this->metrics->observeMilliseconds('pesantenku_database_query_duration', $queryDurationMs, ['route' => $labels['route']]);
        $this->metrics->increment('pesantenku_database_slow_queries_total', ['route' => $labels['route']], $slowQueryCount);
        $this->metrics->observeMilliseconds('pesantenku_database_slowest_query', $slowestQueryMs, ['route' => $labels['route']]);

        $context = [
            'event' => 'http.request.completed',
            'correlation_id' => $correlationId,
            'trace_id' => $traceId,
            'method' => $request->method(),
            'route' => $labels['route'],
            'status' => $status,
            'duration_ms' => round($durationMs, 3),
            'db_query_count' => $queryCount,
            'db_duration_ms' => $queryDurationMs,
            'db_slow_query_count' => $slowQueryCount,
            'db_slowest_query_ms' => $slowestQueryMs,
            'user_id' => $request->user()?->id,
            'tenant_id' => $request->hasSession() ? $request->session()->get('active_pesantren_id') : null,
            'ip' => $request->ip(),
        ];

        $this->tracing->annotateHttp([
            'http.request.method' => $request->method(),
            'http.route' => $labels['route'],
            'http.response.status_code' => $status,
            'server.address' => $request->getHost(),
            'url.path' => '/'.ltrim($request->path(), '/'),
            'enduser.id' => (string) ($request->user()?->id ?? ''),
            'pesantenku.tenant.id' => (string) ($context['tenant_id'] ?? ''),
            'pesantenku.db.query_count' => $queryCount,
            'pesantenku.db.duration_ms' => $queryDurationMs,
        ], $exception);

        $exception ? Log::error('HTTP request failed.', $context + ['exception' => $exception::class]) : Log::info('HTTP request completed.', $context);
    }

    private function correlationId(Request $request): string
    {
        $candidate = trim((string) $request->header('X-Correlation-ID'));

        return preg_match('/^[a-zA-Z0-9._-]{8,128}$/', $candidate) ? $candidate : (string) Str::uuid();
    }

    private function traceId(Request $request): string
    {
        $traceparent = (string) $request->header('traceparent');
        if (preg_match('/^[\da-f]{2}-([\da-f]{32})-[\da-f]{16}-[\da-f]{2}$/i', $traceparent, $matches)) {
            return strtolower($matches[1]);
        }

        return bin2hex(random_bytes(16));
    }
}
