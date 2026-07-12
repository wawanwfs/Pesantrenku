<?php

namespace App\Providers;

use App\Events\NotificationChanged;
use App\Services\MetricRegistry;
use App\Services\RequestTelemetry;
use App\Services\TelemetryService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    private array $queueSpans = [];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(RequestTelemetry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        app(TelemetryService::class)->initialize();

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)->by(strtolower((string) $request->input('login')).'|'.$request->ip()));
        RateLimiter::for('operations', fn (Request $request) => Limit::perMinute(120)->by((string) ($request->user()?->id ?: $request->ip())));
        RateLimiter::for('large-files', fn (Request $request) => Limit::perMinute(12)->by((string) ($request->user()?->id ?: $request->ip())));
        RateLimiter::for('sensitive', fn (Request $request) => Limit::perMinute(20)->by((string) ($request->user()?->id ?: $request->ip())));
        RateLimiter::for('notification-stream', fn (Request $request) => Limit::perMinute(30)->by((string) ($request->user()?->id ?: $request->ip())));

        DB::listen(function (QueryExecuted $query): void {
            app(RequestTelemetry::class)->recordQuery((float) $query->time);
            app(TelemetryService::class)->recordDatabase($query->connectionName, $query->sql, (float) $query->time);
        });

        Queue::createPayloadUsing(fn () => array_filter([
            'otel_traceparent' => app(TelemetryService::class)->traceparent(),
            'pesantenku_queued_at' => microtime(true),
        ]));
        Queue::before(function (JobProcessing $event): void {
            $labels = $this->queueLabels($event->job);
            app(MetricRegistry::class)->increment('pesantenku_queue_jobs_started_total', $labels);
            $payload = $event->job->payload();
            $queuedAt = (float) ($payload['pesantenku_queued_at'] ?? 0);
            if ($queuedAt > 0) {
                app(MetricRegistry::class)->observeMilliseconds('pesantenku_queue_wait_duration', max(0, (microtime(true) - $queuedAt) * 1000), $labels);
            }
            Cache::put($this->queueRuntimeKey($event->job), microtime(true), now()->addHour());
            $this->queueSpans[$this->queueKey($event->job)] = app(TelemetryService::class)->startQueueSpan(
                (string) ($event->job->getQueue() ?: 'default'),
                class_basename($event->job->resolveName()),
                $payload['otel_traceparent'] ?? null,
            );
        });
        Queue::after(function (JobProcessed $event): void {
            app(MetricRegistry::class)->increment('pesantenku_queue_jobs_completed_total', $this->queueLabels($event->job));
            $this->recordQueueDuration($event->job);
            app(TelemetryService::class)->endQueueSpan($this->pullQueueSpan($event->job));
        });
        Queue::exceptionOccurred(function (JobExceptionOccurred $event): void {
            app(MetricRegistry::class)->increment('pesantenku_queue_jobs_exceptions_total', $this->queueLabels($event->job));
            $this->recordQueueDuration($event->job);
            app(TelemetryService::class)->endQueueSpan($this->pullQueueSpan($event->job), $event->exception);
        });
        Queue::failing(function (JobFailed $event): void {
            app(MetricRegistry::class)->increment('pesantenku_queue_jobs_failed_total', $this->queueLabels($event->job));
            app(TelemetryService::class)->endQueueSpan($this->pullQueueSpan($event->job), $event->exception);
        });
        Event::listen(NotificationChanged::class, fn () => app(MetricRegistry::class)->increment('pesantenku_reverb_events_total', ['event' => 'notifications.changed']));
    }

    private function queueLabels($job): array
    {
        return [
            'queue' => (string) ($job->getQueue() ?: 'default'),
            'job' => class_basename($job->resolveName()),
        ];
    }

    private function queueKey($job): string
    {
        return (string) ($job->getJobId() ?: spl_object_id($job));
    }

    private function pullQueueSpan($job): ?array
    {
        $key = $this->queueKey($job);
        $state = $this->queueSpans[$key] ?? null;
        unset($this->queueSpans[$key]);

        return $state;
    }

    private function recordQueueDuration($job): void
    {
        $startedAt = Cache::pull($this->queueRuntimeKey($job));
        if ($startedAt !== null) {
            app(MetricRegistry::class)->observeMilliseconds(
                'pesantenku_queue_job_duration',
                (microtime(true) - (float) $startedAt) * 1000,
                $this->queueLabels($job),
            );
        }
    }

    private function queueRuntimeKey($job): string
    {
        return 'pesantenku:queue-runtime:'.$this->queueKey($job);
    }
}
