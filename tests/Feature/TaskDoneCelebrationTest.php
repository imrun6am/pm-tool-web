<?php

namespace Tests\Feature;

use App\Http\Middleware\ShareCelebration;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\CelebrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The "nice work" moment and the preferences it introduces
 * (openspec/changes/task-done-celebration).
 */
class TaskDoneCelebrationTest extends TestCase
{
    use RefreshDatabase;

    private Team $core;

    private User $dev;

    protected function setUp(): void
    {
        parent::setUp();

        $this->core = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $this->dev = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Dana Dev']);
        $this->core->members()->attach($this->dev);
    }

    private function task(string $title = 'Write tests', array $attrs = []): Task
    {
        return Task::create($attrs + [
            'title' => $title, 'status' => 'in_progress', 'position' => 0,
            'team_id' => $this->core->id, 'assignee_id' => $this->dev->id,
        ]);
    }

    /** @return array{message: string, all_clear: bool}|null */
    private function header(TestResponse $response): ?array
    {
        $raw = $response->headers->get(ShareCelebration::HEADER);

        return $raw === null ? null : json_decode(rawurldecode($raw), true);
    }

    private function isOneOfTheMessages(string $message, string $title): bool
    {
        return collect(CelebrationService::MESSAGES)
            ->contains(fn ($m) => str_replace(':task', "“{$title}”", $m) === $message);
    }

    public function test_dragging_to_done_on_the_board_sends_the_header(): void
    {
        $task = $this->task();
        $this->task('Another open one');

        $response = $this->actingAs($this->dev)
            ->patchJson(route('board.move', $task), ['status' => 'done'])
            ->assertOk();

        $celebration = $this->header($response);
        $this->assertNotNull($celebration);
        $this->assertFalse($celebration['all_clear']);
        $this->assertTrue($this->isOneOfTheMessages($celebration['message'], 'Write tests'));
    }

    public function test_marking_done_with_a_redirect_flashes_it_and_the_next_page_shows_it(): void
    {
        $task = $this->task();
        $this->task('Another open one');

        $this->actingAs($this->dev)
            ->from(route('tasks.show', $task))
            ->patch(route('tasks.status', $task), ['status' => 'done'])
            ->assertRedirect()
            ->assertSessionHas('celebrate')
            ->assertHeaderMissing(ShareCelebration::HEADER);

        $this->get(route('tasks.show', $task))->assertOk()->assertSee('"type":"celebrate"', false);
    }

    public function test_the_last_open_task_gets_the_all_clear(): void
    {
        $task = $this->task('Only one');
        $this->task('Old', ['status' => 'done']);

        $celebration = $this->header($this->actingAs($this->dev)->patchJson(route('board.move', $task), ['status' => 'done']));

        $this->assertTrue($celebration['all_clear']);
        $this->assertSame(str_replace(':task', '“Only one”', CelebrationService::ALL_CLEAR), $celebration['message']);
    }

    public function test_archive_reopen_and_unchanged_saves_are_silent(): void
    {
        $done = $this->task('Done one', ['status' => 'done']);

        $this->actingAs($this->dev);
        $this->assertNull($this->header($this->patchJson(route('board.move', $done), ['status' => 'archive'])));
        $this->assertNull($this->header($this->patchJson(route('board.move', $done), ['status' => 'done'])));
        $this->assertNull($this->header($this->patchJson(route('board.move', $done), ['status' => 'todo'])));
    }

    public function test_the_api_carries_the_header_too(): void
    {
        $task = $this->task();
        Sanctum::actingAs($this->dev);

        $response = $this->patchJson(route('api-v1.tasks.status', $task), ['status' => 'done'])->assertOk();

        $this->assertNotNull($this->header($response));
    }

    public function test_turning_celebrations_off_on_the_profile_stops_them(): void
    {
        $this->actingAs($this->dev)->get(route('profile.edit'))->assertOk()->assertSee('Celebrate finished tasks');

        // An unchecked box sends nothing.
        $this->put(route('profile.preferences.update'))->assertRedirect()->assertSessionHas('success');
        $this->assertFalse($this->dev->fresh()->preferences['celebrations']);

        $task = $this->task();
        $this->assertNull($this->header($this->patchJson(route('board.move', $task), ['status' => 'done'])));

        $this->put(route('profile.preferences.update'), ['celebrations' => '1']);
        $this->assertTrue($this->dev->fresh()->preferences['celebrations']);
    }

    public function test_preferences_over_the_api(): void
    {
        Sanctum::actingAs($this->dev);

        $this->getJson(route('api-v1.me'))->assertJsonPath('data.preferences.celebrations', true);
        $this->putJson(route('api-v1.me.preferences'), ['celebrations' => false])
            ->assertOk()->assertJsonPath('data.celebrations', false);
        $this->putJson(route('api-v1.me.preferences'), ['celebrations' => 'nope'])
            ->assertUnprocessable();
        $this->getJson(route('api-v1.me'))->assertJsonPath('data.preferences.celebrations', false);
    }

    public function test_changing_preferences_is_not_written_to_the_activity_log(): void
    {
        $this->actingAs($this->dev)->put(route('profile.preferences.update'), ['celebrations' => '1']);

        $this->assertDatabaseMissing('activities', ['subject_type' => $this->dev->getMorphClass(), 'subject_id' => $this->dev->id, 'event' => 'updated']);
    }
}
