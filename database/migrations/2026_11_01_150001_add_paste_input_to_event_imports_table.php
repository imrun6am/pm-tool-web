<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pasted text is stored as a file like an upload; these two columns
        // only let the history tell the two apart without reading files.
        Schema::table('event_imports', function (Blueprint $table) {
            $table->string('input', 8)->default('file')->after('user_id'); // file | paste
            $table->string('preview')->nullable()->after('original_name');
        });
    }

    public function down(): void
    {
        Schema::table('event_imports', function (Blueprint $table) {
            $table->dropColumn(['input', 'preview']);
        });
    }
};
