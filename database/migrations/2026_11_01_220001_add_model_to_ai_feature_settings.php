<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin overrides for a feature's model and effort; null keeps config/ai.php's.
        Schema::table('ai_feature_settings', function (Blueprint $table) {
            $table->boolean('enabled')->nullable()->change();
            $table->string('model', 64)->nullable()->after('enabled');
            $table->string('effort', 16)->nullable()->after('model');
        });
    }

    public function down(): void
    {
        Schema::table('ai_feature_settings', function (Blueprint $table) {
            $table->dropColumn(['model', 'effort']);
        });
    }
};
