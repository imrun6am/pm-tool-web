<?php

namespace Tests\Feature;

use App\Models\Label;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Developers and QA open the board and task list on their own tasks, and can
 * still reach everyone's. Leads keep seeing everything.
 */
class MyTasksDefaultTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $member;

    private User $teammate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $this->member = $this->user(User::ROLE_DEVELOPER);
        $this->teammate = $this->user(User::ROLE_DEVELOPER);

        $this->task('Mine', $this->member);
        $this->task('Theirs', $this->teammate);
        $this->task('Nobody', null);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->teams()->attach($this->team);

        return $user;
    }

    private function task(string $title, ?User $assignee): Task
    {
        return Task::create(['title' => $title, 'status' => 'todo', 'team_id' => $this->team->id, 'assignee_id' => $assignee?->id]);
    }

    /** @return list<string> */
    private function titlesOn(string $page, User $viewer, array $query = []): array
    {
        $response = $this->actingAs($viewer)->get(route($page, $query))->assertOk();

        return collect(['Mine', 'Theirs', 'Nobody'])
            ->filter(fn (string $title) => str_contains($response->getContent(), $title))
            ->values()
            ->all();
    }

    /** @return array<string, array{string}> */
    public static function pages(): array
    {
        return ['board' => ['board.index'], 'all tasks' => ['tasks.index']];
    }

    #[DataProvider('pages')]
    public function test_a_developer_lands_on_their_own_tasks(string $page): void
    {
        $this->assertSame(['Mine'], $this->titlesOn($page, $this->member));

        $this->actingAs($this->member)->get(route($page))
            ->assertSee('aria-pressed="true"', false)
            ->assertViewHas('filters', fn (array $filters) => $filters['assignee_id'] === $this->member->id);
    }

    #[DataProvider('pages')]
    public function test_a_qa_user_lands_on_their_own_tasks(string $page): void
    {
        $qa = $this->user(User::ROLE_QA);
        $this->task('Nobody', $qa);

        $this->assertSame(['Nobody'], $this->titlesOn($page, $qa));
    }

    #[DataProvider('pages')]
    public function test_a_lead_still_lands_on_everything(string $page): void
    {
        $lead = $this->user(User::ROLE_TECH_LEAD);

        $this->assertSame(['Mine', 'Theirs', 'Nobody'], $this->titlesOn($page, $lead));
    }

    #[DataProvider('pages')]
    public function test_a_developer_can_choose_everyones_tasks(string $page): void
    {
        $this->assertSame(['Mine', 'Theirs', 'Nobody'], $this->titlesOn($page, $this->member, ['assignee_id' => TaskService::ANYONE]));

        $this->actingAs($this->member)->get(route($page, ['assignee_id' => TaskService::ANYONE]))
            ->assertSee('aria-pressed="false"', false);
    }

    #[DataProvider('pages')]
    public function test_a_link_with_a_filter_is_not_narrowed_to_their_own_tasks(string $page): void
    {
        $label = Label::create(['name' => 'Checkout flow', 'color' => '#e11d48']);
        Task::where('title', 'Theirs')->firstOrFail()->labels()->attach($label);

        $this->assertSame(['Theirs'], $this->titlesOn($page, $this->member, ['label_id' => $label->id]));
    }

    #[DataProvider('pages')]
    public function test_the_default_is_not_offered_as_a_filter_to_clear(string $page): void
    {
        $clearLink = '/Clear\s*<\/a>/';

        $this->assertDoesNotMatchRegularExpression($clearLink, $this->actingAs($this->member)->get(route($page))->getContent());
        $this->assertMatchesRegularExpression($clearLink, $this->actingAs($this->member)->get(route($page, ['assignee_id' => $this->member->id]))->getContent());
    }

    public function test_sorting_the_task_list_keeps_the_default(): void
    {
        $this->assertSame(['Mine'], $this->titlesOn('tasks.index', $this->member, ['sort' => 'title']));
    }

    public function test_grouping_the_board_keeps_the_default(): void
    {
        $this->assertSame(['Mine'], $this->titlesOn('board.index', $this->member, ['group' => 'priority']));
    }

    public function test_an_empty_task_list_offers_everyones_tasks(): void
    {
        $this->actingAs($this->user(User::ROLE_DEVELOPER))->get(route('tasks.index'))
            ->assertSee('No tasks assigned to you.')
            ->assertSee('assignee_id='.TaskService::ANYONE, false);
    }

    public function test_the_api_is_unchanged(): void
    {
        Sanctum::actingAs($this->member);

        $this->assertCount(3, $this->getJson('/api/v1/tasks')->assertOk()->json('data'));
    }
}
