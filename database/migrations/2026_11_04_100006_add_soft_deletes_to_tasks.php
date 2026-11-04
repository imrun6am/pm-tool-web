<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
            // Went with its parent, so it comes back with it too.
            $table->boolean('trashed_with_parent')->default(false)->after('deleted_by');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropIndex(['deleted_at']);
            $table->dropSoftDeletes();
            $table->dropColumn('trashed_with_parent');
        });
    }
};
