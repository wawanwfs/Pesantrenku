<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('type')->index();
            $table->string('resource')->index();
            $table->string('status')->default('queued')->index();
            $table->string('queue_name')->default('default');
            $table->string('file_path')->nullable();
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_jobs');
    }
};
