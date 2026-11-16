<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The page a browser action went to (Claude in Chrome), without its
        // query string or fragment.
        Schema::table('claude_activity_logs', function (Blueprint $table) {
            $table->string('url', 1024)->nullable()->after('cwd');
        });
    }

    public function down(): void
    {
        Schema::table('claude_activity_logs', function (Blueprint $table) {
            $table->dropColumn('url');
        });
    }
};
