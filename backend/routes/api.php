<?php

use App\Http\Controllers\ActionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CrudController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MfaController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'authz.current'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/tenant/switch', [AuthController::class, 'switchTenant']);
        Route::post('/security/mfa/enroll', [MfaController::class, 'startEnrollment'])->middleware('throttle:sensitive');
        Route::post('/security/mfa/confirm', [MfaController::class, 'confirmEnrollment'])->middleware('throttle:sensitive');
        Route::post('/security/mfa/recovery-codes', [MfaController::class, 'regenerateRecoveryCodes'])->middleware(['throttle:sensitive', 'idempotency']);
        Route::delete('/security/mfa', [MfaController::class, 'disable'])->middleware(['throttle:sensitive', 'idempotency']);

        foreach ([
            'super-admin', 'owner', 'keuangan', 'akademik', 'tu', 'santri', 'wali-murid', 'musyrif', 'guru', 'klinik',
        ] as $dashboard) {
            Route::get("/dashboard/{$dashboard}", [DashboardController::class, 'show'])->defaults('role', $dashboard);
        }

        Route::post('/notifications/read', [ActionController::class, 'readNotifications'])->middleware('idempotency');
        Route::get('/notifications', [ActionController::class, 'notifications']);
        Route::get('/notifications/stream', [ActionController::class, 'notificationStream'])->middleware('throttle:notification-stream');
        Route::get('/jobs', [ActionController::class, 'jobs']);
        Route::get('/jobs/{uuid}', [ActionController::class, 'jobStatus']);
        Route::get('/jobs/{uuid}/download', [ActionController::class, 'downloadJob'])->middleware('throttle:large-files');
        Route::delete('/jobs/{uuid}', [ActionController::class, 'deleteJob'])->middleware(['throttle:sensitive', 'idempotency']);
        Route::get('/queue/monitor', [ActionController::class, 'queueMonitor']);
        Route::post('/queue/failed/{uuid}/retry', [ActionController::class, 'retryFailedJob'])->middleware(['throttle:sensitive', 'idempotency']);
        Route::delete('/queue/failed/{uuid}', [ActionController::class, 'forgetFailedJob'])->middleware(['throttle:sensitive', 'idempotency']);
        Route::post('/qr/scanner/register', [ActionController::class, 'registerQrScanner'])->middleware(['throttle:sensitive', 'idempotency']);
        Route::post('/qr/scan/challenge', [ActionController::class, 'qrScanChallenge'])->middleware(['throttle:operations', 'idempotency']);
        Route::post('/qr/scan', [ActionController::class, 'scanQr'])->middleware(['throttle:operations', 'idempotency']);
        Route::post('/qr/generate', [ActionController::class, 'generateQr'])->middleware(['throttle:operations', 'idempotency']);
        Route::post('/backup/run', [ActionController::class, 'runBackup'])->middleware(['throttle:sensitive', 'idempotency']);
        Route::get('/backup/restore-requests', [ActionController::class, 'restoreRequests']);
        Route::post('/backup/restore-requests/{id}/approve', [ActionController::class, 'approveRestore'])->whereNumber('id')->middleware(['throttle:sensitive', 'idempotency']);
        Route::post('/backup/restore-requests/{id}/execute', [ActionController::class, 'executeRestore'])->whereNumber('id')->middleware(['throttle:sensitive', 'idempotency']);
        Route::get('/backup/{id}/download', [ActionController::class, 'downloadBackup'])->whereNumber('id')->middleware('throttle:large-files');
        Route::post('/backup/{id}/restore', [ActionController::class, 'restoreBackup'])->whereNumber('id')->middleware(['throttle:sensitive', 'idempotency']);
        Route::post('/workflow/{resource}/{id}/{transition}', [ActionController::class, 'transition'])->whereNumber('id')->middleware(['throttle:operations', 'idempotency']);

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
});
