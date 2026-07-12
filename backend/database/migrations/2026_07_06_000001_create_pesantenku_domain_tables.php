<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['role_id', 'user_id']);
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['permission_id', 'role_id']);
        });

        Schema::create('pesantrens', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('logo')->nullable();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->decimal('monthly_price', 18, 2)->default(0);
            $table->unsignedInteger('max_students')->default(0);
            $table->unsignedInteger('max_users')->default(0);
            $table->json('features')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->index();
            $table->foreignId('plan_id')->nullable()->index();
            $table->string('status')->default('trial');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('tenant_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->index();
            $table->string('module');
            $table->boolean('is_enabled')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['pesantren_id', 'module']);
        });

        Schema::create('pesantren_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['pesantren_id', 'user_id', 'role_id']);
        });

        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->string('name');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->string('name');
            $table->string('code');
            $table->string('level')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pesantren_id', 'code']);
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('program_id')->nullable();
            $table->foreignId('academic_year_id')->nullable();
            $table->string('name');
            $table->string('level')->nullable();
            $table->string('grade')->nullable();
            $table->string('homeroom_teacher')->nullable();
            $table->unsignedInteger('student_count')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->string('name');
            $table->string('code');
            $table->string('level')->nullable();
            $table->unsignedInteger('kkm')->default(75);
            $table->string('teacher_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pesantren_id', 'code']);
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('user_id')->nullable();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('user_id')->nullable();
            $table->string('name');
            $table->string('nip')->nullable();
            $table->string('subject')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('user_id')->nullable();
            $table->string('name');
            $table->string('nip')->nullable();
            $table->string('role')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('user_id')->nullable();
            $table->foreignId('guardian_id')->nullable();
            $table->foreignId('class_id')->nullable();
            $table->string('nis');
            $table->string('name');
            $table->string('gender')->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('room')->nullable();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pesantren_id', 'nis']);
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->string('name');
            $table->string('block')->nullable();
            $table->unsignedInteger('capacity')->default(0);
            $table->unsignedInteger('occupied')->default(0);
            $table->string('type')->default('Reguler');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('room_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('student_id')->nullable();
            $table->foreignId('room_id')->nullable();
            $table->date('assigned_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('student_id')->nullable();
            $table->foreignId('class_id')->nullable();
            $table->date('date');
            $table->string('session')->nullable();
            $table->string('status');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('dorm_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('student_id')->nullable();
            $table->foreignId('room_id')->nullable();
            $table->date('date');
            $table->string('activity');
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('student_id')->nullable();
            $table->foreignId('class_id')->nullable();
            $table->foreignId('subject_id')->nullable();
            $table->string('semester')->default('Genap 2024/2025');
            $table->decimal('assignment_score', 5, 2)->default(0);
            $table->decimal('midterm_score', 5, 2)->default(0);
            $table->decimal('final_score', 5, 2)->default(0);
            $table->decimal('attitude_score', 5, 2)->default(0);
            $table->decimal('average_score', 5, 2)->default(0);
            $table->string('grade')->nullable();
            $table->timestamps();
        });

        Schema::create('report_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('student_id')->nullable();
            $table->string('semester');
            $table->decimal('average_score', 5, 2)->default(0);
            $table->string('status')->default('draft');
            $table->json('items')->nullable();
            $table->timestamps();
        });

        Schema::create('memorization_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('student_id')->nullable();
            $table->string('juz');
            $table->string('surah')->nullable();
            $table->string('ayat_range')->nullable();
            $table->decimal('progress_percent', 5, 2)->default(0);
            $table->date('recorded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('class_id')->nullable();
            $table->foreignId('subject_id')->nullable();
            $table->string('day');
            $table->string('time');
            $table->string('teacher_name')->nullable();
            $table->string('room_name')->nullable();
            $table->string('type')->default('academic');
            $table->timestamps();
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('class_id')->nullable();
            $table->foreignId('subject_id')->nullable();
            $table->string('title');
            $table->date('due_date')->nullable();
            $table->string('status')->default('given');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('class_id')->nullable();
            $table->foreignId('subject_id')->nullable();
            $table->string('title');
            $table->string('file_type')->nullable();
            $table->string('file_size')->nullable();
            $table->string('url')->nullable();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->string('title');
            $table->text('body');
            $table->string('category')->default('Umum');
            $table->string('audience')->default('all');
            $table->date('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('student_id')->nullable();
            $table->string('title');
            $table->unsignedInteger('points')->default(0);
            $table->string('category')->default('Ringan');
            $table->date('date')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('permits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('student_id')->nullable();
            $table->string('type');
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('daily_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('student_id')->nullable();
            $table->dateTime('noted_at')->nullable();
            $table->text('note');
            $table->timestamps();
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->string('bank_name');
            $table->string('account_number');
            $table->string('account_holder');
            $table->decimal('balance', 18, 2)->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('student_id')->nullable();
            $table->string('invoice_number');
            $table->string('period');
            $table->decimal('amount', 18, 2);
            $table->date('due_date')->nullable();
            $table->string('status')->default('unpaid');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pesantren_id', 'invoice_number']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('invoice_id')->nullable();
            $table->string('name');
            $table->decimal('amount', 18, 2);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('invoice_id')->nullable();
            $table->foreignId('student_id')->nullable();
            $table->foreignId('payment_method_id')->nullable();
            $table->decimal('amount', 18, 2);
            $table->dateTime('paid_at')->nullable();
            $table->string('status')->default('success');
            $table->string('reference')->nullable();
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('expense_category_id')->nullable();
            $table->string('title');
            $table->decimal('amount', 18, 2);
            $table->date('date')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('cashflow_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->string('type');
            $table->string('title');
            $table->decimal('amount', 18, 2);
            $table->date('date')->nullable();
            $table->timestamps();
        });

        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('student_id')->nullable();
            $table->string('name');
            $table->decimal('amount', 18, 2);
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->string('number');
            $table->string('subject');
            $table->string('type');
            $table->string('destination')->nullable();
            $table->date('date')->nullable();
            $table->string('status')->default('done');
            $table->timestamps();
            $table->unique(['pesantren_id', 'number']);
        });

        Schema::create('room_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('student_id')->nullable();
            $table->string('from_room')->nullable();
            $table->string('to_room')->nullable();
            $table->date('date')->nullable();
            $table->string('status')->default('approved');
            $table->timestamps();
        });

        Schema::create('alumni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->string('name');
            $table->string('graduation_year');
            $table->string('program')->nullable();
            $table->string('occupation')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::create('clinics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->string('name');
            $table->string('specialization')->nullable();
            $table->string('schedule')->nullable();
            $table->timestamps();
        });

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->string('name');
            $table->string('unit')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('minimum_stock')->default(10);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('medicine_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('medicine_id')->nullable();
            $table->integer('quantity');
            $table->string('type');
            $table->date('date')->nullable();
            $table->timestamps();
        });

        Schema::create('medical_queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('student_id')->nullable();
            $table->string('queue_number');
            $table->string('status')->default('waiting');
            $table->time('estimated_time')->nullable();
            $table->timestamps();
        });

        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('student_id')->nullable();
            $table->string('complaint');
            $table->string('diagnosis')->nullable();
            $table->date('date')->nullable();
            $table->string('doctor_name')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('medical_examinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('medical_record_id')->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->string('blood_pressure')->nullable();
            $table->unsignedInteger('pulse')->nullable();
            $table->text('doctor_note')->nullable();
            $table->timestamps();
        });

        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('medical_record_id')->nullable();
            $table->foreignId('medicine_id')->nullable();
            $table->string('dosage');
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
        });

        Schema::create('sick_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('student_id')->nullable();
            $table->string('number');
            $table->date('date')->nullable();
            $table->unsignedInteger('duration_days')->default(1);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['pesantren_id', 'number']);
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('student_id')->nullable();
            $table->string('hospital_name');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('user_id')->nullable();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('type')->default('info');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('user_id')->nullable();
            $table->string('action');
            $table->string('module')->nullable();
            $table->string('ip_address')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['pesantren_id', 'key']);
        });

        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->string('name');
            $table->string('status')->default('success');
            $table->string('file_path')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (array_reverse([
            'roles', 'permissions', 'role_user', 'permission_role', 'pesantrens', 'academic_years',
            'plans', 'subscriptions', 'tenant_modules', 'pesantren_user', 'programs', 'classes', 'subjects', 'guardians', 'teachers', 'staff', 'students', 'rooms',
            'room_assignments', 'attendances', 'dorm_attendances', 'grades', 'report_cards',
            'memorization_records', 'schedules', 'assignments', 'materials', 'announcements',
            'violations', 'permits', 'daily_notes', 'payment_methods', 'bank_accounts', 'invoices',
            'invoice_items', 'payments', 'expense_categories', 'expenses', 'cashflow_entries',
            'discounts', 'letters', 'room_mutations', 'alumni', 'clinics', 'doctors', 'medicines',
            'medicine_stocks', 'medical_queues', 'medical_records', 'medical_examinations',
            'prescriptions', 'sick_letters', 'referrals', 'notification_logs', 'audit_logs',
            'system_settings', 'backups',
        ]) as $table) {
            Schema::dropIfExists($table);
        }
    }
};
