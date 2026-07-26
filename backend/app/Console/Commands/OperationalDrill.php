<?php

namespace App\Console\Commands;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OperationalDrill extends Command
{
    protected $signature = 'pesantenku:operational-drill
        {--correlation= : Correlation identifier supplied by the release orchestrator}
        {--timeout= : Maximum seconds to wait for downstream confirmations}';

    protected $description = 'Prove alert delivery, SMTP delivery, realtime delivery, and centralized log ingestion end to end';

    public function handle(BroadcastManager $broadcast): int
    {
        if (! app()->environment('production')) {
            $this->error('Operational drill hanya boleh dijalankan pada environment production.');

            return self::FAILURE;
        }

        $correlation = trim((string) ($this->option('correlation') ?: Str::uuid()->toString()));
        $timeout = max(15, min(300, (int) ($this->option('timeout') ?: config('pesantrenku.observability.drill_timeout_seconds', 90))));
        $mailTo = trim((string) config('pesantrenku.observability.drill_mail_to'));
        $alertmanagerApi = trim((string) config('pesantrenku.observability.alertmanager_api_url'));
        $lokiQueryUrl = trim((string) config('pesantrenku.observability.loki_query_url'));
        $confirmationUrl = trim((string) config('pesantrenku.observability.drill_confirmation_url'));
        $confirmationToken = trim((string) config('pesantrenku.observability.drill_confirmation_token'));

        foreach ([
            'PESANTRENKU_OPERATIONAL_DRILL_MAIL_TO' => $mailTo,
            'PESANTRENKU_ALERTMANAGER_API_URL' => $alertmanagerApi,
            'PESANTRENKU_LOKI_QUERY_URL' => $lokiQueryUrl,
            'PESANTRENKU_OPERATIONAL_DRILL_CONFIRM_URL' => $confirmationUrl,
            'PESANTRENKU_OPERATIONAL_DRILL_CONFIRM_TOKEN' => $confirmationToken,
        ] as $name => $value) {
            if ($value === '') {
                $this->error("{$name} wajib dikonfigurasi.");

                return self::FAILURE;
            }
        }
        if (filter_var($mailTo, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Alamat penerima drill SMTP tidak valid.');

            return self::FAILURE;
        }
        foreach ([$confirmationUrl, $alertmanagerApi, $lokiQueryUrl] as $url) {
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                $this->error("URL drill tidak valid: {$url}");

                return self::FAILURE;
            }
        }

        $marker = 'pesantenku-operational-drill-'.$correlation;
        $startedAt = now()->toIso8601String();

        try {
            Log::warning($marker, [
                'event' => 'operational_drill',
                'correlation' => $correlation,
                'started_at' => $startedAt,
            ]);

            Mail::raw("Pesantenku operational drill\nCorrelation: {$correlation}\nStarted: {$startedAt}", function ($message) use ($mailTo, $correlation) {
                $message->to($mailTo)->subject("[Pesantenku Drill] {$correlation}");
            });

            $broadcast->driver()->broadcast(
                ['private-operational.drill'],
                'operational.drill',
                ['correlation' => $correlation, 'marker' => $marker, 'started_at' => $startedAt],
            );

            $alertResponse = Http::timeout(10)->post($alertmanagerApi, [[
                'labels' => [
                    'alertname' => 'PesantenkuOperationalDrill',
                    'service' => 'pesantenku',
                    'severity' => 'info',
                    'correlation' => $correlation,
                ],
                'annotations' => [
                    'summary' => 'Synthetic Pesantenku operational drill',
                    'marker' => $marker,
                ],
                'startsAt' => now()->toRfc3339String(),
                'endsAt' => now()->addMinutes(5)->toRfc3339String(),
                'generatorURL' => (string) config('app.url'),
            ]]);
            if (! $alertResponse->successful()) {
                throw new \RuntimeException('Alertmanager menolak synthetic alert: '.$alertResponse->status().' '.$alertResponse->body());
            }

            $deadline = microtime(true) + $timeout;
            $lokiConfirmed = false;
            $deliveryConfirmed = false;
            $lastDetail = 'confirmation not received';

            while (microtime(true) < $deadline) {
                if (! $lokiConfirmed) {
                    $loki = Http::timeout(10)->get($lokiQueryUrl, [
                        'query' => '{job="docker",environment="production"} |= "'.$marker.'"',
                        'limit' => 20,
                        'start' => now()->subMinutes(5)->getTimestampMs() * 1_000_000,
                        'end' => now()->addMinute()->getTimestampMs() * 1_000_000,
                    ]);
                    $lokiConfirmed = $loki->successful()
                        && (string) $loki->json('status') === 'success'
                        && count((array) $loki->json('data.result', [])) > 0;
                    if (! $lokiConfirmed) {
                        $lastDetail = 'Loki marker belum terlihat';
                    }
                }

                $confirmation = Http::withToken($confirmationToken)
                    ->acceptJson()
                    ->timeout(10)
                    ->get($confirmationUrl, ['correlation' => $correlation]);
                if ($confirmation->successful()) {
                    $sameCorrelation = hash_equals($correlation, (string) $confirmation->json('correlation'));
                    $deliveryConfirmed = $sameCorrelation
                        && (bool) $confirmation->json('alert_received')
                        && (bool) $confirmation->json('mail_received')
                        && (bool) $confirmation->json('reverb_received');
                    if (! $deliveryConfirmed) {
                        $lastDetail = 'Receiver belum mengonfirmasi alert, email, dan Reverb untuk correlation yang sama';
                    }
                } else {
                    $lastDetail = 'Receiver confirmation HTTP '.$confirmation->status();
                }

                if ($lokiConfirmed && $deliveryConfirmed) {
                    Cache::put('pesantenku:operational-drill:last-success', now()->timestamp, now()->addDays(7));
                    Log::info('Pesantenku operational drill completed', ['correlation' => $correlation]);
                    $this->info("Operational drill lulus: {$correlation}");

                    return self::SUCCESS;
                }

                usleep(1_000_000);
            }

            throw new \RuntimeException("Operational drill timeout setelah {$timeout}s: {$lastDetail}");
        } catch (\Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
