<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_notes', function (Blueprint $table) {
            // everyone | attendees — attendees-only notes are visible to their
            // attendees, author, and leads.
            $table->string('visibility')->default('everyone')->after('body');
            $table->index('visibility');
        });

        Schema::create('meeting_note_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['meeting_note_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_note_user');

        Schema::table('meeting_notes', function (Blueprint $table) {
            $table->dropIndex(['visibility']);
            $table->dropColumn('visibility');
        });
    }
};
