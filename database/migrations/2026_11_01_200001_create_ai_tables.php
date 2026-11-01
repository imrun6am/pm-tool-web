<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per Claude call made through AiGateway.
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->string('feature', 64)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('model', 64);
            $table->string('status', 16); // ok | refused | truncated | invalid | error
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('error', 500)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // Admin on/off switches; a missing row falls back to config/ai.php.
        Schema::create('ai_feature_settings', function (Blueprint $table) {
            $table->string('feature', 64)->primary();
            $table->boolean('enabled');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Background Claude results (risk briefs, standup digests, document
        // insights), attached to the record they describe.
        Schema::create('ai_insights', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 32);
            $table->morphs('subject');
            $table->date('for_date')->nullable(); // standup digests: the day summarized
            $table->string('status', 16)->default('pending'); // pending | processing | ready | failed
            $table->json('payload')->nullable();
            $table->string('error', 500)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['kind', 'subject_type', 'subject_id']);
        });

        Schema::table('releases', function (Blueprint $table) {
            $table->text('release_notes')->nullable()->after('completion_notes');
        });
    }

    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->dropColumn('release_notes');
        });
        Schema::dropIfExists('ai_insights');
        Schema::dropIfExists('ai_feature_settings');
        Schema::dropIfExists('ai_usage_logs');
    }
};
