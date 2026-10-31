<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();      // evaluatee
            $table->foreignId('evaluator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('competency_id')->constrained('performance_competencies')->restrictOnDelete();
            $table->string('period_type');          // daily | weekly (denormalized from cadence)
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedTinyInteger('score');   // 1..5
            $table->text('note')->nullable();        // evaluator's private note, lead-only
            $table->timestamps();

            // One score per member per competency per period — the upsert target.
            $table->unique(['team_id', 'user_id', 'competency_id', 'period_start'], 'perf_scores_unique_period');
            $table->index(['team_id', 'period_start']);
            $table->index(['user_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_scores');
    }
};
