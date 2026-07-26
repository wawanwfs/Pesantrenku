<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('authz_version')->default(1)->after('mfa_enabled');
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('authz_version')->nullable()->after('abilities');
            $table->string('device_id', 128)->nullable()->after('authz_version');
            $table->string('device_name', 190)->nullable()->after('device_id');
            $table->index(['tokenable_type', 'tokenable_id', 'device_id'], 'pat_user_device_idx');
        });

        // Tokens issued before authorization/device binding are not safe to
        // grandfather. Force a fresh login under the new contract.
        DB::table('personal_access_tokens')->delete();
        DB::table('sessions')->delete();
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex('pat_user_device_idx');
            $table->dropColumn(['authz_version', 'device_id', 'device_name']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('authz_version'));
    }
};
