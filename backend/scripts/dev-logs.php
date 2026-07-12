<?php

declare(strict_types=1);

if (function_exists('pcntl_fork')) {
    if (in_array('--check', $argv, true)) {
        fwrite(STDOUT, "Laravel Pail tersedia.\n");
        exit(0);
    }

    passthru(escapeshellarg(PHP_BINARY).' artisan pail --timeout=0', $exitCode);
    exit($exitCode);
}

$logPath = dirname(__DIR__).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'laravel.log';

fwrite(STDOUT, "Pail membutuhkan pcntl; menggunakan pemantau laravel.log yang kompatibel dengan Windows.\n");

if (in_array('--check', $argv, true)) {
    exit(0);
}

$handle = null;
$position = 0;

while (true) {
    clearstatcache(true, $logPath);

    if (! is_resource($handle)) {
        if (! is_file($logPath)) {
            usleep(250_000);

            continue;
        }

        $handle = fopen($logPath, 'rb');
        if ($handle === false) {
            usleep(500_000);

            continue;
        }

        fseek($handle, 0, SEEK_END);
        $position = ftell($handle) ?: 0;
    }

    $line = fgets($handle);
    if ($line !== false) {
        $position = ftell($handle) ?: $position;
        fwrite(STDOUT, $line);

        continue;
    }

    $size = is_file($logPath) ? filesize($logPath) : false;
    if ($size === false || $size < $position) {
        fclose($handle);
        $handle = null;
        $position = 0;
    } else {
        usleep(200_000);
    }
}
