<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_scanner_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->index();
            $table->foreignId('user_id')->index();
            $table->string('device_id', 128);
            $table->string('device_name', 190)->nullable();
            $table->text('secret_ciphertext');
            $table->string('status', 24)->default('active');
            $table->string('last_ip', 64)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['pesantren_id', 'user_id', 'device_id'], 'qr_scanner_user_device_unique');
            $table->index(['pesantren_id', 'status'], 'qr_scanner_tenant_status_idx');
        });

        Schema::create('qr_scan_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('pesantren_id')->index();
            $table->foreignId('qr_token_id')->index();
            $table->foreignId('scanner_device_id')->index();
            $table->foreignId('scanner_user_id')->index();
            $table->string('code_hash', 64);
            $table->string('nonce_hash', 64);
            $table->string('ip_address', 64);
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['pesantren_id', 'scanner_user_id', 'consumed_at'], 'qr_challenge_actor_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_scan_challenges');
        Schema::dropIfExists('qr_scanner_devices');
    }
};
