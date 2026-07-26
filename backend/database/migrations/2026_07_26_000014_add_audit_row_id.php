<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('row_id')->nullable()->after('module');
            $table->index(['pesantren_id', 'module', 'row_id', 'id'], 'audit_tenant_module_row_idx');
        });

        DB::table('audit_logs')
            ->whereNull('row_id')
            ->whereNotNull('metadata')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $metadata = json_decode((string) $row->metadata, true) ?: [];
                    $rowId = filter_var($metadata['row_id'] ?? null, FILTER_VALIDATE_INT);
                    if ($rowId !== false && (int) $rowId > 0) {
                        DB::table('audit_logs')->where('id', $row->id)->update(['row_id' => (int) $rowId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_tenant_module_row_idx');
            $table->dropColumn('row_id');
        });
    }
};
