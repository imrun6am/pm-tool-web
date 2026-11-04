<?php

use App\Models\Task;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable(); // null: the task was created in this status
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();

            $table->index(['task_id', 'changed_at']);
            $table->index(['to_status', 'changed_at']);
        });

        // Bring over the history the activity log already holds, for tasks and
        // users that still exist. The log itself is left as it is.
        DB::table('activities')
            ->where('subject_type', (new Task)->getMorphClass())
            ->whereIn('event', ['created', 'updated'])
            ->whereNotNull('properties->attributes->status')
            ->select(['id', 'subject_id', 'event', 'causer_id', 'properties', 'created_at'])
            ->chunkById(500, function ($activities) {
                $taskIds = DB::table('tasks')->whereIn('id', $activities->pluck('subject_id')->unique())->pluck('id')->flip();
                $userIds = DB::table('users')->whereIn('id', $activities->pluck('causer_id')->filter()->unique())->pluck('id')->flip();

                $rows = [];
                foreach ($activities as $activity) {
                    if (! $taskIds->has($activity->subject_id)) {
                        continue;
                    }

                    $properties = json_decode($activity->properties, true);
                    $rows[] = [
                        'task_id' => $activity->subject_id,
                        'from_status' => $activity->event === 'created' ? null : ($properties['old']['status'] ?? null),
                        'to_status' => $properties['attributes']['status'],
                        'changed_by' => $userIds->has($activity->causer_id) ? $activity->causer_id : null,
                        'changed_at' => $activity->created_at,
                    ];
                }

                DB::table('task_status_changes')->insert($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_status_changes');
    }
};
