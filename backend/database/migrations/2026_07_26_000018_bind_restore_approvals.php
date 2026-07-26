<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('backup_restore_requests')->where('status', 'executing')->exists()) {
            throw new RuntimeException('Restore sedang berjalan; migration approval binding ditolak sampai restore selesai.');
        }

        Schema::table('backup_restore_requests', function (Blueprint $table) {
            $table->char('approved_change_hash', 64)->nullable()->after('approvals');
        });

        // An approval created before the approved backup state was bound is
        // intentionally not grandfathered into the stronger restore contract.
        DB::table('backup_restore_requests')
            ->whereIn('status', ['pending', 'approved'])
            ->update([
                'status' => 'expired',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('backup_restore_requests', fn (Blueprint $table) => $table->dropColumn('approved_change_hash'));
    }
};
