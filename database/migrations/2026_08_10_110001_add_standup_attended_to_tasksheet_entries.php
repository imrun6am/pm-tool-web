<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasksheet_entries', function (Blueprint $table) {
            // Nullable on purpose: null is "not yet marked", which the filter
            // must tell apart from false ("did not attend"). A `false` default
            // would assert every historical row was a standup no-show.
            $table->boolean('standup_attended')->nullable()->after('leave_type');
            $table->index(['standup_attended', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('tasksheet_entries', function (Blueprint $table) {
            $table->dropIndex(['standup_attended', 'date']);
            $table->dropColumn('standup_attended');
        });
    }
};
