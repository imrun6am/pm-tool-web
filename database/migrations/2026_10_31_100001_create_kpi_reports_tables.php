<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope')->default('all');           // all | team | member
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('format');                          // xlsx | pdf
            $table->string('status')->default('queued');       // queued | processing | completed | failed
            $table->unsignedInteger('members_total')->default(0);
            $table->unsignedInteger('members_done')->default(0);
            $table->unsignedInteger('members_failed')->default(0);
            $table->string('batch_id')->nullable();
            $table->string('file_path')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // One row per member per report: each queued chunk writes its own row,
        // so no job ever holds the whole team's analysis in memory.
        Schema::create('kpi_report_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');      // pending | completed | failed
            $table->json('analysis')->nullable();              // Claude's day counts + monthly drafts
            $table->text('error')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->timestamps();

            $table->unique(['kpi_report_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_report_members');
        Schema::dropIfExists('kpi_reports');
    }
};
