<?php

namespace App\Support;

final class TenantBackupManifest
{
    public const VERSION = 4;

    public const PROTECTED_SETTING_PREFIXES = ['permissions.', 'security.', 'auth.', 'backup-security.'];

    /**
     * Dependency-safe insertion order. Restore deletes these tables in reverse
     * order so foreign-key enforcement can remain enabled for the whole job.
     */
    public const TABLES = [
        // Billing/subscription and module entitlements belong to the platform
        // control plane and must survive an application-level tenant restore.
        'academic_years',
        'programs',
        'subjects',
        'guardians',
        'teachers',
        'staff',
        'classes',
        'students',
        'rooms',
        'teacher_assignments',
        'room_assignments',
        'musyrif_room_assignments',
        'attendances',
        'dorm_attendances',
        'grades',
        'report_cards',
        'memorization_records',
        'schedules',
        'assignments',
        'materials',
        'announcements',
        'violations',
        'permits',
        'daily_notes',
        'payment_methods',
        'bank_accounts',
        'invoices',
        'invoice_items',
        'payments',
        'expense_categories',
        'expenses',
        'cashflow_entries',
        'discounts',
        'letters',
        'room_mutations',
        'alumni',
        'clinics',
        'doctors',
        'medicines',
        'medicine_stocks',
        'medical_queues',
        'medical_records',
        'medical_examinations',
        'prescriptions',
        'sick_letters',
        'referrals',
        'system_settings',
    ];

    public static function isProtectedSetting(string $key): bool
    {
        foreach (self::PROTECTED_SETTING_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function checksum(array $rows): string
    {
        return hash('sha256', json_encode(
            $rows,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        ));
    }
}
