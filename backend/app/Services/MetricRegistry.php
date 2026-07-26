<?php

namespace App\Services;

use App\Console\Commands\RuntimeHeartbeat;
use App\Support\QueueNames;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class MetricRegistry
{
    private const INDEX_KEY = 'pesantenku:metrics:index';

    private ?array $identity = null;

    public function increment(string $metric, array $labels = [], int $amount = 1): void
    {
        try {
            $labels = $this->withIdentityLabels($labels);
            $key = $this->key($metric, $labels);
            $ttl = now()->addDays($this->retentionDays());
            $created = Cache::add($key, 0, $ttl);
            Cache::increment($key, $amount);
            Cache::put($key.':seen', now()->timestamp, $ttl);
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
            'pesantenku_info'.$this->formatLabels($this->withIdentityLabels([
                'environment' => (string) config('app.env'),
            ])).' 1',
        ];

        try {
            $leader = $this->claimExporterLease();
            $lines[] = '# TYPE pesantenku_metrics_exporter_leader gauge';
            $lines[] = 'pesantenku_metrics_exporter_leader'.$this->formatLabels($this->identity()).' '.($leader ? 1 : 0);
            if (! $leader) {
                return implode("\n", array_values(array_unique($lines)))."\n";
            }

            $definitions = Cache::get(self::INDEX_KEY, []);
            $retained = [];
            $identity = $this->identity();

            foreach ($definitions as $definition) {
                $seenAt = (int) Cache::get($definition['key'].':seen', 0);
                if ($seenAt <= 0 || $seenAt < now()->subDays($this->retentionDays())->timestamp) {
                    continue;
                }
                $retained[$definition['key']] = $definition;
                $labels = $definition['labels'] ?? [];
                if (($labels['track'] ?? null) !== $identity['track'] || ($labels['revision'] ?? null) !== $identity['revision']) {
                    continue;
                }
                $value = Cache::get($definition['key'], 0);
                $lines[] = '# TYPE '.$definition['metric'].' '.$definition['type'];
                $lines[] = $definition['metric'].$this->formatLabels($labels).' '.((int) $value);
            }
            if (count($retained) !== count($definitions)) {
                Cache::put(self::INDEX_KEY, $retained, now()->addDays($this->retentionDays()));
            }
            $lines[] = '# TYPE pesantenku_metrics_storage_available gauge';
            $lines[] = 'pesantenku_metrics_storage_available 1';
            if (Schema::hasTable('failed_jobs')) {
                $lines[] = '# TYPE pesantenku_queue_failed_jobs gauge';
                $lines[] = 'pesantenku_queue_failed_jobs '.DB::table('failed_jobs')->count();
            }
            $lines[] = '# TYPE pesantenku_queue_depth gauge';
            foreach (QueueNames::monitored() as $queue) {
                try {
                    $redis = Redis::connection('default');
                    $pending = (int) $redis->llen("queues:{$queue}");
                    $reserved = (int) $redis->zcard("queues:{$queue}:reserved");
                    $delayed = (int) $redis->zcard("queues:{$queue}:delayed");
                } catch (\Throwable) {
                    $pending = $reserved = $delayed = -1;
                }
                foreach (['pending' => $pending, 'reserved' => $reserved, 'delayed' => $delayed] as $state => $depth) {
                    $lines[] = 'pesantenku_queue_depth{queue="'.$queue.'",state="'.$state.'"} '.$depth;
                }
                $heartbeat = (int) Cache::get(RuntimeHeartbeat::cacheKey('worker', $queue), 0);
                $age = $heartbeat > 0 ? max(0, now()->timestamp - $heartbeat) : -1;
                $lines[] = '# TYPE pesantenku_worker_heartbeat_age_seconds gauge';
                $lines[] = 'pesantenku_worker_heartbeat_age_seconds{queue="'.$queue.'"} '.$age;
            }
            $lines[] = '# TYPE pesantenku_runtime_heartbeat_age_seconds gauge';
            foreach ([['scheduler', 'primary'], ['reverb', 'primary']] as [$component, $name]) {
                $heartbeat = (int) Cache::get(RuntimeHeartbeat::cacheKey($component, $name), 0);
                $age = $heartbeat > 0 ? max(0, now()->timestamp - $heartbeat) : -1;
                $lines[] = 'pesantenku_runtime_heartbeat_age_seconds{component="'.$component.'",name="'.$name.'"} '.$age;
            }

            $lines[] = '# TYPE pesantenku_backup_last_success_age_seconds gauge';
            $lastBackup = Schema::hasTable('backups')
                ? DB::table('backups')->where('status', 'success')->max('completed_at')
                : null;
            $backupAge = $lastBackup
                ? max(0, now()->timestamp - Carbon::parse($lastBackup)->timestamp)
                : -1;
            $lines[] = 'pesantenku_backup_last_success_age_seconds '.$backupAge;

            $lastDrill = (int) Cache::get('pesantenku:operational-drill:last-success', 0);
            $drillAge = $lastDrill > 0 ? max(0, now()->timestamp - $lastDrill) : -1;
            $lines[] = '# TYPE pesantenku_operational_drill_last_success_age_seconds gauge';
            $lines[] = 'pesantenku_operational_drill_last_success_age_seconds '.$drillAge;

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
        $indexKey = self::INDEX_KEY;
        Cache::lock($indexKey.':lock', 5)->block(2, function () use ($metric, $labels, $key, $type, $indexKey) {
            $definitions = Cache::get($indexKey, []);
            $definitions[$key] = compact('metric', 'labels', 'key', 'type');
            Cache::forever($indexKey, $definitions);
        });
    }

    private function retentionDays(): int
    {
        return max(1, min(90, (int) config('pesantrenku.observability.metric_retention_days', 14)));
    }

    private function claimExporterLease(): bool
    {
        $identity = $this->identity();
        $leaseSeconds = max(15, min(120, (int) config('pesantrenku.observability.metric_exporter_lease_seconds', 45)));
        $leaseKey = 'pesantenku:metrics:exporter-leader:'.hash('sha256', $identity['track'].'|'.$identity['revision']);
        $owner = (string) Cache::get($leaseKey, '');

        if ($owner !== '' && hash_equals($owner, $identity['instance'])) {
            Cache::put($leaseKey, $identity['instance'], now()->addSeconds($leaseSeconds));

            return true;
        }

        return Cache::add($leaseKey, $identity['instance'], now()->addSeconds($leaseSeconds));
    }

    private function withIdentityLabels(array $labels): array
    {
        return array_merge($labels, $this->identity());
    }

    private function identity(): array
    {
        if ($this->identity !== null) {
            return $this->identity;
        }

        $instance = trim((string) config('pesantrenku.observability.metric_identity.instance', gethostname() ?: php_uname('n')));
        $revision = trim((string) config('pesantrenku.observability.metric_identity.revision', 'unknown'));
        $track = trim((string) config('pesantrenku.observability.metric_identity.track', 'stable'));

        return $this->identity = [
            'instance' => $instance !== '' ? $instance : 'unknown',
            'revision' => $revision !== '' ? $revision : 'unknown',
            'track' => $track !== '' ? $track : 'stable',
        ];
    }

    private function formatLabels(array $labels): string
    {
        if ($labels === []) {
            return '';
        }

        ksort($labels);
        $serialized = collect($labels)
            ->map(fn ($value, $name) => $this->labelName((string) $name).'="'.addslashes((string) $value).'"')
            ->implode(',');

        return $serialized === '' ? '' : "{{$serialized}}";
    }

    private function labelName(string $label): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $label) ?: 'label';
    }
}
