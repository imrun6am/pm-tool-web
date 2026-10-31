<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_notes', function (Blueprint $table) {
            // Notes written from a calendar meeting link back to it; deleting
            // the event keeps the minutes (same rationale as release_id).
            $table->foreignId('event_id')->nullable()->after('release_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('meeting_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });
    }
};
