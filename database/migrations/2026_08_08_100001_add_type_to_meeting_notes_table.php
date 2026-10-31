<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_notes', function (Blueprint $table) {
            // The kind of meeting — see MeetingNote::TYPES. Existing rows take the
            // default, which keeps the label they already read as ("Meeting").
            $table->string('type')->default('meeting')->after('title');
            $table->index(['type', 'meeting_date']);
        });
    }

    public function down(): void
    {
        Schema::table('meeting_notes', function (Blueprint $table) {
            $table->dropIndex(['type', 'meeting_date']);
            $table->dropColumn('type');
        });
    }
};
