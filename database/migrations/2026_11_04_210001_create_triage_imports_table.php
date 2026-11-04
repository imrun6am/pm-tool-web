<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('triage_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('input', 16)->default('file');      // file | paste
            $table->string('original_name');
            $table->string('preview', 200)->nullable();        // opening words of a paste
            $table->string('file_path');                       // private `local` disk
            $table->string('extension', 16);
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('status', 24)->default('pending');  // pending | processing | needs_review | completed | failed
            $table->unsignedInteger('chunks_total')->default(0);
            $table->unsignedInteger('chunks_done')->default(0);
            $table->json('proposals')->nullable();             // the draft requests; nothing is in `triage_items` until confirm
            $table->unsignedInteger('proposals_dropped')->default(0);
            $table->unsignedInteger('requests_created')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::table('triage_items', function (Blueprint $table) {
            $table->foreignId('triage_import_id')->nullable()->after('priority')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('triage_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('triage_import_id');
        });

        Schema::dropIfExists('triage_imports');
    }
};
