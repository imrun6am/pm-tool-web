<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            // Null only if the account is ever hard-deleted, so the hours still total up.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('logged_on');
            $table->unsignedInteger('minutes');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['task_id', 'logged_on']);
            $table->index(['user_id', 'logged_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_time_logs');
    }
};
