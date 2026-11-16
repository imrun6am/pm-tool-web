<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which PM Tool person uses a monitored device. One person per device;
        // a person may have several devices.
        Schema::create('claude_device_owners', function (Blueprint $table) {
            $table->id();
            $table->string('device_id', 64)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // An AI review of one person's Claude Code use over a period.
        Schema::create('claude_habit_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 16)->default('queued')->index(); // queued | processing | completed | failed
            $table->json('facts')->nullable();    // counted by the app, shown as is
            $table->json('analysis')->nullable(); // written by the AI
            $table->string('model', 100)->nullable(); // its cost is on the AI usage page
            $table->string('error', 500)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claude_habit_reports');
        Schema::dropIfExists('claude_device_owners');
    }
};
