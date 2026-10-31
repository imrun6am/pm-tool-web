<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasksheet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->text('plan')->nullable();
            $table->text('result')->nullable();
            $table->text('comment')->nullable();
            $table->text('tickets')->nullable();
            $table->unsignedInteger('work_points')->nullable();
            $table->unsignedInteger('ticket_count')->nullable();
            $table->unsignedInteger('ticket_points')->nullable();
            $table->string('leave_type')->nullable(); // casual | sick
            $table->text('feedback')->nullable();     // lead-only, never rendered for dev/qa
            $table->timestamps();

            // One row per member per team per day — upsert target.
            $table->unique(['team_id', 'user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasksheet_entries');
    }
};
