<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('end_date');
            $table->foreignId('completed_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            $table->text('completion_notes')->nullable()->after('completed_by');
        });
    }

    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('completed_by');
            $table->dropColumn(['completed_at', 'completion_notes']);
        });
    }
};
