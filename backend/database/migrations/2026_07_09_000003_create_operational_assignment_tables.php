<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('teacher_id')->nullable()->index();
            $table->foreignId('class_id')->nullable()->index();
            $table->foreignId('subject_id')->nullable()->index();
            $table->date('assigned_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->string('status')->default('active');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('musyrif_room_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesantren_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->index();
            $table->foreignId('room_id')->nullable()->index();
            $table->date('assigned_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->string('status')->default('active');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('musyrif_room_assignments');
        Schema::dropIfExists('teacher_assignments');
    }
};
