<?php

use App\Http\Controllers\ActionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CrudController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\WebController;
use App\Services\OperationStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;

Route::get('/', function (Request $request) {
    if (! $request->user()) {
        return Inertia::render('Landing');
    }

    $role = $request->user()->loadMissing('roles')->role_names[0] ?? 'super_admin';
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
    $storageStatus = Cache::remember("health:storage:{$operationDisk}", 60, function () use ($operationStorage) {
        try {
            $path = '.health/'.Str::uuid().'.txt';
            if (! $operationStorage->put($path, 'ok')) {
                return 'readonly';
            }
            $operationStorage->delete($path);

            return 'ok';
        } catch (Throwable) {
            return 'error';
        }
    });

    $health = [
        'status' => 'ok',
        'app' => config('app.name'),
        'database' => DB::select('select 1 as ok')[0]->ok === 1 ? 'ok' : 'error',
        'queue_table' => Schema::hasTable('jobs') ? 'ok' : 'missing',
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

    return response()->json($health);
});
Route::get('/metrics', MetricsController::class)->middleware('throttle:60,1');

Route::middleware('auth')->group(function () {
    Route::post('/tenant/switch', [AuthController::class, 'switchTenant'])->name('tenant.switch');
    Route::prefix('web-actions')->group(function () {
        Route::get('/notifications', [ActionController::class, 'notifications']);
        Route::get('/notifications/stream', [ActionController::class, 'notificationStream'])->middleware('throttle:notification-stream');
        Route::post('/notifications/read', [ActionController::class, 'readNotifications']);
        Route::get('/jobs', [ActionController::class, 'jobs']);
        Route::get('/jobs/{uuid}', [ActionController::class, 'jobStatus']);
        Route::get('/jobs/{uuid}/download', [ActionController::class, 'downloadJob'])->middleware('throttle:large-files');
        Route::delete('/jobs/{uuid}', [ActionController::class, 'deleteJob'])->middleware('throttle:sensitive');
        Route::get('/queue/monitor', [ActionController::class, 'queueMonitor']);
        Route::post('/queue/failed/{uuid}/retry', [ActionController::class, 'retryFailedJob'])->middleware('throttle:sensitive');
        Route::delete('/queue/failed/{uuid}', [ActionController::class, 'forgetFailedJob'])->middleware('throttle:sensitive');
        Route::post('/qr/scan', [ActionController::class, 'scanQr']);
        Route::get('/qr/generate', [ActionController::class, 'generateQr']);
        Route::post('/backup/run', [ActionController::class, 'runBackup'])->middleware('throttle:sensitive');
        Route::get('/backup/restore-requests', [ActionController::class, 'restoreRequests']);
        Route::post('/backup/restore-requests/{id}/approve', [ActionController::class, 'approveRestore'])->whereNumber('id')->middleware(['throttle:sensitive', 'idempotency']);
        Route::post('/backup/restore-requests/{id}/execute', [ActionController::class, 'executeRestore'])->whereNumber('id')->middleware(['throttle:sensitive', 'idempotency']);
        Route::get('/backup/{id}/download', [ActionController::class, 'downloadBackup'])->whereNumber('id')->middleware('throttle:large-files');
        Route::post('/backup/{id}/restore', [ActionController::class, 'restoreBackup'])->whereNumber('id')->middleware(['throttle:sensitive', 'idempotency']);
        Route::post('/workflow/{resource}/{id}/{transition}', [ActionController::class, 'transition'])->whereNumber('id')->middleware(['throttle:operations', 'idempotency']);
    });
    Route::prefix('web-api')->group(function () {
        Route::get('/permissions/matrix', [CrudController::class, 'permissionMatrix']);
        Route::patch('/permissions/matrix', [CrudController::class, 'updatePermissionMatrix'])->middleware('throttle:sensitive');
        Route::post('/permissions/matrix', [CrudController::class, 'updatePermissionMatrix'])->middleware('throttle:sensitive');
        Route::get('/permissions/custom-roles', [CrudController::class, 'customRoles']);
        Route::post('/permissions/custom-roles', [CrudController::class, 'storeCustomRole'])->middleware('throttle:sensitive');
        Route::patch('/permissions/custom-roles/{id}', [CrudController::class, 'updateCustomRole'])->whereNumber('id')->middleware('throttle:sensitive');
        Route::delete('/permissions/custom-roles/{id}', [CrudController::class, 'deleteCustomRole'])->whereNumber('id')->middleware('throttle:sensitive');
        Route::post('/permissions/custom-roles/{id}/assign', [CrudController::class, 'assignCustomRole'])->whereNumber('id')->middleware('throttle:sensitive');
        Route::post('/{resource}/import/preview', [CrudController::class, 'importPreview'])->middleware('throttle:large-files');
        Route::post('/{resource}/export/queue', [CrudController::class, 'queueExport'])->middleware('throttle:large-files');
        Route::get('/{resource}/export', [CrudController::class, 'export']);
        Route::post('/{resource}/import', [CrudController::class, 'import'])->middleware('throttle:large-files');
        Route::post('/{resource}/bulk', [CrudController::class, 'bulk']);
        Route::get('/{resource}/analytics', [CrudController::class, 'analytics']);
        Route::get('/{resource}', [CrudController::class, 'index']);
        Route::post('/{resource}', [CrudController::class, 'store'])->middleware('idempotency');
        Route::get('/{resource}/{id}/timeline', [CrudController::class, 'timeline'])->whereNumber('id');
        Route::get('/{resource}/{id}', [CrudController::class, 'show'])->whereNumber('id');
        Route::put('/{resource}/{id}', [CrudController::class, 'update'])->whereNumber('id')->middleware('idempotency');
        Route::patch('/{resource}/{id}', [CrudController::class, 'update'])->whereNumber('id')->middleware('idempotency');
        Route::delete('/{resource}/{id}', [CrudController::class, 'destroy'])->whereNumber('id');
    });
    Route::get('/dashboard/{panel?}', [WebController::class, 'dashboard'])->name('dashboard');
    Route::get('/{panel}/module/{module}', [WebController::class, 'module'])->name('module');
});
