<?php

namespace App\Services;

class RequestTelemetry
{
    private int $queryCount = 0;

    private float $queryDurationMs = 0;

    private int $slowQueryCount = 0;

    private float $slowestQueryMs = 0;

    public function reset(): void
    {
        $this->queryCount = 0;
        $this->queryDurationMs = 0;
        $this->slowQueryCount = 0;
        $this->slowestQueryMs = 0;
    }

    public function recordQuery(float $durationMs): void
    {
        $this->queryCount++;
        $this->queryDurationMs += $durationMs;
        $this->slowestQueryMs = max($this->slowestQueryMs, $durationMs);
        if ($durationMs >= (float) config('pesantrenku.observability.slow_query_ms', 100)) {
            $this->slowQueryCount++;
        }
    }

    public function queryCount(): int
    {
        return $this->queryCount;
    }

    public function queryDurationMs(): float
    {
        return round($this->queryDurationMs, 3);
    }

    public function slowQueryCount(): int
    {
        return $this->slowQueryCount;
    }

    public function slowestQueryMs(): float
    {
        return round($this->slowestQueryMs, 3);
    }
}
