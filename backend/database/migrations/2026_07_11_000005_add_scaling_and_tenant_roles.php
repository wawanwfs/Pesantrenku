<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_jobs', function (Blueprint $table) {
            $table->string('source_path')->nullable()->after('file_path');
            $table->string('output_path')->nullable()->after('source_path');
            $table->unsignedTinyInteger('progress')->default(0)->after('status');
            $table->unsignedInteger('total_rows')->default(0)->after('progress');
            $table->unsignedInteger('processed_rows')->default(0)->after('total_rows');
            $table->timestamp('expires_at')->nullable()->after('finished_at');
        });

        Schema::create('tenant_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->index();
            $table->string('name');
            $table->string('label');
            $table->text('description')->nullable();
            $table->json('permissions');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['pesantren_id', 'name']);
        });

        Schema::table('pesantren_user', function (Blueprint $table) {
            $table->foreignId('tenant_role_id')->nullable()->after('role_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('pesantren_user', function (Blueprint $table) {
            $table->dropColumn('tenant_role_id');
        });

        Schema::dropIfExists('tenant_roles');

        Schema::table('operation_jobs', function (Blueprint $table) {
            $table->dropColumn(['source_path', 'output_path', 'progress', 'total_rows', 'processed_rows', 'expires_at']);
        });
    }
};
