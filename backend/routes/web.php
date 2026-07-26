<?php

use App\Http\Controllers\ActionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CrudController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\MfaController;
use App\Http\Controllers\WebController;
use App\Services\OperationStorage;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;

Route::get('/', function (Request $request) {
    if (! $request->user()) {
        return Inertia::render('Landing');
    }

    $role = app(TenantContext::class)->activeRole($request) ?? 'super_admin';
    $panel = str_replace('_', '-', match ($role) {
        'owner_pesantren' => 'owner',
        'admin_keuangan' => 'keuangan',
        'admin_akademik' => 'akademik',
        'admin_tu' => 'tu',
        default => $role,
    });

    return redirect("/dashboard/{$panel}");
});
Route::get('/login', [AuthController::class, 'loginPage'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/auth/session', [AuthController::class, 'sessionStatus'])->name('auth.session');
Route::get('/health', function () {
    $operationStorage = app(OperationStorage::class);
    $operationDisk = $operationStorage->diskName();

    try {
        $databaseStatus = DB::select('select 1 as ok')[0]->ok == 1 ? 'ok' : 'error';
    } catch (Throwable) {
        $databaseStatus = 'error';
    }

    try {
        $queueTableStatus = Schema::hasTable('jobs') ? 'ok' : 'missing';
    } catch (Throwable) {
        $queueTableStatus = 'error';
    }

    $queueConnection = (string) config('queue.default');
    try {
        $queueBackendStatus = match ($queueConnection) {
            'redis' => in_array(Redis::connection((string) config('queue.connections.redis.connection', 'default'))->ping(), [true, 'PONG', '+PONG'], true) ? 'ok' : 'error',
            'database' => $queueTableStatus,
            default => app()->environment('production') ? 'unsupported' : 'ok',
        };
    } catch (Throwable) {
        $queueBackendStatus = 'error';
    }

    $checkStorage = function () use ($operationStorage) {
        $path = '.health/'.Str::uuid().'.txt';
        try {
            if (! $operationStorage->put($path, 'ok')) {
                return 'readonly';
            }
            if (! hash_equals('ok', (string) $operationStorage->get($path))) {
                return 'unreadable';
            }
            if (! $operationStorage->delete($path) || $operationStorage->exists($path)) {
                return 'undeletable';
            }

            return 'ok';
        } catch (Throwable) {
            return 'error';
        } finally {
            try {
                if ($operationStorage->exists($path)) {
                    $operationStorage->delete($path);
                }
            } catch (Throwable) {
                // Health response already reports a storage failure.
            }
        }
    };
    $storageStatus = $checkStorage();

    $queueSchemaReady = $queueConnection !== 'database' || $queueTableStatus === 'ok';
    $ready = $databaseStatus === 'ok'
        && $queueSchemaReady
        && $queueBackendStatus === 'ok'
        && $storageStatus === 'ok';
    $health = [
        'status' => $ready ? 'ok' : 'degraded',
        'app' => config('app.name'),
        'database' => $databaseStatus,
        'queue_table' => $queueTableStatus,
        'queue_backend' => $queueBackendStatus,
        'storage' => $storageStatus,
        'checked_at' => now()->toIso8601String(),
    ];

    if (! app()->environment('production')) {
        $health += [
            'environment' => config('app.env'),
            'debug' => config('app.debug'),
            'storage_disk' => $operationDisk,
            'broadcast' => config('broadcasting.default'),
        ];
    }

    return response()->json($health, $ready ? 200 : 503);
});
Route::get('/metrics', MetricsController::class)->middleware('throttle:60,1');

Route::middleware(['auth', 'authz.current'])->group(function () {
    Route::post('/tenant/switch', [AuthController::class, 'switchTenant'])->name('tenant.switch');
    Route::prefix('web-actions')->group(function () {
        Route::get('/notifications', [ActionController::class, 'notifications']);
        Route::get('/notifications/stream', [ActionController::class, 'notificationStream'])->middleware('throttle:notification-stream');
        Route::post('/notifications/read', [ActionController::class, 'readNotifications'])->middleware('idempotency');
        Route::get('/jobs', [ActionController::class, 'jobs']);
        Route::get('/jobs/{uuid}', [ActionController::class, 'jobStatus']);
        Route::get('/jobs/{uuid}/download', [ActionController::class, 'downloadJob'])->middleware('throttle:large-files');
        Route::delete('/jobs/{uuid}', [ActionController::class, 'deleteJob'])->middleware(['throttle:sensitive', 'idempotency']);
        Route::get('/queue/monitor', [ActionController::class, 'queueMonitor']);
        Route::post('/queue/failed/{uuid}/retry', [ActionController::class, 'retryFailedJob'])->middleware(['throttle:sensitive', 'idempotency']);
        Route::delete('/queue/failed/{uuid}', [ActionController::class, 'forgetFailedJob'])->middleware(['throttle:sensitive', 'idempotency']);
        Route::post('/qr/generate', [ActionController::class, 'generateQr'])->middleware(['throttle:operations', 'idempotency']);
        Route::post('/backup/run', [ActionController::class, 'runBackup'])->middleware(['throttle:sensitive', 'idempotency']);
        Route::get('/backup/restore-requests', [ActionController::class, 'restoreRequests']);
        Route::post('/backup/restore-requests/{id}/approve', [ActionController::class, 'approveRestore'])->whereNumber('id')->middleware(['throttle:sensitive', 'idempotency']);
        Route::post('/backup/restore-requests/{id}/execute', [ActionController::class, 'executeRestore'])->whereNumber('id')->middleware(['throttle:sensitive', 'idempotency']);
        Route::get('/backup/{id}/download', [ActionController::class, 'downloadBackup'])->whereNumber('id')->middleware('throttle:large-files');
        Route::post('/backup/{id}/restore', [ActionController::class, 'restoreBackup'])->whereNumber('id')->middleware(['throttle:sensitive', 'idempotency']);
        Route::post('/workflow/{resource}/{id}/{transition}', [ActionController::class, 'transition'])->whereNumber('id')->middleware(['throttle:operations', 'idempotency']);
    });
    Route::prefix('web-api')->group(function () {
        Route::post('/security/mfa/enroll', [MfaController::class, 'startEnrollment'])->middleware('throttle:sensitive');
        Route::post('/security/mfa/confirm', [MfaController::class, 'confirmEnrollment'])->middleware('throttle:sensitive');
        Route::post('/security/mfa/recovery-codes', [MfaController::class, 'regenerateRecoveryCodes'])->middleware(['throttle:sensitive', 'idempotency']);
        Route::delete('/security/mfa', [MfaController::class, 'disable'])->middleware(['throttle:sensitive', 'idempotency']);
        Route::get('/permissions/matrix', [CrudController::class, 'permissionMatrix']);
        Route::patch('/permissions/matrix', [CrudController::class, 'updatePermissionMatrix'])->middleware(['throttle:sensitive', 'idempotency']);
        Route::post('/permissions/matrix', [CrudController::class, 'updatePermissionMatrix'])->middleware(['throttle:sensitive', 'idempotency']);
        Route::get('/permissions/custom-roles', [CrudController::class, 'customRoles']);
        Route::post('/permissions/custom-roles', [CrudController::class, 'storeCustomRole'])->middleware(['throttle:sensitive', 'idempotency']);
        Route::patch('/permissions/custom-roles/{id}', [CrudController::class, 'updateCustomRole'])->whereNumber('id')->middleware(['throttle:sensitive', 'idempotency']);
        Route::delete('/permissions/custom-roles/{id}', [CrudController::class, 'deleteCustomRole'])->whereNumber('id')->middleware(['throttle:sensitive', 'idempotency']);
        Route::post('/permissions/custom-roles/{id}/assign', [CrudController::class, 'assignCustomRole'])->whereNumber('id')->middleware(['throttle:sensitive', 'idempotency']);
        Route::post('/{resource}/import/preview', [CrudController::class, 'importPreview'])->middleware('throttle:large-files');
        Route::post('/{resource}/export/queue', [CrudController::class, 'queueExport'])->middleware(['throttle:large-files', 'idempotency']);
        Route::get('/{resource}/export', [CrudController::class, 'export']);
        Route::post('/{resource}/import', [CrudController::class, 'import'])->middleware(['throttle:large-files', 'idempotency']);
        Route::post('/{resource}/bulk', [CrudController::class, 'bulk'])->middleware('idempotency');
        Route::get('/{resource}/analytics', [CrudController::class, 'analytics']);
        Route::get('/{resource}', [CrudController::class, 'index']);
        Route::post('/{resource}', [CrudController::class, 'store'])->middleware('idempotency');
        Route::get('/{resource}/{id}/timeline', [CrudController::class, 'timeline'])->whereNumber('id');
        Route::get('/{resource}/{id}', [CrudController::class, 'show'])->whereNumber('id');
        Route::put('/{resource}/{id}', [CrudController::class, 'update'])->whereNumber('id')->middleware('idempotency');
        Route::patch('/{resource}/{id}', [CrudController::class, 'update'])->whereNumber('id')->middleware('idempotency');
        Route::delete('/{resource}/{id}', [CrudController::class, 'destroy'])->whereNumber('id')->middleware('idempotency');
    });
    Route::get('/dashboard/{panel?}', [WebController::class, 'dashboard'])->name('dashboard');
    Route::get('/{panel}/module/{module}', [WebController::class, 'module'])->name('module');
});
