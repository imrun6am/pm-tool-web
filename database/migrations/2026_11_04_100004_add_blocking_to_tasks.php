<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('blocked_at')->nullable()->after('estimate_minutes');
            $table->string('blocked_reason', 500)->nullable()->after('blocked_at');
        });

        // `task_id` can't finish until `depends_on_id` does.
        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depends_on_id')->constrained('tasks')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['task_id', 'depends_on_id']);
            $table->index('depends_on_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_dependencies');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['blocked_at', 'blocked_reason']);
        });
    }
};
