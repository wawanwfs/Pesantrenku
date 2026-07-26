<?php

namespace App\Console\Commands;

use App\Jobs\CanaryRoundTripJob;
use App\Services\BackupCipher;
use App\Services\OperationStorage;
use App\Support\QueueNames;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class ProductionReadinessCheck extends Command
{
    protected $signature = 'pesantenku:production-check
        {--skip-database : Skip database and storage connectivity checks}
        {--skip-runtime-probes : Skip probes that require workers and observability services}';

    protected $description = 'Fail deployment when production security, durability, or runtime requirements are not satisfied';

    public function handle(BackupCipher $backupCipher, OperationStorage $operationStorage, BroadcastManager $broadcast): int
    {
        $checks = [];
        $this->check($checks, 'APP_ENV production', app()->environment('production'), app()->environment());
        $this->check($checks, 'APP_DEBUG disabled', ! (bool) config('app.debug'), config('app.debug') ? 'enabled' : 'disabled');
        $appKey = (string) config('app.key');
        $rawAppKey = str_starts_with($appKey, 'base64:')
            ? base64_decode(substr($appKey, 7), true)
            : $appKey;
        $validAppKey = is_string($rawAppKey) && Encrypter::supported($rawAppKey, (string) config('app.cipher'));
        $this->check($checks, 'APP_KEY valid for cipher', $validAppKey, $validAppKey ? 'valid' : 'missing or invalid length');
        $appUrl = (string) config('app.url');
        $appHost = parse_url($appUrl, PHP_URL_HOST);
        $this->check($checks, 'APP_URL uses HTTPS', str_starts_with($appUrl, 'https://'), $appUrl);
        $this->check(
            $checks,
            'APP_URL uses a public hostname',
            is_string($appHost) && $appHost !== '' && ! in_array($appHost, ['localhost', '127.0.0.1'], true) && ! str_ends_with($appHost, '.test') && ! str_ends_with($appHost, '.example'),
            is_string($appHost) ? $appHost : 'invalid',
        );

        $trustedProxies = array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))));
        $this->check($checks, 'Trusted proxies explicit', $trustedProxies !== [] && ! in_array('*', $trustedProxies, true), implode(',', $trustedProxies) ?: 'missing');

        $allowedHosts = collect(config('pesantrenku.security.allowed_hosts', []))->filter()->map(fn ($host) => strtolower((string) $host))->values();
        $this->check($checks, 'Allowed-host enforcement enabled', (bool) config('pesantrenku.security.enforce_allowed_hosts'), config('pesantrenku.security.enforce_allowed_hosts') ? 'enabled' : 'disabled');
        $this->check(
            $checks,
            'Allowed hosts explicit',
            $allowedHosts->isNotEmpty() && ! $allowedHosts->contains('*') && is_string($appHost) && $allowedHosts->contains(strtolower($appHost)),
            $allowedHosts->implode(',') ?: 'missing',
        );
        $this->check($checks, 'Security headers enabled', (bool) config('pesantrenku.security.headers_enabled'), config('pesantrenku.security.headers_enabled') ? 'enabled' : 'disabled');

        foreach (['openssl', 'pdo', 'bcmath', 'mbstring', 'simplexml', 'zip'] as $extension) {
            $this->check($checks, "PHP extension {$extension}", extension_loaded($extension), extension_loaded($extension) ? 'loaded' : 'missing');
        }

        $this->check($checks, 'Database is durable', config('database.default') !== 'sqlite', (string) config('database.default'));
        $this->check($checks, 'Cache uses Redis distributed locks', config('cache.default') === 'redis', (string) config('cache.default'));
        $this->check($checks, 'Sessions are shared', ! in_array(config('session.driver'), ['array', 'file', 'cookie'], true), (string) config('session.driver'));
        $this->check($checks, 'Queue uses Redis worker backend', config('queue.default') === 'redis', (string) config('queue.default'));
        $defaultWorkerTimeout = (int) config('pesantrenku.queue.default_worker_timeout', 120);
        $longWorkerTimeout = (int) config('pesantrenku.queue.long_worker_timeout', 900);
        $visibilityMargin = (int) config('pesantrenku.queue.visibility_margin', 60);
        $defaultRetryAfter = (int) config('queue.connections.redis.retry_after');
        $longRetryAfter = (int) config('queue.connections.redis-long.retry_after');
        $this->check($checks, 'Default queue visibility exceeds worker timeout', $defaultRetryAfter >= $defaultWorkerTimeout + $visibilityMargin, "{$defaultRetryAfter}s retry / {$defaultWorkerTimeout}s worker");
        $this->check($checks, 'Long queue visibility exceeds worker timeout', $longRetryAfter >= $longWorkerTimeout + $visibilityMargin, "{$longRetryAfter}s retry / {$longWorkerTimeout}s worker");
        $this->check($checks, 'Operation files are remote', config('pesantrenku.operations.disk') !== 'local', (string) config('pesantrenku.operations.disk'));
        $this->check($checks, 'Backup encryption uses KMS', config('backup-security.provider') === 'kms' && filled(config('backup-security.kms_key_id')), (string) config('backup-security.provider'));
        $this->check($checks, 'Metrics token configured', filled(config('pesantrenku.observability.metrics_token')), filled(config('pesantrenku.observability.metrics_token')) ? 'configured' : 'missing');
        $this->check($checks, 'Alertmanager endpoint configured', filled(config('pesantrenku.observability.alertmanager_url')), (string) config('pesantrenku.observability.alertmanager_url', 'missing'));
        $this->check($checks, 'Alertmanager API configured', filled(config('pesantrenku.observability.alertmanager_api_url')), (string) config('pesantrenku.observability.alertmanager_api_url', 'missing'));
        $this->check($checks, 'Loki endpoint configured', filled(config('pesantrenku.observability.loki_url')), (string) config('pesantrenku.observability.loki_url', 'missing'));
        $this->check($checks, 'Loki query API configured', filled(config('pesantrenku.observability.loki_query_url')), (string) config('pesantrenku.observability.loki_query_url', 'missing'));
        $drillMailTo = (string) config('pesantrenku.observability.drill_mail_to');
        $drillConfirmationUrl = (string) config('pesantrenku.observability.drill_confirmation_url');
        $drillConfirmationToken = (string) config('pesantrenku.observability.drill_confirmation_token');
        $drillTimeout = (int) config('pesantrenku.observability.drill_timeout_seconds', 90);
        $this->check($checks, 'Operational drill mail recipient configured', filter_var($drillMailTo, FILTER_VALIDATE_EMAIL) !== false, $drillMailTo ?: 'missing');
        $this->check($checks, 'Operational drill confirmation URL uses HTTPS', str_starts_with($drillConfirmationUrl, 'https://'), $drillConfirmationUrl ?: 'missing');
        $this->check($checks, 'Operational drill confirmation token strong', strlen($drillConfirmationToken) >= 32, $drillConfirmationToken !== '' ? 'configured' : 'missing');
        $this->check($checks, 'Operational drill timeout bounded', $drillTimeout >= 15 && $drillTimeout <= 300, (string) $drillTimeout);
        $metricRetention = (int) config('pesantrenku.observability.metric_retention_days', 14);
        $metricLease = (int) config('pesantrenku.observability.metric_exporter_lease_seconds', 45);
        $metricTrack = trim((string) config('pesantrenku.observability.metric_identity.track'));
        $metricRevision = trim((string) config('pesantrenku.observability.metric_identity.revision'));
        $this->check($checks, 'Metric definition retention bounded', $metricRetention >= 1 && $metricRetention <= 90, (string) $metricRetention);
        $this->check($checks, 'Metric exporter lease bounded', $metricLease >= 15 && $metricLease <= 120, (string) $metricLease);
        $this->check($checks, 'Metric track explicit', in_array($metricTrack, ['stable', 'canary'], true), $metricTrack ?: 'missing');
        $this->check($checks, 'Metric revision explicit', $metricRevision !== '' && $metricRevision !== 'unknown', $metricRevision ?: 'missing');
        $this->check($checks, 'Idempotency required', (bool) config('pesantrenku.security.require_idempotency_key'), config('pesantrenku.security.require_idempotency_key') ? 'required' : 'optional');
        $processingTtl = (int) config('pesantrenku.security.idempotency_processing_ttl_minutes');
        $completedTtl = (int) config('pesantrenku.security.idempotency_completed_ttl_hours');
        $this->check($checks, 'Idempotency processing lease bounded', $processingTtl >= 5 && $processingTtl <= 120, (string) $processingTtl);
        $this->check($checks, 'Idempotency replay retention bounded', $completedTtl >= 1 && $completedTtl <= 168, (string) $completedTtl);
        $this->check($checks, 'Secure session cookie', (bool) config('session.secure'), config('session.secure') ? 'enabled' : 'disabled');
        $this->check($checks, 'HTTP-only session cookie', (bool) config('session.http_only'), config('session.http_only') ? 'enabled' : 'disabled');
        $this->check($checks, 'Encrypted sessions', (bool) config('session.encrypt'), config('session.encrypt') ? 'enabled' : 'disabled');
        $sanctumExpiration = (int) config('sanctum.expiration');
        $this->check($checks, 'API token expiration bounded', $sanctumExpiration >= 60 && $sanctumExpiration <= 10080, (string) $sanctumExpiration);
        $sameSite = strtolower((string) config('session.same_site'));
        $this->check($checks, 'Session SameSite policy', in_array($sameSite, ['lax', 'strict'], true), $sameSite ?: 'missing');

        $qrChallengeSeconds = (int) config('pesantrenku.qr.challenge_seconds', 30);
        $qrAllowedNetworks = collect(config('pesantrenku.qr.allowed_networks', []))
            ->map(fn ($network) => trim((string) $network))
            ->filter()
            ->values();
        $validQrNetworks = $qrAllowedNetworks->isNotEmpty()
            && $qrAllowedNetworks->every(fn ($network) => $this->validRestrictedCidr($network));
        $this->check($checks, 'QR scanner challenge bounded', $qrChallengeSeconds >= 10 && $qrChallengeSeconds <= 60, (string) $qrChallengeSeconds);
        $this->check(
            $checks,
            'QR scanner networks explicit and restricted',
            $validQrNetworks,
            $qrAllowedNetworks->implode(',') ?: 'missing',
        );

        $mailer = (string) config('mail.default');
        $fromAddress = (string) config('mail.from.address');
        $this->check($checks, 'Production mail transport', ! in_array($mailer, ['log', 'array'], true), $mailer ?: 'missing');
        $this->check(
            $checks,
            'Production mail sender',
            filter_var($fromAddress, FILTER_VALIDATE_EMAIL) !== false && ! str_ends_with(strtolower($fromAddress), '@example.com'),
            $fromAddress ?: 'missing',
        );

        $bucket = (string) config('filesystems.disks.s3.bucket');
        $this->check($checks, 'Object storage bucket configured', $bucket !== '', $bucket !== '' ? $bucket : 'missing');

        $websocketEnabled = (bool) config('pesantrenku.notifications.websocket_enabled');
        $this->check($checks, 'Production broadcast driver', ! $websocketEnabled || config('broadcasting.default') === 'reverb', (string) config('broadcasting.default'));
        $sseEnabled = (bool) config('pesantrenku.notifications.sse_enabled');
        $websocketOrigin = (string) config('pesantrenku.notifications.websocket_origin');
        $reverbAppId = (string) config('reverb.apps.apps.0.app_id');
        $reverbAppKey = (string) config('reverb.apps.apps.0.key');
        $reverbAppSecret = (string) config('reverb.apps.apps.0.secret');
        $reverbScaling = (bool) config('reverb.servers.reverb.scaling.enabled');
        $allowedOrigins = collect(config('reverb.apps.apps.0.allowed_origins', []))->filter()->values();
        $this->check($checks, 'Realtime notification transport', $websocketEnabled || $sseEnabled, $websocketEnabled ? 'websocket' : ($sseEnabled ? 'sse' : 'disabled'));
        if ($websocketEnabled) {
            $this->check($checks, 'WebSocket origin uses WSS', str_starts_with($websocketOrigin, 'wss://'), $websocketOrigin ?: 'missing');
            $this->check($checks, 'Reverb application ID configured', $reverbAppId !== '', $reverbAppId ?: 'missing');
            $this->check($checks, 'Reverb application key strong', strlen($reverbAppKey) >= 16, $reverbAppKey !== '' ? 'configured' : 'missing');
            $this->check($checks, 'Reverb application secret strong', strlen($reverbAppSecret) >= 32, $reverbAppSecret !== '' ? 'configured' : 'missing');
            $this->check($checks, 'Reverb Redis scaling enabled', $reverbScaling, $reverbScaling ? 'enabled' : 'disabled');
            $this->check($checks, 'Reverb origins explicit', $allowedOrigins->isNotEmpty() && ! $allowedOrigins->contains('*'), $allowedOrigins->implode(',') ?: 'missing');
        }

        $importMaxRows = (int) config('pesantrenku.import.max_rows');
        $importMaxBytes = (int) config('pesantrenku.import.max_uncompressed_bytes');
        $this->check($checks, 'Import row limit bounded', $importMaxRows > 0 && $importMaxRows <= 100000, (string) $importMaxRows);
        $this->check($checks, 'Import extraction limit bounded', $importMaxBytes > 0 && $importMaxBytes <= 268435456, (string) $importMaxBytes);
        $operationProcessingTtl = (int) config('pesantrenku.operations.processing_timeout_minutes');
        $operationQueueTtl = (int) config('pesantrenku.operations.queue_wait_timeout_minutes');
        $operationRecoveryAttempts = (int) config('pesantrenku.operations.max_recovery_attempts');
        $this->check($checks, 'Operation processing timeout bounded', $operationProcessingTtl >= 16 && $operationProcessingTtl <= 120, (string) $operationProcessingTtl);
        $this->check($checks, 'Operation queue wait bounded', $operationQueueTtl >= 30 && $operationQueueTtl <= 1440, (string) $operationQueueTtl);
        $this->check($checks, 'Operation recovery attempts bounded', $operationRecoveryAttempts >= 1 && $operationRecoveryAttempts <= 10, (string) $operationRecoveryAttempts);

        $backupMaxRows = (int) config('pesantrenku.backup.max_rows');
        $backupMaxBytes = (int) config('pesantrenku.backup.max_plaintext_bytes');
        $requiredApprovals = (int) config('backup-security.required_approvals');
        $restoreTtl = (int) config('backup-security.request_ttl_minutes');
        $this->check($checks, 'Backup row limit bounded', $backupMaxRows > 0 && $backupMaxRows <= 100000, (string) $backupMaxRows);
        $this->check($checks, 'Backup size limit bounded', $backupMaxBytes >= 1048576 && $backupMaxBytes <= 268435456, (string) $backupMaxBytes);
        $this->check($checks, 'Restore approval quorum bounded', $requiredApprovals >= 2 && $requiredApprovals <= 10, (string) $requiredApprovals);
        $this->check($checks, 'Restore approval TTL bounded', $restoreTtl >= 5 && $restoreTtl <= 1440, (string) $restoreTtl);
        $restoreExecutionTtl = (int) config('pesantrenku.backup.restore_execution_timeout_minutes');
        $backupCreationTtl = (int) config('pesantrenku.backup.creation_timeout_minutes');
        $backupQueueTtl = (int) config('pesantrenku.backup.queue_wait_timeout_minutes');
        $this->check($checks, 'Restore execution timeout bounded', $restoreExecutionTtl >= 15 && $restoreExecutionTtl <= 120, (string) $restoreExecutionTtl);
        $this->check($checks, 'Backup creation timeout bounded', $backupCreationTtl >= 16 && $backupCreationTtl <= 120, (string) $backupCreationTtl);
        $this->check($checks, 'Backup queue wait bounded', $backupQueueTtl >= 30 && $backupQueueTtl <= 1440, (string) $backupQueueTtl);

        if (! $this->option('skip-database')) {
            try {
                DB::select('select 1');
                $this->check($checks, 'Database connectivity', true, 'connected');

                $recoveryStorageReady = DB::getSchemaBuilder()->hasTable('mfa_recovery_codes');
                $privilegedRoles = config('pesantrenku.auth.privileged_roles', ['super_admin', 'owner_pesantren']);
                $activeProductionUsers = DB::table('users')
                    ->where('users.status', 'active')
                    ->whereNull('users.deleted_at')
                    ->where('users.email', 'not like', '%.test');

                $activeSuperAdmins = (clone $activeProductionUsers)
                    ->whereExists(function ($query) {
                        $query->selectRaw('1')
                            ->from('role_user')
                            ->join('roles', 'roles.id', '=', 'role_user.role_id')
                            ->whereColumn('role_user.user_id', 'users.id')
                            ->where('roles.name', 'super_admin');
                    });

                $privilegedUsers = (clone $activeProductionUsers)
                    ->where(function ($query) use ($privilegedRoles) {
                        $query->whereExists(function ($globalRole) use ($privilegedRoles) {
                            $globalRole->selectRaw('1')
                                ->from('role_user')
                                ->join('roles', 'roles.id', '=', 'role_user.role_id')
                                ->whereColumn('role_user.user_id', 'users.id')
                                ->whereIn('roles.name', $privilegedRoles);
                        })->orWhereExists(function ($tenantRole) use ($privilegedRoles) {
                            $tenantRole->selectRaw('1')
                                ->from('pesantren_user')
                                ->join('roles', 'roles.id', '=', 'pesantren_user.role_id')
                                ->join('pesantrens', 'pesantrens.id', '=', 'pesantren_user.pesantren_id')
                                ->whereColumn('pesantren_user.user_id', 'users.id')
                                ->where('pesantren_user.status', 'active')
                                ->where('pesantrens.status', 'active')
                                ->whereNull('pesantrens.deleted_at')
                                ->whereIn('roles.name', $privilegedRoles);
                        });
                    });

                $readyScope = function ($query) {
                    $query->where('users.mfa_enabled', true)
                        ->whereNotNull('users.mfa_secret')
                        ->whereExists(function ($recoveryCode) {
                            $recoveryCode->selectRaw('1')
                                ->from('mfa_recovery_codes')
                                ->whereColumn('mfa_recovery_codes.user_id', 'users.id')
                                ->whereNull('mfa_recovery_codes.used_at');
                        });
                };

                $superAdminCount = (clone $activeSuperAdmins)->count();
                $readySuperAdminCount = $recoveryStorageReady
                    ? (clone $activeSuperAdmins)->where($readyScope)->count()
                    : 0;
                $privilegedCount = (clone $privilegedUsers)->count();
                $readyPrivilegedCount = $recoveryStorageReady
                    ? (clone $privilegedUsers)->where($readyScope)->count()
                    : 0;

                $this->check($checks, 'MFA recovery storage migrated', $recoveryStorageReady, $recoveryStorageReady ? 'ready' : 'missing');
                $this->check(
                    $checks,
                    'Active production super admin has MFA and recovery',
                    $superAdminCount > 0 && $readySuperAdminCount === $superAdminCount,
                    "{$readySuperAdminCount}/{$superAdminCount} ready",
                );
                $this->check(
                    $checks,
                    'All privileged production accounts have MFA and recovery',
                    $privilegedCount > 0 && $readyPrivilegedCount === $privilegedCount,
                    "{$readyPrivilegedCount}/{$privilegedCount} ready",
                );
            } catch (\Throwable $exception) {
                $this->check($checks, 'Database connectivity', false, class_basename($exception));
            }

            try {
                $pong = Redis::connection((string) config('queue.connections.redis.connection', 'default'))->ping();
                $connected = in_array($pong, [true, 'PONG', '+PONG'], true);
                $this->check($checks, 'Redis connectivity', $connected, $connected ? 'connected' : 'unexpected response');
            } catch (\Throwable $exception) {
                $this->check($checks, 'Redis connectivity', false, class_basename($exception));
            }

            $probe = 'health/production-check-'.bin2hex(random_bytes(8)).'.txt';
            try {
                $contents = now()->toIso8601String().'-'.bin2hex(random_bytes(8));
                $written = $operationStorage->put($probe, $contents);
                $readable = $written && $operationStorage->exists($probe) && hash_equals($contents, $operationStorage->get($probe));
                $signedUrl = $readable ? $operationStorage->temporaryUrl($probe, 2) : null;
                $signedResponse = is_string($signedUrl) && $signedUrl !== '' ? Http::timeout(10)->get($signedUrl) : null;
                $signedReadReady = $signedResponse?->successful() && hash_equals($contents, $signedResponse->body());
                $deleted = $readable && $operationStorage->delete($probe) && ! $operationStorage->exists($probe);
                $storageReady = $readable && $signedReadReady && $deleted;
                $detail = ! $readable ? 'read/write failed' : (! $signedReadReady ? 'signed read failed' : ($deleted ? 'ready' : 'delete verification failed'));
                $this->check($checks, 'Operation storage signed read/write/delete', $storageReady, $detail);
            } catch (\Throwable $exception) {
                $this->check($checks, 'Operation storage signed read/write/delete', false, class_basename($exception));
            } finally {
                try {
                    if ($operationStorage->exists($probe)) {
                        $operationStorage->delete($probe);
                    }
                } catch (\Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            try {
                $plaintext = 'kms-readiness-'.bin2hex(random_bytes(32));
                $encrypted = $backupCipher->encrypt($plaintext);
                $decrypted = $backupCipher->decrypt($encrypted['contents'], $encrypted['provider'], $encrypted['key_id']);
                $this->check($checks, 'KMS envelope encrypt/decrypt', hash_equals($plaintext, $decrypted), (string) $encrypted['provider']);
            } catch (\Throwable $exception) {
                $this->check($checks, 'KMS envelope encrypt/decrypt', false, class_basename($exception));
            }

            try {
                $latestBackup = DB::table('backups')->where('status', 'success')->max('completed_at');
                $maxAgeHours = max(1, (int) config('pesantrenku.backup.readiness_max_age_hours', 24));
                $ageHours = $latestBackup ? now()->diffInHours(Carbon::parse($latestBackup)) : PHP_INT_MAX;
                $this->check($checks, 'Recent successful backup exists', $ageHours <= $maxAgeHours, $latestBackup ? "{$ageHours}h old" : 'missing');
            } catch (\Throwable $exception) {
                $this->check($checks, 'Recent successful backup exists', false, class_basename($exception));
            }
        }

        if (! $this->option('skip-runtime-probes')) {
            foreach (['Alertmanager' => (string) config('pesantrenku.observability.alertmanager_url'), 'Loki' => (string) config('pesantrenku.observability.loki_url')] as $name => $url) {
                try {
                    $response = Http::timeout(5)->get($url);
                    $this->check($checks, "{$name} readiness", $response->successful(), (string) $response->status());
                } catch (\Throwable $exception) {
                    $this->check($checks, "{$name} readiness", false, class_basename($exception));
                }
            }

            try {
                $marker = 'pesantenku:readiness:queue:'.bin2hex(random_bytes(12));
                Cache::forget($marker);
                CanaryRoundTripJob::dispatch($marker)->onQueue(QueueNames::DEFAULT);
                $deadline = microtime(true) + 30;
                while (! Cache::has($marker) && microtime(true) < $deadline) {
                    usleep(250000);
                }
                $this->check($checks, 'Queue worker roundtrip', (bool) Cache::pull($marker), 'default queue');
            } catch (\Throwable $exception) {
                $this->check($checks, 'Queue worker roundtrip', false, class_basename($exception));
            }

            try {
                if ($websocketEnabled) {
                    $broadcast->driver('reverb')->broadcast(
                        ['private-readiness.probe'],
                        'readiness.probe',
                        ['request_id' => bin2hex(random_bytes(12)), 'at' => now()->toIso8601String()],
                    );
                }
                $this->check($checks, 'Reverb publish probe', ! $websocketEnabled || config('broadcasting.default') === 'reverb', $websocketEnabled ? 'published' : 'websocket disabled');
            } catch (\Throwable $exception) {
                $this->check($checks, 'Reverb publish probe', false, class_basename($exception));
            }

            foreach ([['worker', QueueNames::DEFAULT], ['worker', QueueNames::BACKUPS], ['worker', QueueNames::IMPORTS], ['worker', QueueNames::EXPORTS], ['scheduler', 'primary'], ['reverb', 'primary']] as [$component, $name]) {
                $last = (int) Cache::get(RuntimeHeartbeat::cacheKey($component, $name), 0);
                $age = $last > 0 ? max(0, now()->timestamp - $last) : PHP_INT_MAX;
                $this->check($checks, "Runtime heartbeat {$component}/{$name}", $age <= 120, $last > 0 ? "{$age}s" : 'missing');
            }

            try {
                $host = (string) config('mail.mailers.smtp.host');
                $port = (int) config('mail.mailers.smtp.port');
                $errno = 0;
                $error = '';
                $socket = @fsockopen($host, $port, $errno, $error, 5);
                $ready = is_resource($socket);
                if ($ready) {
                    fclose($socket);
                }
                $this->check($checks, 'SMTP socket connectivity', $ready, $ready ? "{$host}:{$port}" : "{$errno} {$error}");
            } catch (\Throwable $exception) {
                $this->check($checks, 'SMTP socket connectivity', false, class_basename($exception));
            }
        }

        $this->table(['Check', 'Status', 'Detail'], collect($checks)->map(fn (array $check) => [
            $check['name'],
            $check['passed'] ? 'PASS' : 'FAIL',
            $check['detail'],
        ])->all());

        $failed = collect($checks)->where('passed', false)->count();
        if ($failed > 0) {
            $this->error("Production readiness failed with {$failed} blocking check(s).");

            return self::FAILURE;
        }

        $this->info('Production readiness checks passed.');

        return self::SUCCESS;
    }

    private function validRestrictedCidr(string $cidr): bool
    {
        if (! preg_match('/^([^\/]+)\/(\d{1,3})$/', $cidr, $matches)) {
            return false;
        }

        $ip = $matches[1];
        $prefix = (int) $matches[2];
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if ($prefix < 8 || $prefix > 32 || $cidr === '0.0.0.0/0') {
                return false;
            }

            $packed = ip2long($ip);
            if ($packed === false) {
                return false;
            }

            return ($packed & 0xFF000000) === 0x0A000000
                || ($packed & 0xFFF00000) === 0xAC100000
                || ($packed & 0xFFFF0000) === 0xC0A80000;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $prefix >= 16 && $prefix <= 128
                && ! in_array(strtolower($cidr), ['::/0', '0:0:0:0:0:0:0:0/0'], true)
                && (str_starts_with(strtolower($ip), 'fc') || str_starts_with(strtolower($ip), 'fd'));
        }

        return false;
    }

    private function check(array &$checks, string $name, bool $passed, string $detail): void
    {
        $checks[] = compact('name', 'passed', 'detail');
    }
}
