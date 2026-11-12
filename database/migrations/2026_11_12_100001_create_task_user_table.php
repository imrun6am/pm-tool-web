<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every assignee of a task. `tasks.assignee_id` stays as the lead assignee,
 * always one of these rows, so this copies each one across.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_user', function (Blueprint $table) {
            $table->id(); // the order people were added in
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['task_id', 'user_id']);
            $table->index('user_id');
        });

        // Trashed tasks too: restoring one should bring its assignee back.
        DB::table('tasks')->whereNotNull('assignee_id')->orderBy('id')
            ->select(['id', 'assignee_id', 'created_at'])
            ->chunk(1000, function ($tasks) {
                DB::table('task_user')->insertOrIgnore($tasks->map(fn ($task) => [
                    'task_id' => $task->id,
                    'user_id' => $task->assignee_id,
                    'created_at' => $task->created_at,
                    'updated_at' => $task->created_at,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_user');
    }
};
