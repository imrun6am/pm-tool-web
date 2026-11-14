<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use App\Services\Ai\AiFeatures;
use App\Services\BoardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Finished work piles up for good, so the web board draws only the latest
 * cards in Done and Archive and counts the rest; open columns are never cut.
 */
class BoardFinishedLimitTest extends TestCase
{
    use RefreshDatabase;

    private const EXTRA = 5;

    /** @return list<Task> the done cards, oldest first */
    private function doneCards(int $count): array
    {
        $cards = [];
        foreach (range(1, $count) as $i) {
            Carbon::setTestNow(Carbon::parse('2026-01-01')->addDays($i));
            $task = Task::create(['title' => "Done {$i}", 'status' => 'todo', 'position' => $i]);
            $task->update(['status' => 'done']); // records the status change at that time
            $cards[] = $task;
        }
        Carbon::setTestNow();

        return $cards;
    }

    /** @return list<int> the card ids drawn in a column */
    private function drawnIds(string $html, string $status): array
    {
        preg_match('#<section[^>]*data-status="'.$status.'".*?</section>#s', $html, $column);
        preg_match_all('#<article class="board-card[^"]*"[^>]*data-task-id="(\d+)"#', $column[0], $ids);

        return array_map('intval', $ids[1]);
    }

    public function test_done_shows_the_latest_cards_and_counts_them_all(): void
    {
        $limit = BoardService::FINISHED_LIMIT;
        $cards = $this->doneCards($limit + self::EXTRA);
        $open = Task::create(['title' => 'Still going', 'status' => 'todo', 'position' => 0]);

        $html = $this->actingAs(User::factory()->create())->get('/board')->assertOk()->getContent();

        $drawn = $this->drawnIds($html, 'done');
        $this->assertCount($limit, $drawn);
        // The oldest are the ones left out.
        $this->assertNotContains($cards[0]->id, $drawn);
        $this->assertContains(end($cards)->id, $drawn);
        $this->assertSame([$open->id], $this->drawnIds($html, 'todo'));

        $this->assertStringContainsString('data-hidden="'.self::EXTRA.'"', $html);
        $this->assertStringContainsString('Showing the latest '.$limit.' of '.($limit + self::EXTRA).'.', $html);
        $this->assertStringContainsString('<span class="board-count">'.($limit + self::EXTRA).'</span>', $html);
        $this->assertStringContainsString('finished=all', $html);
    }

    public function test_show_all_draws_every_finished_card(): void
    {
        $this->doneCards(BoardService::FINISHED_LIMIT + self::EXTRA);

        $html = $this->actingAs(User::factory()->create())->get('/board?finished=all')->assertOk()->getContent();

        $this->assertCount(BoardService::FINISHED_LIMIT + self::EXTRA, $this->drawnIds($html, 'done'));
        $this->assertStringNotContainsString('Showing the latest', $html);
    }

    public function test_a_short_done_column_is_not_cut(): void
    {
        $this->doneCards(3);

        $html = $this->actingAs(User::factory()->create())->get('/board')->assertOk()->getContent();

        $this->assertCount(3, $this->drawnIds($html, 'done'));
        $this->assertStringNotContainsString('Showing the latest', $html);
    }

    public function test_the_api_board_still_returns_every_card(): void
    {
        $this->doneCards(BoardService::FINISHED_LIMIT + self::EXTRA);
        Sanctum::actingAs(User::factory()->create());

        $done = collect($this->getJson('/api/v1/board')->assertOk()->json('data.columns'))->firstWhere('status', 'done');

        $this->assertSame(BoardService::FINISHED_LIMIT + self::EXTRA, $done['count']);
        $this->assertCount(BoardService::FINISHED_LIMIT + self::EXTRA, $done['tasks']);
    }

    public function test_an_ai_switch_change_shows_within_the_same_request(): void
    {
        $features = app(AiFeatures::class);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $before = $features->enabled('assignee_suggest');

        $features->set('assignee_suggest', ! $before, $admin);

        $this->assertSame(! $before, $features->enabled('assignee_suggest'));
    }
}
