<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claude_activity_logs', function (Blueprint $table) {
            // The Claude model the session was using, e.g. claude-sonnet-5-5.
            $table->string('model', 100)->nullable()->after('entrypoint')->index();
            // The device's own report of its main network adapter's MAC address.
            $table->string('mac_address', 17)->nullable()->after('hostname')->index();
        });
    }

    public function down(): void
    {
        Schema::table('claude_activity_logs', function (Blueprint $table) {
            $table->dropIndex(['model']);
            $table->dropIndex(['mac_address']);
            $table->dropColumn(['model', 'mac_address']);
        });
    }
};
