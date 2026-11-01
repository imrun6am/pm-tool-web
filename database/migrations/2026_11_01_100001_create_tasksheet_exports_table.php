<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasksheet_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->json('filters')->nullable();                // attendance / fill / leave
            $table->boolean('include_feedback')->default(false); // snapshot of the requester's lead status
            $table->string('status')->default('pending');      // pending | processing | ready | failed
            $table->unsignedInteger('chunks_total')->default(0);
            $table->unsignedInteger('chunks_done')->default(0);
            $table->unsignedInteger('rows_total')->default(0);
            $table->string('batch_id')->nullable();
            $table->string('file_path')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['requested_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasksheet_exports');
    }
};
