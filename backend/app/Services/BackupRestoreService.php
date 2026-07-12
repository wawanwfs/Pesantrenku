<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackupRestoreService
{
    public function __construct(
        private readonly OperationStorage $storage,
        private readonly BackupCipher $cipher,
    ) {}

    public function restore(object $backup): int
    {
        if (! $backup->file_path || ! $this->storage->exists($backup->file_path)) {
            throw new \RuntimeException('File backup tidak ditemukan.');
        }
        $snapshot = json_decode($this->cipher->decrypt($this->storage->get($backup->file_path)), true);
        if (! is_array($snapshot) || (int) ($snapshot['tenant_id'] ?? 0) !== (int) $backup->pesantren_id) {
            throw new \RuntimeException('Snapshot backup tidak cocok dengan tenant aktif.');
        }

        return DB::transaction(function () use ($snapshot, $backup) {
            $count = 0;
            Schema::disableForeignKeyConstraints();
            try {
                foreach ($this->tables() as $table) {
                    if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'pesantren_id')) {
                        continue;
                    }
                    $columns = Schema::getColumnListing($table);
                    foreach ($snapshot['tables'][$table] ?? [] as $row) {
                        $data = collect((array) $row)->only($columns)->all();
                        if ((int) ($data['pesantren_id'] ?? 0) !== (int) $backup->pesantren_id || empty($data['id'])) {
                            continue;
                        }
                        $foreignTenant = DB::table($table)->where('id', $data['id'])->where('pesantren_id', '!=', $backup->pesantren_id)->exists();
                        if ($foreignTenant) {
                            throw new \RuntimeException("ID {$data['id']} pada {$table} dimiliki tenant lain.");
                        }
                        DB::table($table)->updateOrInsert(['id' => $data['id'], 'pesantren_id' => $backup->pesantren_id], $data);
                        $count++;
                    }
                }
            } finally {
                Schema::enableForeignKeyConstraints();
            }

            return $count;
        });
    }

    private function tables(): array
    {
        return [
            'subscriptions', 'tenant_modules', 'academic_years', 'programs', 'classes', 'subjects',
            'teacher_assignments', 'guardians', 'teachers', 'staff', 'students', 'rooms', 'room_assignments',
            'musyrif_room_assignments', 'attendances', 'dorm_attendances', 'grades', 'report_cards',
            'memorization_records', 'schedules', 'assignments', 'materials', 'announcements', 'violations',
            'permits', 'daily_notes', 'payment_methods', 'bank_accounts', 'invoices', 'invoice_items',
            'payments', 'expense_categories', 'expenses', 'cashflow_entries', 'discounts', 'letters',
            'room_mutations', 'alumni', 'clinics', 'doctors', 'medicines', 'medicine_stocks',
            'medical_queues', 'medical_records', 'medical_examinations', 'prescriptions', 'sick_letters',
            'referrals', 'system_settings', 'tenant_roles', 'pesantren_user',
        ];
    }
}
