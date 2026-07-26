<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RuntimeHeartbeat extends Command
{
    protected $signature = 'pesantenku:runtime-heartbeat
        {component : worker, scheduler, or reverb}
        {name : Stable instance/queue name}
        {--check= : Fail when the last heartbeat is older than this many seconds}';

    protected $description = 'Write or verify a distributed runtime heartbeat';

    public function handle(): int
    {
        $component = strtolower(trim((string) $this->argument('component')));
        $name = trim((string) $this->argument('name'));
        if (! in_array($component, ['worker', 'scheduler', 'reverb'], true) || $name === '') {
            $this->error('Component atau nama heartbeat tidak valid.');

            return self::INVALID;
        }

        $key = $this->key($component, $name);
        $maxAge = $this->option('check');
        if ($maxAge !== null) {
            $maxAge = max(1, (int) $maxAge);
            $last = (int) Cache::get($key, 0);
            $age = $last > 0 ? max(0, now()->timestamp - $last) : PHP_INT_MAX;
            if ($last <= 0 || $age > $maxAge) {
                $this->error("Heartbeat {$component}/{$name} stale atau belum ada.");

                return self::FAILURE;
            }

            $this->info("Heartbeat {$component}/{$name} sehat ({$age}s).");

            return self::SUCCESS;
        }

        Cache::put($key, now()->timestamp, now()->addMinutes(5));
        $this->line("Heartbeat {$component}/{$name} diperbarui.");

        return self::SUCCESS;
    }

    public static function cacheKey(string $component, string $name): string
    {
        return 'pesantenku:runtime-heartbeat:'.strtolower($component).':'.$name;
    }

    private function key(string $component, string $name): string
    {
        return self::cacheKey($component, $name);
    }
}
