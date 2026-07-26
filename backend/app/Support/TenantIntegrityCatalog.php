<?php

namespace App\Support;

final class TenantIntegrityCatalog
{
    /** Tables whose rows can never exist outside exactly one tenant. */
    public const TABLES = [
        'subscriptions', 'tenant_modules', 'pesantren_user', 'academic_years', 'programs',
        'classes', 'subjects', 'guardians', 'teachers', 'staff', 'students', 'rooms',
        'room_assignments', 'attendances', 'dorm_attendances', 'grades', 'report_cards',
        'memorization_records', 'schedules', 'assignments', 'materials', 'announcements',
        'violations', 'permits', 'daily_notes', 'payment_methods', 'bank_accounts',
        'invoices', 'invoice_items', 'payments', 'expense_categories', 'expenses',
        'cashflow_entries', 'discounts', 'letters', 'room_mutations', 'alumni', 'clinics',
        'doctors', 'medicines', 'medicine_stocks', 'medical_queues', 'medical_records',
        'medical_examinations', 'prescriptions', 'sick_letters', 'referrals', 'qr_tokens',
        'teacher_assignments', 'musyrif_room_assignments', 'tenant_roles',
        'backup_restore_requests', 'qr_scanner_devices', 'qr_scan_challenges',
    ];

    public static function contains(string $table): bool
    {
        return in_array($table, self::TABLES, true);
    }

    /** @return array<int, array{child:string,column:string,parent:string}> */
    public static function tenantRelations(): array
    {
        return collect(DomainRelations::all())
            ->filter(fn (array $relation) => $relation[1] !== 'pesantren_id')
            ->filter(fn (array $relation) => self::contains($relation[0]) && self::contains($relation[2]))
            ->map(fn (array $relation) => [
                'child' => $relation[0],
                'column' => $relation[1],
                'parent' => $relation[2],
            ])
            ->values()
            ->all();
    }
}
