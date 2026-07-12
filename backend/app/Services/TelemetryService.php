<?php

namespace App\Services;

use Illuminate\Http\Request;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\SdkAutoloader;

class TelemetryService
{
    private bool $initialized = false;

    public function enabled(): bool
    {
        return (bool) config('pesantrenku.observability.otel_enabled', false);
    }

    public function initialize(): void
    {
        if ($this->initialized || ! $this->enabled()) {
            return;
        }

        $settings = config('pesantrenku.observability.otel', []);
        $environment = [
            'OTEL_PHP_AUTOLOAD_ENABLED' => 'true',
            'OTEL_PHP_EXPERIMENTAL_AUTO_ROOT_SPAN' => 'false',
            'OTEL_SERVICE_NAME' => $settings['service_name'] ?? 'pesantenku',
            'OTEL_RESOURCE_ATTRIBUTES' => $settings['resource_attributes'] ?? 'service.namespace=pesantenku',
            'OTEL_TRACES_EXPORTER' => $settings['traces_exporter'] ?? 'none',
            'OTEL_EXPORTER_OTLP_PROTOCOL' => $settings['exporter_protocol'] ?? 'http/protobuf',
            'OTEL_EXPORTER_OTLP_ENDPOINT' => $settings['exporter_endpoint'] ?? 'http://127.0.0.1:4318',
            'OTEL_PROPAGATORS' => $settings['propagators'] ?? 'tracecontext,baggage',
            'OTEL_TRACES_SAMPLER' => $settings['sampler'] ?? 'parentbased_always_on',
            'OTEL_PHP_TRACES_PROCESSOR' => $settings['processor'] ?? 'batch',
            'OTEL_BSP_SCHEDULE_DELAY' => (string) ($settings['batch_schedule_delay'] ?? 200),
            'OTEL_BSP_EXPORT_TIMEOUT' => (string) ($settings['batch_export_timeout'] ?? 1000),
            'OTEL_BSP_MAX_QUEUE_SIZE' => (string) ($settings['batch_max_queue_size'] ?? 2048),
            'OTEL_BSP_MAX_EXPORT_BATCH_SIZE' => (string) ($settings['batch_max_export_size'] ?? 512),
        ];
        if ($settings['sampler_argument'] ?? null) {
            $environment['OTEL_TRACES_SAMPLER_ARG'] = $settings['sampler_argument'];
        }

        foreach ($environment as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        SdkAutoloader::autoload();
        $this->initialized = true;
    }

    public function startHttpSpan(Request $request): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $this->initialize();
            $route = $request->route()?->getName() ?: $request->route()?->uri() ?: '/'.ltrim($request->path(), '/');
            $parent = Globals::propagator()->extract($request->headers->all());
            $span = Globals::tracerProvider()->getTracer('pesantenku.http')
                ->spanBuilder($request->method().' '.$route)
                ->setSpanKind(SpanKind::KIND_SERVER)
                ->setParent($parent)
                ->setAttributes([
                    'http.request.method' => $request->method(),
                    'http.route' => (string) $route,
                    'server.address' => $request->getHost(),
                    'url.path' => '/'.ltrim($request->path(), '/'),
                ])
                ->startSpan();

            return ['span' => $span, 'scope' => $span->activate()];
        } catch (\Throwable) {
            return null;
        }
    }

    public function endHttpSpan(?array $state, ?\Throwable $exception = null): void
    {
        if (! $state) {
            return;
        }

        try {
            if ($exception) {
                $state['span']->recordException($exception)->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
            }
            $state['scope']->detach();
            $state['span']->end();
        } catch (\Throwable) {
            // Tracing is best-effort and must not affect the request.
        }
    }

    public function traceId(): ?string
    {
        try {
            $context = Span::getCurrent()->getContext();

            return $context->isValid() ? $context->getTraceId() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function traceparent(): ?string
    {
        try {
            $context = Span::getCurrent()->getContext();
            if (! $context->isValid()) {
                return null;
            }

            return '00-'.$context->getTraceId().'-'.$context->getSpanId().'-'.str_pad(dechex($context->getTraceFlags()), 2, '0', STR_PAD_LEFT);
        } catch (\Throwable) {
            return null;
        }
    }

    public function annotateHttp(array $attributes, ?\Throwable $exception = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            $span = Span::getCurrent();
            $span->setAttributes($attributes);
            if ($exception) {
                $span->recordException($exception)->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
            } elseif (($attributes['http.response.status_code'] ?? 200) >= 500) {
                $span->setStatus(StatusCode::STATUS_ERROR);
            } else {
                $span->setStatus(StatusCode::STATUS_OK);
            }
        } catch (\Throwable) {
            // Tracing is best-effort and must not affect the request.
        }
    }

    public function recordDatabase(string $connection, string $sql, float $durationMs): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            $span = Globals::tracerProvider()->getTracer('pesantenku.database')
                ->spanBuilder('db.query')
                ->setSpanKind(SpanKind::KIND_CLIENT)
                ->setStartTimestamp((int) ((microtime(true) - ($durationMs / 1000)) * 1_000_000_000))
                ->setAttributes([
                    'db.system.name' => $connection,
                    'db.operation.name' => strtolower(strtok(ltrim($sql), " \t\n\r")) ?: 'query',
                    'db.query.summary' => str($sql)->squish()->limit(180, '')->toString(),
                ])
                ->startSpan();
            $span->end();
        } catch (\Throwable) {
            // Database tracing is best-effort.
        }
    }

    public function startQueueSpan(string $queue, string $job, ?string $traceparent = null): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $this->initialize();
            $builder = Globals::tracerProvider()->getTracer('pesantenku.queue')
                ->spanBuilder($job.' process')
                ->setSpanKind(SpanKind::KIND_CONSUMER)
                ->setAttributes([
                    'messaging.system' => 'laravel_queue',
                    'messaging.destination.name' => $queue,
                    'messaging.operation.type' => 'process',
                    'messaging.message.type' => $job,
                ]);
            if ($traceparent) {
                $builder->setParent(Globals::propagator()->extract(['traceparent' => $traceparent]));
            }
            $span = $builder->startSpan();

            return ['span' => $span, 'scope' => $span->activate()];
        } catch (\Throwable) {
            return null;
        }
    }

    public function endQueueSpan(?array $state, ?\Throwable $exception = null): void
    {
        if (! $state) {
            return;
        }

        try {
            if ($exception) {
                $state['span']->recordException($exception)->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
            } else {
                $state['span']->setStatus(StatusCode::STATUS_OK);
            }
            $state['scope']->detach();
            $state['span']->end();
        } catch (\Throwable) {
            // Queue tracing is best-effort.
        }
    }
}
