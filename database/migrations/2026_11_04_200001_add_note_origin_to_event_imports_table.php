<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An import started from a meeting note or daily note: which note, and
        // the date its relative dates ("next Friday") are read against.
        Schema::table('event_imports', function (Blueprint $table) {
            $table->nullableMorphs('origin');
            $table->date('reference_date')->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('event_imports', function (Blueprint $table) {
            $table->dropMorphs('origin');
            $table->dropColumn('reference_date');
        });
    }
};
