<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tasks no longer need a release. Deleting a release now unlinks its tasks
 * (nullOnDelete) instead of deleting them: the work outlives the plan.
 *
 * SQLite rebuilds the table for both steps; MySQL alters in place and the
 * (release_id, parent_id) index keeps backing the foreign key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['release_id']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('release_id')->nullable()->change();
            $table->foreign('release_id')->references('id')->on('releases')->nullOnDelete();
        });
    }

    /**
     * Restores the cascade. NOT NULL comes back only when no task is unlinked —
     * unlinked tasks are refused rather than deleted.
     */
    public function down(): void
    {
        if (DB::table('tasks')->whereNull('release_id')->exists()) {
            throw new RuntimeException('Some tasks have no release. Link or delete them before rolling back.');
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['release_id']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('release_id')->nullable(false)->change();
            $table->foreign('release_id')->references('id')->on('releases')->cascadeOnDelete();
        });
    }
};
