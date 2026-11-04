<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A generated standup has no human author.
        Schema::table('meeting_notes', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->change();
        });

        Schema::create('daily_standups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            // Null means someone deleted the note, so the schedule leaves it alone.
            $table->foreignId('meeting_note_id')->nullable()->constrained()->nullOnDelete();
            $table->json('content');
            $table->char('fingerprint', 32);
            $table->text('search_text');
            $table->timestamp('generated_at')->nullable();
            // Once set, the attendee list belongs to people, not the generator.
            $table->timestamp('attendees_edited_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_standups');

        // created_by is left nullable: generated notes may already have no author.
    }
};
