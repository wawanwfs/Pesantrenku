<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ActionBackupConcern;
use App\Http\Controllers\Concerns\ActionNotificationJobConcern;
use App\Http\Controllers\Concerns\ActionWorkflowConcern;
use App\Services\OperationStorage;
use App\Services\QrScannerService;
use App\Services\QueueMonitorService;
use App\Services\ResourceAuthorization;
use App\Services\RestoreApprovalService;
use App\Services\TenantBackupService;
use App\Services\TenantContext;
use App\Services\TotpService;

class ActionController extends Controller
{
    use ActionBackupConcern, ActionNotificationJobConcern, ActionWorkflowConcern;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly OperationStorage $operationStorage,
        private readonly QrScannerService $qrScanner,
        private readonly QueueMonitorService $queueMonitor,
        private readonly ResourceAuthorization $resourceAuthorization,
        private readonly TenantBackupService $tenantBackup,
        private readonly RestoreApprovalService $restoreApprovals,
        private readonly TotpService $totp,
    ) {}

    private array $workflowResources = [
        'permits' => 'permits',
        'payments' => 'payments',
        'medical-queues' => 'medical_queues',
    ];

    private array $workflowTransitions = [
        'permits' => [
            'approve' => ['from' => ['pending', 'waiting'], 'to' => 'approved'],
            'reject' => ['from' => ['pending', 'waiting'], 'to' => 'rejected'],
            'cancel' => ['from' => ['pending', 'approved'], 'to' => 'cancelled'],
        ],
        'payments' => [
            'verify' => ['from' => ['pending', 'waiting', 'paid'], 'to' => 'success'],
            'reject' => ['from' => ['pending', 'waiting', 'paid'], 'to' => 'rejected'],
        ],
        'medical-queues' => [
            'start' => ['from' => ['waiting', 'pending'], 'to' => 'in_progress'],
            'finish' => ['from' => ['in_progress'], 'to' => 'done'],
            'cancel' => ['from' => ['waiting', 'pending', 'in_progress'], 'to' => 'cancelled'],
        ],
    ];
}
