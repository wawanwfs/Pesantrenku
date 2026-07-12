<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use App\Services\OperationStorage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pesantenku:cleanup-files', function () {
    $storage = app(OperationStorage::class);
    $jobs = DB::table('operation_jobs')->whereNotNull('expires_at')->where('expires_at', '<=', now())->get();
    foreach ($jobs as $job) {
        foreach (array_unique(array_filter([$job->source_path, $job->output_path, $job->file_path])) as $path) {
            $storage->delete($path);
        }
        DB::table('operation_jobs')->where('id', $job->id)->update([
            'status' => $job->status === 'completed' ? 'expired' : $job->status,
            'source_path' => null,
            'output_path' => null,
            'file_path' => null,
            'updated_at' => now(),
        ]);
    }
    $this->info("{$jobs->count()} operation file dibersihkan.");
})->purpose('Delete expired import/export temporary and result files');

Schedule::call(fn () => DB::table('idempotency_keys')->where('expires_at', '<=', now())->delete())
    ->name('cleanup-idempotency-keys')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('pesantenku:cleanup-files')->dailyAt('02:30')->withoutOverlapping();
