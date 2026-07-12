<?php

namespace App\Services;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class MetricRegistry
{
    private const INDEX_KEY = 'pesantenku:metrics:index';

    public function increment(string $metric, array $labels = [], int $amount = 1): void
    {
        try {
            $key = $this->key($metric, $labels);
            $created = Cache::add($key, 0, now()->addDays(7));
            Cache::increment($key, $amount);
            if ($created) {
                $this->remember($metric, $labels, $key, 'counter');
            }
        } catch (\Throwable) {
            // Telemetry must never turn a successful business request into an error.
        }
    }

    public function observeMilliseconds(string $metric, float $durationMs, array $labels = []): void
    {
        $microseconds = max(0, (int) round($durationMs * 1000));
        $this->increment($metric.'_count', $labels);
        $this->increment($metric.'_sum_microseconds', $labels, $microseconds);
    }

    public function exposition(): string
    {
        $lines = [
            '# HELP pesantenku_info Pesantenku application information.',
            '# TYPE pesantenku_info gauge',
            'pesantenku_info{environment="'.addslashes((string) config('app.env')).'"} 1',
        ];

        try {
            $definitions = Cache::get(self::INDEX_KEY, []);

            foreach ($definitions as $definition) {
                $value = Cache::get($definition['key'], 0);
                $labels = collect($definition['labels'] ?? [])
                    ->map(fn ($value, $name) => $this->labelName($name).'="'.addslashes((string) $value).'"')
                    ->implode(',');
                $lines[] = '# TYPE '.$definition['metric'].' '.$definition['type'];
                $lines[] = $definition['metric'].($labels !== '' ? "{{$labels}}" : '').' '.((int) $value);
            }
            $lines[] = '# TYPE pesantenku_metrics_storage_available gauge';
            $lines[] = 'pesantenku_metrics_storage_available 1';
            if (Schema::hasTable('jobs')) {
                $lines[] = '# TYPE pesantenku_queue_depth gauge';
                $lines[] = 'pesantenku_queue_depth{state="pending"} '.DB::table('jobs')->whereNull('reserved_at')->count();
                $lines[] = 'pesantenku_queue_depth{state="reserved"} '.DB::table('jobs')->whereNotNull('reserved_at')->count();
            }
            if (Schema::hasTable('failed_jobs')) {
                $lines[] = '# TYPE pesantenku_queue_failed_total gauge';
                $lines[] = 'pesantenku_queue_failed_total '.DB::table('failed_jobs')->count();
            }
            foreach (['default', 'imports', 'exports'] as $queue) {
                $lines[] = '# TYPE pesantenku_queue_redis_depth gauge';
                try {
                    $depth = (int) Redis::connection()->llen("queues:{$queue}");
                } catch (\Throwable) {
                    $depth = 0;
                }
                $lines[] = 'pesantenku_queue_redis_depth{queue="'.$queue.'"} '.$depth;
            }
            $lines[] = '# TYPE pesantenku_reverb_configured gauge';
            $lines[] = 'pesantenku_reverb_configured '.(config('broadcasting.default') === 'reverb' ? 1 : 0);
            $connections = 0;
            if (config('broadcasting.default') === 'reverb') {
                try {
                    $connections = (int) (Broadcast::connection('reverb')->getPusher()->get('/connections')->connections ?? 0);
                } catch (\Throwable) {
                    $connections = 0;
                }
            }
            $lines[] = '# TYPE pesantenku_reverb_connections gauge';
            $lines[] = 'pesantenku_reverb_connections '.$connections;
        } catch (\Throwable) {
            $lines[] = '# TYPE pesantenku_metrics_storage_available gauge';
            $lines[] = 'pesantenku_metrics_storage_available 0';
        }

        return implode("\n", array_values(array_unique($lines)))."\n";
    }

    private function key(string $metric, array $labels): string
    {
        ksort($labels);

        return 'pesantenku:metrics:'.hash('sha256', $metric.'|'.json_encode($labels));
    }

    private function remember(string $metric, array $labels, string $key, string $type): void
    {
        Cache::lock(self::INDEX_KEY.':lock', 5)->block(2, function () use ($metric, $labels, $key, $type) {
            $definitions = Cache::get(self::INDEX_KEY, []);
            $definitions[$key] = compact('metric', 'labels', 'key', 'type');
            Cache::put(self::INDEX_KEY, $definitions, now()->addDays(7));
        });
    }

    private function labelName(string $label): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $label) ?: 'label';
    }
}
