<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_jobs', function (Blueprint $table) {
            $table->unsignedTinyInteger('recovery_attempts')->default(0)->after('processed_rows');
            $table->timestamp('last_recovered_at')->nullable()->after('recovery_attempts');
            $table->index(['status', 'updated_at'], 'idx_operation_jobs_stale_recovery');
        });

        Schema::table('backups', function (Blueprint $table) {
            $table->index(['status', 'updated_at'], 'idx_backups_stale_recovery');
        });
    }

    public function down(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->dropIndex('idx_backups_stale_recovery');
        });

        Schema::table('operation_jobs', function (Blueprint $table) {
            $table->dropIndex('idx_operation_jobs_stale_recovery');
            $table->dropColumn(['recovery_attempts', 'last_recovered_at']);
        });
    }
};
