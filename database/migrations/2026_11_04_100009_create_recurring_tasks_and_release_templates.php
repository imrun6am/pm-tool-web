<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 16)->nullable();
            $table->unsignedInteger('estimate_minutes')->nullable();
            $table->string('frequency', 16); // daily | weekly | monthly
            $table->unsignedTinyInteger('anchor_day')->nullable(); // monthly: the day of the month asked for
            $table->date('next_run_on');
            $table->unsignedSmallInteger('due_in_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('last_run_on')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'next_run_on']);
        });

        Schema::create('release_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('release_template_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_template_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('phase', 32)->nullable();
            $table->string('priority', 16)->nullable();
            $table->unsignedInteger('estimate_minutes')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['release_template_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_template_tasks');
        Schema::dropIfExists('release_templates');
        Schema::dropIfExists('recurring_tasks');
    }
};
