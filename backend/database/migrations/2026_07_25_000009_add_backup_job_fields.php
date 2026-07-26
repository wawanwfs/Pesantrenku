<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('backups')) {
            return;
        }

        if (! Schema::hasColumn('backups', 'started_at')) {
            Schema::table('backups', function (Blueprint $table) {
                $table->timestamp('started_at')->nullable()->after('status');
            });
        }
        if (! Schema::hasColumn('backups', 'error')) {
            Schema::table('backups', function (Blueprint $table) {
                $table->text('error')->nullable()->after('checksum_sha256');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('backups')) {
            return;
        }

        $columns = array_values(array_filter(
            ['started_at', 'error'],
            fn (string $column) => Schema::hasColumn('backups', $column),
        ));
        if ($columns !== []) {
            Schema::table('backups', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
