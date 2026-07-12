<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('mfa_secret')->nullable()->after('password');
            $table->boolean('mfa_enabled')->default(false)->after('mfa_secret');
        });

        Schema::table('backups', function (Blueprint $table) {
            $table->string('encryption_provider')->nullable()->after('file_path');
            $table->string('encryption_key_id')->nullable()->after('encryption_provider');
            $table->char('checksum_sha256', 64)->nullable()->after('encryption_key_id');
        });

        Schema::create('backup_restore_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_id')->index();
            $table->foreignId('pesantren_id')->index();
            $table->foreignId('requested_by')->index();
            $table->string('status')->default('pending')->index();
            $table->json('approvals')->nullable();
            $table->text('reason');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->foreignId('executed_by')->nullable();
            $table->text('result')->nullable();
            $table->timestamps();
        });

        Schema::create('secret_rotations', function (Blueprint $table) {
            $table->id();
            $table->string('secret_name')->index();
            $table->string('old_key_id')->nullable();
            $table->string('new_key_id')->nullable();
            $table->foreignId('rotated_by')->nullable();
            $table->string('status')->default('completed');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secret_rotations');
        Schema::dropIfExists('backup_restore_requests');
        Schema::table('backups', fn (Blueprint $table) => $table->dropColumn(['encryption_provider', 'encryption_key_id', 'checksum_sha256']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['mfa_secret', 'mfa_enabled']));
    }
};
