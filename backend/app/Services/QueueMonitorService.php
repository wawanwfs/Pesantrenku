<?php

namespace App\Services;

use App\Console\Commands\RuntimeHeartbeat;
use App\Support\QueueNames;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class QueueMonitorService
{
    public function snapshot(): array
    {
        $queues = [];
        $totals = ['pending' => 0, 'reserved' => 0, 'delayed' => 0];
        $queueConnection = (string) config('queue.default', 'database');
        $driver = (string) config("queue.connections.{$queueConnection}.driver", $queueConnection);
        $connection = null;

        if ($driver === 'redis') {
            try {
                $connection = Redis::connection((string) config("queue.connections.{$queueConnection}.connection", 'default'));
            } catch (\Throwable) {
                // Monitoring must remain available when Redis or its PHP client
                // is temporarily unavailable. Worker health will still expose
                // the degraded state through its missing heartbeat.
            }
        }

        foreach (QueueNames::monitored() as $queue) {
            $row = [
                'queue' => $queue,
                'pending' => $connection ? (int) $connection->llen("queues:{$queue}") : 0,
                'reserved' => $connection ? (int) $connection->zcard("queues:{$queue}:reserved") : 0,
                'delayed' => $connection ? (int) $connection->zcard("queues:{$queue}:delayed") : 0,
            ];
            $heartbeat = (int) Cache::get(RuntimeHeartbeat::cacheKey('worker', $queue), 0);
            $row['heartbeat_age_seconds'] = $heartbeat > 0 ? max(0, now()->timestamp - $heartbeat) : null;
            $row['healthy'] = $row['heartbeat_age_seconds'] !== null && $row['heartbeat_age_seconds'] <= 120;
            foreach (array_keys($totals) as $state) {
                $totals[$state] += $row[$state];
            }
            $queues[] = $row;
        }

        return ['totals' => $totals, 'queues' => $queues];
    }
}
