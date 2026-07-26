<?php

namespace App\Http\Controllers\Concerns;

use App\Services\EntitlementService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

trait CrudPayloadConcern
{
    private function payload(Request $request, string $table, bool $includeCreatedAt = true): array
    {
        $rules = $this->validationRules($request, $table, $request->isMethod('post'));
        $submittedFields = array_keys($request->except(['_token', '_method', 'pesantren_id', 'mfa_code']));
        $unknownFields = array_values(array_diff($submittedFields, array_keys($rules)));
        if ($unknownFields !== []) {
            throw new HttpResponseException(response()->json([
                'message' => 'Payload memuat field yang tidak didukung.',
                'errors' => collect($unknownFields)->mapWithKeys(fn (string $field) => [
                    $field => ['Field ini tidak dapat ditulis melalui endpoint tersebut.'],
                ]),
            ], 422));
        }

        $validator = Validator::make(
            $request->all(),
            $rules,
            [],
            $this->validationAttributes()
        );

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'message' => 'Data tidak valid. Periksa kembali field yang ditandai.',
                'errors' => $validator->errors(),
            ], 422));
        }

        $validated = $validator->validated();
        abort_if($validated === [], 422, 'Tidak ada field yang dapat diproses.');

        return $this->preparePayload($request, $table, $validated, $includeCreatedAt);
    }

    private function preparePayload(Request $request, string $table, array $raw, bool $includeCreatedAt = true): array
    {
        $data = collect($raw)
            ->only($this->columns($table))
            ->merge(['updated_at' => now()])
            ->when($includeCreatedAt && $this->hasColumn($table, 'created_at'), fn ($data) => $data->merge(['created_at' => $data->get('created_at', now())]));

        if ($table === 'users') {
            $data->forget(['remember_token', 'mfa_secret']);
            if ($data->has('password') && filled($data->get('password'))) {
                $data->put('password', Hash::make((string) $data->get('password')));
            } else {
                $data->forget('password');
            }
        }

        foreach (['metadata', 'profile', 'settings', 'features', 'items'] as $jsonColumn) {
            if ($data->has($jsonColumn) && is_array($data->get($jsonColumn))) {
                $data->put($jsonColumn, json_encode($data->get($jsonColumn), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            }
        }

        if ($table === 'system_settings' && $data->has('value') && filled($data->get('value')) && json_decode((string) $data->get('value'), true) === null) {
            $data->put('value', json_encode(['body' => (string) $data->get('value')], JSON_UNESCAPED_UNICODE));
        }

        return $data->all();
    }

    private function validationRules(Request $request, string $table, bool $creating): array
    {
        $required = fn (string $rule = 'string') => [$creating ? 'required' : 'sometimes', $rule];
        $nullableString = ['nullable', 'string', 'max:255'];
        $nullableText = ['nullable', 'string', 'max:5000'];
        $nullableDate = ['nullable', 'date'];
        $nullableInteger = ['nullable', 'integer'];
        $nullableNumber = ['nullable', 'numeric'];
        $status = match ($table) {
            'permits' => $creating ? ['nullable', Rule::in(['pending', 'waiting'])] : ['prohibited'],
            'payments' => $creating ? ['nullable', Rule::in(['pending', 'waiting'])] : ['prohibited'],
            'medical_queues' => $creating ? ['nullable', Rule::in(['pending', 'waiting'])] : ['prohibited'],
            default => ['nullable', 'string', 'max:60'],
        };

        $rules = match ($table) {
            'users' => [
                'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
                'email' => [$creating ? 'required' : 'sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($request->route('id'))],
                'username' => ['nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($request->route('id'))],
                'phone' => ['nullable', 'string', 'max:255', Rule::unique('users', 'phone')->ignore($request->route('id'))],
                'password' => [$creating ? 'required' : 'nullable', 'string', 'min:12', 'max:255'],
                'status' => ['nullable', Rule::in(['active', 'inactive'])],
            ],
            'pesantrens' => [
                'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
                'city' => $nullableString,
                'address' => $nullableText,
                'phone' => $nullableString,
                'email' => ['nullable', 'email', 'max:255'],
                'logo' => $nullableString,
                'status' => ['nullable', Rule::in(['active', 'inactive'])],
                'metadata' => ['nullable', 'array'],
            ],
            'pesantren_user' => [
                'user_id' => $required('integer'),
                'role_id' => $required('integer'),
                'tenant_role_id' => $nullableInteger,
                'status' => ['nullable', Rule::in(['active', 'inactive'])],
                'joined_at' => $nullableDate,
            ],
            'tenant_modules' => [
                'module' => [$creating ? 'required' : 'sometimes', Rule::in(EntitlementService::moduleAllowlist())],
                'is_enabled' => ['sometimes', 'boolean'],
                'settings' => ['nullable', 'array'],
            ],
            'academic_years' => [
                'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
                'start_date' => $nullableDate,
                'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'students' => [
                'nis' => $required('string'),
                'name' => $required('string'),
                'class_id' => $nullableInteger,
                'guardian_id' => $nullableInteger,
                'gender' => $nullableString,
                'birth_date' => $nullableDate,
                'room' => $nullableString,
                'status' => $status,
            ],
            'teachers', 'guardians', 'staff', 'rooms', 'payment_methods', 'expense_categories', 'doctors', 'clinics', 'medicines', 'plans' => [
                'name' => $required('string'),
                'status' => $status,
            ],
            'programs', 'subjects' => [
                'name' => $required('string'),
                'code' => $required('string'),
                'status' => $status,
            ],
            'classes' => [
                'name' => $required('string'),
                'program_id' => $nullableInteger,
                'academic_year_id' => $nullableInteger,
                'student_count' => $nullableInteger,
                'status' => $status,
            ],
            'teacher_assignments' => [
                'teacher_id' => $required('integer'),
                'class_id' => $required('integer'),
                'subject_id' => $required('integer'),
                'assigned_at' => $nullableDate,
                'ended_at' => $nullableDate,
                'status' => $status,
                'note' => $nullableText,
            ],
            'musyrif_room_assignments' => [
                'user_id' => $required('integer'),
                'room_id' => $required('integer'),
                'assigned_at' => $nullableDate,
                'ended_at' => $nullableDate,
                'status' => $status,
                'note' => $nullableText,
            ],
            'room_assignments' => [
                'student_id' => $required('integer'),
                'room_id' => $required('integer'),
                'assigned_at' => $nullableDate,
                'ended_at' => $nullableDate,
                'status' => $status,
            ],
            'permits' => [
                'student_id' => $required('integer'),
                'type' => $required('string'),
                'start_at' => $nullableDate,
                'end_at' => $nullableDate,
                'reason' => $nullableText,
                'status' => $status,
            ],
            'payments' => [
                'invoice_id' => $nullableInteger,
                'student_id' => $required('integer'),
                'payment_method_id' => $nullableInteger,
                'amount' => [$creating ? 'required' : 'sometimes', 'numeric', 'min:0.01'],
                'paid_at' => $nullableDate,
                'status' => $status,
                'reference' => $nullableString,
            ],
            'invoices' => [
                'student_id' => $required('integer'),
                'invoice_number' => $required('string'),
                'period' => $required('string'),
                'amount' => [$creating ? 'required' : 'sometimes', 'numeric', 'min:0.01'],
                'due_date' => $nullableDate,
                'status' => $status,
            ],
            'attendances' => [
                'student_id' => $required('integer'),
                'class_id' => $nullableInteger,
                'date' => [$creating ? 'required' : 'sometimes', 'date'],
                'session' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
                'status' => $required('string'),
                'note' => $nullableText,
            ],
            'dorm_attendances' => [
                'student_id' => $required('integer'),
                'room_id' => $required('integer'),
                'date' => [$creating ? 'required' : 'sometimes', 'date'],
                'activity' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
                'status' => $required('string'),
            ],
            'grades' => [
                'student_id' => $required('integer'),
                'class_id' => $required('integer'),
                'subject_id' => $required('integer'),
                'semester' => $required('string'),
                'assignment_score' => ['nullable', 'numeric', 'between:0,100'],
                'midterm_score' => ['nullable', 'numeric', 'between:0,100'],
                'final_score' => ['nullable', 'numeric', 'between:0,100'],
                'attitude_score' => ['nullable', 'numeric', 'between:0,100'],
                'average_score' => ['nullable', 'numeric', 'between:0,100'],
                'grade' => $nullableString,
            ],
            'report_cards' => [
                'student_id' => $required('integer'),
                'semester' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
                'average_score' => ['nullable', 'numeric', 'between:0,100'],
                'status' => ['nullable', Rule::in(['draft', 'published', 'archived'])],
                'items' => ['nullable', 'array'],
            ],
            'memorization_records' => [
                'student_id' => $required('integer'),
                'juz' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
                'surah' => $nullableString,
                'ayat_range' => $nullableString,
                'progress_percent' => ['nullable', 'numeric', 'between:0,100'],
                'recorded_at' => $nullableDate,
            ],
            'schedules' => [
                'class_id' => $nullableInteger,
                'subject_id' => $nullableInteger,
                'day' => [$creating ? 'required' : 'sometimes', 'string', 'max:30'],
                'time' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
                'teacher_name' => $nullableString,
                'room_name' => $nullableString,
                'type' => ['nullable', 'string', 'max:60'],
            ],
            'assignments', 'materials' => [
                'class_id' => $required('integer'),
                'subject_id' => $required('integer'),
                'title' => $required('string'),
                'due_date' => $nullableDate,
                'status' => $status,
            ],
            'announcements' => [
                'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
                'body' => [$creating ? 'required' : 'sometimes', 'string', 'max:20000'],
                'category' => ['nullable', 'string', 'max:100'],
                'audience' => ['nullable', 'string', 'max:100'],
                'published_at' => $nullableDate,
            ],
            'violations' => [
                'student_id' => $required('integer'),
                'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
                'points' => ['nullable', 'integer', 'min:0', 'max:100000'],
                'category' => ['nullable', 'string', 'max:100'],
                'date' => $nullableDate,
                'note' => $nullableText,
            ],
            'daily_notes' => [
                'student_id' => $required('integer'),
                'noted_at' => $nullableDate,
                'note' => [$creating ? 'required' : 'sometimes', 'string', 'max:5000'],
            ],
            'invoice_items' => [
                'invoice_id' => $required('integer'),
                'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
                'amount' => [$creating ? 'required' : 'sometimes', 'numeric', 'min:0.01', 'max:9999999999999999.99'],
            ],
            'bank_accounts' => [
                'bank_name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
                'account_number' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
                'account_holder' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
                'balance' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99'],
                'is_primary' => ['sometimes', 'boolean'],
            ],
            'expenses' => [
                'expense_category_id' => $nullableInteger,
                'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
                'amount' => [$creating ? 'required' : 'sometimes', 'numeric', 'min:0.01', 'max:9999999999999999.99'],
                'date' => $nullableDate,
                'note' => $nullableText,
            ],
            'cashflow_entries' => [
                'type' => [$creating ? 'required' : 'sometimes', Rule::in(['pemasukan', 'pengeluaran', 'income', 'expense'])],
                'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
                'amount' => [$creating ? 'required' : 'sometimes', 'numeric', 'min:0.01', 'max:9999999999999999.99'],
                'date' => $nullableDate,
            ],
            'discounts' => [
                'student_id' => $nullableInteger,
                'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
                'amount' => [$creating ? 'required' : 'sometimes', 'numeric', 'min:0.01', 'max:9999999999999999.99'],
                'status' => ['nullable', Rule::in(['active', 'inactive'])],
            ],
            'medical_queues' => [
                'student_id' => $required('integer'),
                'queue_number' => $required('string'),
                'status' => $status,
                'estimated_time' => ['nullable'],
            ],
            'medical_records' => [
                'student_id' => $required('integer'),
                'complaint' => $required('string'),
                'diagnosis' => $nullableString,
                'date' => $nullableDate,
                'doctor_name' => $nullableString,
            ],
            'medical_examinations' => [
                'medical_record_id' => $required('integer'),
                'temperature' => $nullableNumber,
                'blood_pressure' => $nullableString,
                'pulse' => $nullableInteger,
                'doctor_note' => $nullableText,
            ],
            'medicine_stocks' => [
                'medicine_id' => $required('integer'),
                'quantity' => [$creating ? 'required' : 'sometimes', 'integer', 'min:1', 'max:1000000000'],
                'type' => [$creating ? 'required' : 'sometimes', Rule::in(['in', 'out', 'adjustment'])],
                'date' => $nullableDate,
            ],
            'prescriptions' => [
                'medical_record_id' => $required('integer'),
                'medicine_id' => $required('integer'),
                'dosage' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
                'quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            ],
            'letters' => [
                'number' => $required('string'),
                'subject' => $required('string'),
                'type' => $required('string'),
                'destination' => $nullableString,
                'date' => $nullableDate,
                'status' => $status,
            ],
            'room_mutations' => [
                'student_id' => $required('integer'),
                'from_room' => $nullableString,
                'to_room' => $required('string'),
                'date' => $nullableDate,
                'status' => $status,
            ],
            'alumni' => [
                'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
                'graduation_year' => [$creating ? 'required' : 'sometimes', 'string', 'max:20'],
                'program' => $nullableString,
                'occupation' => $nullableString,
                'phone' => $nullableString,
            ],
            'sick_letters' => [
                'student_id' => $required('integer'),
                'number' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
                'date' => $nullableDate,
                'duration_days' => ['nullable', 'integer', 'min:1', 'max:365'],
                'description' => $nullableText,
            ],
            'referrals' => [
                'student_id' => $required('integer'),
                'hospital_name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
                'phone' => $nullableString,
                'address' => $nullableText,
            ],
            'system_settings' => [
                'key' => $required('string'),
                'value' => ['nullable'],
            ],
            default => [],
        };

        if (! $rules) {
            foreach ($this->columnDefinitions($table) as $column) {
                $name = (string) ($column['name'] ?? '');
                if ($name === ''
                    || in_array($name, ['id', 'pesantren_id', 'created_at', 'updated_at', 'deleted_at'], true)
                    || ($column['auto_increment'] ?? false)) {
                    continue;
                }

                $type = strtolower((string) ($column['type_name'] ?? $column['type'] ?? 'string'));
                $typeRule = str_contains($type, 'int')
                    ? 'integer'
                    : (preg_match('/decimal|numeric|float|double|real/', $type) ? 'numeric' : (str_contains($type, 'date') || str_contains($type, 'time') ? 'date' : 'string'));
                $requiredOnCreate = $creating
                    && ! ($column['nullable'] ?? false)
                    && ($column['default'] ?? null) === null;
                $presenceRule = $requiredOnCreate ? 'required' : ($creating ? 'nullable' : 'sometimes');
                $rules[$name] = [$presenceRule, $typeRule];
                if ($typeRule === 'string') {
                    $rules[$name][] = 'max:5000';
                }
            }
        }

        return $rules;
    }

    private function validationAttributes(): array
    {
        return [
            'student_id' => 'santri',
            'class_id' => 'kelas',
            'subject_id' => 'mata pelajaran',
            'teacher_id' => 'guru',
            'room_id' => 'kamar',
            'invoice_id' => 'tagihan',
            'payment_method_id' => 'metode pembayaran',
            'medical_record_id' => 'rekam medis',
            'queue_number' => 'nomor antrian',
            'invoice_number' => 'nomor tagihan',
        ];
    }

    private function invalidateContextCache(string $table, ?object $before, ?object $after): void
    {
        if ($table === 'pesantren_user') {
            $this->tenantContext->invalidateMemberships(
                (int) ($after?->user_id ?? $before?->user_id ?? 0)
            );
        }

        if ($table === 'pesantrens') {
            $this->tenantContext->invalidatePesantren(
                (int) ($after?->id ?? $before?->id ?? 0)
            );
        }
    }

    private function columns(string $table): array
    {
        return $this->schemaColumns[$table] ??= Schema::getColumnListing($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->columns($table), true);
    }

    private function hasTable(string $table): bool
    {
        return $this->schemaTables[$table] ??= Schema::hasTable($table);
    }

    private function columnDefinitions(string $table): array
    {
        return $this->schemaDefinitions[$table] ??= Schema::getColumns($table);
    }

    private function requestMemo(Request $request, string $key, callable $resolver): mixed
    {
        $attribute = "pesantenku.crud.{$key}";
        if (! $request->attributes->has($attribute)) {
            $request->attributes->set($attribute, $resolver());
        }

        return $request->attributes->get($attribute);
    }
}
