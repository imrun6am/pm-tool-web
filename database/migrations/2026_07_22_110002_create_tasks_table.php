<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('tasks')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('todo'); // todo | in_progress | in_review | done
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('phase')->nullable(); // development | qa | retest | release
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['release_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
