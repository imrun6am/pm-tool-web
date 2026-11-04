<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An activity's description wraps the record's title in extra words, so a title
 * at the 255-character limit used to overflow the description column and fail
 * the save on MySQL. SQLite doesn't enforce the length, so these assert it.
 */
class ActivityLongTitleTest extends TestCase
{
    use RefreshDatabase;

    private function longestDescription(): int
    {
        return Activity::all()->max(fn (Activity $a) => mb_strlen($a->description));
    }

    public function test_a_task_with_the_longest_title_logs_a_description_that_fits(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $task = Task::create(['title' => str_repeat('a', 255), 'status' => 'todo']);
        $task->update(['title' => str_repeat('é', 255)]);
        $task->delete();

        $this->assertSame(3, Activity::where('subject_type', (new Task)->getMorphClass())->count());
        $this->assertLessThanOrEqual(255, $this->longestDescription());
    }

    public function test_the_full_title_is_kept_in_the_logged_changes(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $title = str_repeat('b', 255);

        $task = Task::create(['title' => 'Short', 'status' => 'todo']);
        $task->update(['title' => $title]);

        $activity = Activity::where('subject_type', (new Task)->getMorphClass())->where('event', 'updated')->firstOrFail();
        $this->assertStringEndsWith('…', $activity->description);
        $this->assertSame($title, $activity->changes()['title']['new']);
    }

    public function test_a_short_title_is_logged_unchanged(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        Task::create(['title' => 'Short', 'status' => 'todo']);

        $this->assertSame('Created task “Short”', Activity::where('subject_type', (new Task)->getMorphClass())->firstOrFail()->description);
    }
}
