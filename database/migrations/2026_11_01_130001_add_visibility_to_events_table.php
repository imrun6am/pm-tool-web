<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing events stay visible to everyone: the default is `shared`.
        Schema::table('events', function (Blueprint $table) {
            $table->string('visibility', 16)->default('shared')->after('type'); // shared | private
            $table->index(['visibility', 'created_by']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['visibility', 'created_by']);
            $table->dropColumn('visibility');
        });
    }
};
