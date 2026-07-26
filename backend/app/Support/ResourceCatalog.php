<?php

namespace App\Support;

final class ResourceCatalog
{
    /** Every resource exposed by the generic resource API. */
    public const ALL = [
        'users', 'roles', 'pesantrens', 'plans', 'subscriptions', 'tenant-modules', 'memberships',
        'academic-years', 'classes', 'programs', 'subjects', 'teacher-assignments', 'students', 'guardians',
        'teachers', 'staff', 'rooms', 'room-assignments', 'musyrif-room-assignments', 'attendances', 'dorm-attendances', 'grades',
        'report-cards', 'memorization-records', 'schedules', 'assignments', 'materials', 'announcements',
        'violations', 'permits', 'daily-notes', 'invoices', 'invoice-items', 'payments', 'payment-methods',
        'bank-accounts', 'expense-categories', 'expenses', 'cashflow-entries', 'discounts', 'letters',
        'room-mutations', 'alumni', 'clinics', 'doctors', 'medicines', 'medicine-stocks', 'medical-queues',
        'medical-records', 'medical-examinations', 'prescriptions', 'sick-letters', 'referrals',
        'notifications', 'audit-logs', 'system-settings', 'backups',
    ];

    private const TABLE_RESOURCE_ALIASES = [
        'pesantren_user' => 'memberships',
        'tenant_modules' => 'tenant-modules',
        'academic_years' => 'academic-years',
        'teacher_assignments' => 'teacher-assignments',
        'room_assignments' => 'room-assignments',
        'musyrif_room_assignments' => 'musyrif-room-assignments',
        'dorm_attendances' => 'dorm-attendances',
        'report_cards' => 'report-cards',
        'memorization_records' => 'memorization-records',
        'daily_notes' => 'daily-notes',
        'invoice_items' => 'invoice-items',
        'payment_methods' => 'payment-methods',
        'bank_accounts' => 'bank-accounts',
        'expense_categories' => 'expense-categories',
        'cashflow_entries' => 'cashflow-entries',
        'room_mutations' => 'room-mutations',
        'medicine_stocks' => 'medicine-stocks',
        'medical_queues' => 'medical-queues',
        'medical_records' => 'medical-records',
        'medical_examinations' => 'medical-examinations',
        'sick_letters' => 'sick-letters',
        'notification_logs' => 'notifications',
        'audit_logs' => 'audit-logs',
        'system_settings' => 'system-settings',
        'backups' => 'backups',
    ];

    /**
     * Built-in access is the safe fallback when the permission tables have not
     * been provisioned yet. Database permissions and tenant overrides may only
     * reduce or delegate within these bounded resources.
     */
    public const DEFAULT_ROLE_ACCESS = [
        'owner_pesantren' => [
            'read' => ['pesantrens', 'memberships', 'tenant-modules', 'subscriptions', 'plans', 'audit-logs', 'system-settings', 'academic-years', 'classes', 'programs', 'subjects', 'teacher-assignments', 'students', 'guardians', 'teachers', 'staff', 'rooms', 'room-assignments', 'musyrif-room-assignments', 'attendances', 'dorm-attendances', 'grades', 'report-cards', 'memorization-records', 'schedules', 'assignments', 'materials', 'announcements', 'violations', 'permits', 'daily-notes', 'invoices', 'invoice-items', 'payments', 'payment-methods', 'bank-accounts', 'expense-categories', 'expenses', 'cashflow-entries', 'discounts', 'letters', 'room-mutations', 'alumni', 'clinics', 'doctors', 'medicines', 'medicine-stocks', 'medical-queues', 'medical-records', 'medical-examinations', 'prescriptions', 'sick-letters', 'referrals', 'notifications', 'backups'],
            'write' => ['pesantrens', 'memberships', 'teacher-assignments', 'musyrif-room-assignments', 'permits', 'payments', 'medical-queues', 'system-settings'],
        ],
        'admin_keuangan' => [
            'read' => ['students', 'guardians', 'programs', 'invoice-items', 'bank-accounts', 'academic-years', 'invoices', 'payments', 'payment-methods', 'discounts', 'cashflow-entries', 'expenses', 'system-settings', 'memberships'],
            'write' => ['students', 'guardians', 'programs', 'invoice-items', 'bank-accounts', 'academic-years', 'invoices', 'payments', 'payment-methods', 'discounts', 'cashflow-entries', 'expenses', 'system-settings'],
        ],
        'admin_akademik' => [
            'read' => ['academic-years', 'programs', 'subjects', 'classes', 'teachers', 'teacher-assignments', 'schedules', 'attendances', 'grades', 'report-cards', 'students', 'announcements', 'system-settings'],
            'write' => ['academic-years', 'programs', 'subjects', 'classes', 'teachers', 'teacher-assignments', 'schedules', 'attendances', 'grades', 'report-cards', 'students', 'announcements', 'system-settings'],
        ],
        'admin_tu' => [
            'read' => ['students', 'guardians', 'teachers', 'staff', 'classes', 'rooms', 'room-assignments', 'musyrif-room-assignments', 'programs', 'letters', 'room-mutations', 'alumni', 'announcements', 'system-settings'],
            'write' => ['students', 'guardians', 'teachers', 'staff', 'classes', 'rooms', 'room-assignments', 'musyrif-room-assignments', 'programs', 'letters', 'room-mutations', 'alumni', 'announcements', 'system-settings'],
        ],
        'santri' => [
            'read' => ['students', 'grades', 'report-cards', 'memorization-records', 'attendances', 'permits', 'violations', 'medical-records', 'invoices', 'payments', 'announcements', 'notifications', 'schedules'],
            'write' => ['permits', 'payments'],
        ],
        'wali_murid' => [
            'read' => ['students', 'grades', 'report-cards', 'attendances', 'permits', 'violations', 'medical-records', 'invoices', 'payments', 'announcements', 'notifications', 'schedules'],
            'write' => ['permits', 'payments'],
        ],
        'guru' => [
            'read' => ['students', 'classes', 'subjects', 'teacher-assignments', 'schedules', 'attendances', 'grades', 'assignments', 'materials', 'report-cards', 'daily-notes', 'announcements'],
            'write' => ['attendances', 'grades', 'assignments', 'materials', 'daily-notes'],
        ],
        'musyrif' => [
            'read' => ['students', 'rooms', 'musyrif-room-assignments', 'dorm-attendances', 'permits', 'violations', 'daily-notes', 'room-mutations', 'schedules', 'announcements'],
            'write' => ['dorm-attendances', 'permits', 'violations', 'daily-notes', 'room-mutations'],
        ],
        'klinik' => [
            'read' => ['students', 'medical-queues', 'medical-records', 'medical-examinations', 'medicines', 'medicine-stocks', 'prescriptions', 'sick-letters', 'referrals', 'doctors', 'announcements'],
            'write' => ['medical-queues', 'medical-records', 'medical-examinations', 'medicines', 'medicine-stocks', 'prescriptions', 'sick-letters', 'referrals', 'doctors'],
        ],
    ];

    /**
     * Resources that are visible through the generic query layer but whose
     * lifecycle is owned by a dedicated platform/service workflow.
     */
    public const SERVICE_MANAGED = [
        'roles',
        'plans',
        'subscriptions',
        'notifications',
        'audit-logs',
        'backups',
    ];

    /**
     * Resources that must never be delegated by a tenant permission override
     * or custom tenant role. They either span the platform, control tenant
     * boundaries, or expose security/compliance state.
     */
    public const NON_DELEGABLE = [
        'roles',
        'pesantrens',
        'plans',
        'subscriptions',
        'tenant-modules',
        'memberships',
        'notifications',
        'audit-logs',
        'system-settings',
        'backups',
    ];

    /** Resources whose status is controlled by ActionController's state machine. */
    public const WORKFLOW = [
        'permits',
        'payments',
        'medical-queues',
    ];

    /** Default action-level workflow abilities for built-in tenant roles. */
    public const WORKFLOW_ACTION_ROLES = [
        'permits.approve' => ['musyrif', 'admin_tu', 'owner_pesantren'],
        'permits.reject' => ['musyrif', 'admin_tu', 'owner_pesantren'],
        'permits.cancel' => ['santri', 'wali_murid', 'musyrif', 'admin_tu', 'owner_pesantren'],
        'payments.verify' => ['admin_keuangan', 'owner_pesantren'],
        'payments.reject' => ['admin_keuangan', 'owner_pesantren'],
        'medical-queues.start' => ['klinik'],
        'medical-queues.finish' => ['klinik'],
        'medical-queues.cancel' => ['klinik'],
    ];

    public static function delegable(array $allowed = self::ALL): array
    {
        return array_values(array_diff($allowed, self::NON_DELEGABLE));
    }

    public static function resourceForTable(string $table): ?string
    {
        $resource = self::TABLE_RESOURCE_ALIASES[$table] ?? str_replace('_', '-', $table);

        return in_array($resource, self::ALL, true) ? $resource : null;
    }

    public static function defaultAccess(string $role): array
    {
        $access = self::DEFAULT_ROLE_ACCESS[$role] ?? ['read' => [], 'write' => []];
        $access['actions'] = self::defaultWorkflowAbilities($role);

        return $access;
    }

    public static function workflowAbilities(): array
    {
        return array_keys(self::WORKFLOW_ACTION_ROLES);
    }

    public static function defaultWorkflowAbilities(string $role): array
    {
        return array_keys(array_filter(
            self::WORKFLOW_ACTION_ROLES,
            fn (array $roles) => in_array($role, $roles, true),
        ));
    }

    public static function workflowMatrix(): array
    {
        $matrix = [];
        foreach (self::WORKFLOW_ACTION_ROLES as $ability => $roles) {
            [$resource, $action] = explode('.', $ability, 2);
            $matrix[$resource][$action] = $roles;
        }

        return $matrix;
    }

    public static function isServiceManaged(string $resource): bool
    {
        return in_array($resource, self::SERVICE_MANAGED, true);
    }

    public static function isWorkflow(string $resource): bool
    {
        return in_array($resource, self::WORKFLOW, true);
    }
}
