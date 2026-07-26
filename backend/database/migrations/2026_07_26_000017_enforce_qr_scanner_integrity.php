<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_scanner_devices', function (Blueprint $table) {
            $table->unique(['pesantren_id', 'id'], 'qr_scanner_tenant_id_unique');
        });
        Schema::table('qr_scan_challenges', function (Blueprint $table) {
            $table->foreign(['pesantren_id', 'qr_token_id'], 'qr_challenge_token_tenant_fk')
                ->references(['pesantren_id', 'id'])->on('qr_tokens')->restrictOnDelete();
            $table->foreign(['pesantren_id', 'scanner_device_id'], 'qr_challenge_device_tenant_fk')
                ->references(['pesantren_id', 'id'])->on('qr_scanner_devices')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS `qr_challenge_actor_bi`');
            DB::unprepared("CREATE TRIGGER `qr_challenge_actor_bi` BEFORE INSERT ON `qr_scan_challenges` FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM `qr_scanner_devices` d WHERE d.`id`=NEW.`scanner_device_id` AND d.`pesantren_id`=NEW.`pesantren_id` AND d.`user_id`=NEW.`scanner_user_id` AND d.`status`='active') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='scanner actor/device mismatch'; END IF; END");
            DB::unprepared('DROP TRIGGER IF EXISTS `qr_challenge_actor_bu`');
            DB::unprepared("CREATE TRIGGER `qr_challenge_actor_bu` BEFORE UPDATE ON `qr_scan_challenges` FOR EACH ROW BEGIN IF NOT (NEW.`pesantren_id` <=> OLD.`pesantren_id`) OR NOT (NEW.`qr_token_id` <=> OLD.`qr_token_id`) OR NOT (NEW.`scanner_device_id` <=> OLD.`scanner_device_id`) OR NOT (NEW.`scanner_user_id` <=> OLD.`scanner_user_id`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QR challenge identity immutable'; END IF; END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS `qr_challenge_actor_bi`');
            DB::unprepared('DROP TRIGGER IF EXISTS `qr_challenge_actor_bu`');
        }
        Schema::table('qr_scan_challenges', function (Blueprint $table) {
            $table->dropForeign('qr_challenge_token_tenant_fk');
            $table->dropForeign('qr_challenge_device_tenant_fk');
        });
        Schema::table('qr_scanner_devices', function (Blueprint $table) {
            $table->dropUnique('qr_scanner_tenant_id_unique');
        });
    }
};
