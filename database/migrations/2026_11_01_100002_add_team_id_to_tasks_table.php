<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A task's team, stored on the task so the board can filter by it even when
 * the task has no release. A linked task's team is its release's team; the
 * backfill copies it for every existing linked task.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('release_id')->constrained()->nullOnDelete();
        });

        DB::table('tasks')->whereNotNull('release_id')->update([
            'team_id' => DB::raw('(select releases.team_id from releases where releases.id = tasks.release_id)'),
        ]);
    }

    /**
     * Linked tasks' teams can be re-derived from their releases; an unlinked
     * task's team cannot, so rolling back is refused while any exist.
     */
    public function down(): void
    {
        if (DB::table('tasks')->whereNull('release_id')->whereNotNull('team_id')->exists()) {
            throw new RuntimeException('Some tasks without a release have a team. Clear or link them before rolling back.');
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });
    }
};
