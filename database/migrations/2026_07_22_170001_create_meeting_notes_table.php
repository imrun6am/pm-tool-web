<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_notes', function (Blueprint $table) {
            $table->id();
            // Release-wise or general: deleting a release keeps its notes as general notes.
            $table->foreignId('release_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->date('meeting_date');
            $table->text('body');
            $table->timestamps();

            $table->index(['release_id', 'meeting_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_notes');
    }
};
