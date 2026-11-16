<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per Claude Code hook event, posted by a monitored device.
        Schema::create('claude_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 32)->index();            // SessionStart | UserPromptSubmit | SessionEnd | …
            $table->string('session_id', 64)->nullable()->index();
            $table->string('device_id', 64)->nullable()->index(); // random id fixed at install time
            $table->string('hostname')->nullable();
            $table->string('os_user', 128)->nullable();
            $table->string('platform', 32)->nullable();
            $table->string('entrypoint', 64)->nullable();     // cli, claude-desktop, …
            $table->string('ip', 45)->nullable()->index();    // as seen by this server, not self-reported
            $table->string('cwd', 1024)->nullable();
            $table->string('detail', 64)->nullable();         // SessionStart source / SessionEnd reason
            $table->string('prompt_excerpt', 1000)->nullable();
            $table->string('transcript_path', 1024)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claude_activity_logs');
    }
};
