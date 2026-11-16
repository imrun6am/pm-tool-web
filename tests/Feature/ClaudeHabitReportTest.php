<?php

namespace Tests\Feature;

use App\Jobs\GenerateClaudeHabitReport;
use App\Models\ClaudeActivityLog;
use App\Models\ClaudeDeviceOwner;
use App\Models\ClaudeHabitReport;
use App\Models\User;
use App\Services\Ai\AiFeatures;
use App\Services\Ai\AiTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeAiTransport;
use Tests\TestCase;

class ClaudeHabitReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $dev;

    protected function setUp(): void
    {
        parent::setUp();
        config(['claude_monitor.viewers' => []]);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->dev = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Rahim']);
    }

    private function event(string $device, string $event, array $extra = []): ClaudeActivityLog
    {
        return ClaudeActivityLog::create(['event' => $event, 'device_id' => $device, 'hostname' => 'mac', 'session_id' => 's1',
            'cwd' => '/Users/rahim/Projects/billing', 'model' => 'claude-sonnet-5-5', 'entrypoint' => 'cli'] + $extra);
    }

    private function answer(): array
    {
        return [
            'summary' => 'Mostly fixes billing bugs in short sessions.',
            'prompt_kinds' => [['kind' => 'Fixing bugs', 'share' => 'about 70%', 'examples' => ['fix the invoice total']]],
            'projects' => [['name' => 'billing', 'focus' => 'Invoice rounding fixes.']],
            'good_practices' => ['States the expected result.'],
            'mistakes' => ['Asks for several changes at once.'],
            'struggles' => ['Asked to fix rounding three times.'],
            'improvements' => ['Ask for a failing test first.'],
        ];
    }

    public function test_a_device_is_linked_to_a_person_and_unlinked(): void
    {
        $this->event('dev-1', 'SessionStart');

        $this->actingAs($this->admin)->post(route('claude-activity.owner', 'dev-1'), ['user_id' => $this->dev->id])
            ->assertSessionHas('success', 'Device linked to Rahim.');
        $this->assertSame($this->dev->id, ClaudeDeviceOwner::where('device_id', 'dev-1')->value('user_id'));

        $this->get('/claude-activity')->assertOk()->assertSee('Habit reports');

        $this->post(route('claude-activity.owner', 'dev-1'), ['user_id' => ''])->assertSessionHas('success', 'Device unlinked.');
        $this->assertSame(0, ClaudeDeviceOwner::count());

        $this->actingAs($this->dev)->post(route('claude-activity.owner', 'dev-1'), ['user_id' => $this->dev->id])->assertForbidden();
    }

    public function test_a_report_needs_a_linked_device_and_prompts(): void
    {
        Queue::fake();
        $this->actingAs($this->admin);
        $period = ['user_id' => $this->dev->id, 'from' => today()->subDays(6)->toDateString(), 'to' => today()->toDateString()];

        $this->post(route('claude-activity.habits.store'), $period)->assertSessionHasErrors('user_id');

        ClaudeDeviceOwner::create(['device_id' => 'dev-1', 'user_id' => $this->dev->id]);
        $this->event('dev-1', 'SessionStart');
        $this->post(route('claude-activity.habits.store'), $period)->assertSessionHasErrors('from');

        $this->event('dev-1', 'UserPromptSubmit', ['prompt_excerpt' => 'fix the invoice total']);
        $this->post(route('claude-activity.habits.store'), $period)->assertRedirect(route('claude-activity.habits.index'));

        $report = ClaudeHabitReport::sole();
        $this->assertSame([ClaudeHabitReport::STATUS_QUEUED, $this->dev->id, $this->admin->id], [$report->status, $report->user_id, $report->requested_by]);
        Queue::assertPushed(GenerateClaudeHabitReport::class, fn ($job) => $job->reportId === $report->id);
    }

    public function test_the_report_counts_facts_and_keeps_the_ai_review(): void
    {
        ClaudeDeviceOwner::create(['device_id' => 'dev-1', 'user_id' => $this->dev->id]);
        $this->event('dev-1', 'SessionStart');
        $this->event('dev-1', 'UserPromptSubmit', ['prompt_excerpt' => 'fix the invoice total']);
        $this->event('dev-1', 'UserPromptSubmit', ['prompt_excerpt' => 'still wrong, fix rounding again']);
        $this->event('dev-1', 'PreToolUse', ['url' => 'https://pm.example.com/board', 'detail' => 'navigate']);
        // Someone else's device: never part of Rahim's report.
        $this->event('dev-2', 'UserPromptSubmit', ['prompt_excerpt' => 'someone else entirely']);

        $ai = (new FakeAiTransport)->respond($this->answer());
        $this->app->instance(AiTransport::class, $ai);
        $report = ClaudeHabitReport::create(['user_id' => $this->dev->id, 'requested_by' => $this->admin->id,
            'period_start' => today()->subDays(6), 'period_end' => today(), 'status' => ClaudeHabitReport::STATUS_QUEUED]);

        GenerateClaudeHabitReport::dispatchSync($report->id);

        $report->refresh();
        $this->assertSame(ClaudeHabitReport::STATUS_COMPLETED, $report->status);
        $this->assertSame(2, $report->facts['prompts']);
        $this->assertSame(1, $report->facts['sessions']);
        $this->assertSame(1, $report->facts['browser_actions']);
        $this->assertSame('billing', $report->facts['projects'][0]['name']);
        $this->assertSame(['pm.example.com' => 1], $report->facts['browser_sites']);
        $this->assertSame('Mostly fixes billing bugs in short sessions.', $report->analysis['summary']);

        $sent = json_encode($ai->requests[0]);
        $this->assertStringContainsString('still wrong, fix rounding again', $sent);
        $this->assertStringNotContainsString('someone else entirely', $sent);

        $this->actingAs($this->admin)->get(route('claude-activity.habits.show', $report))->assertOk()
            ->assertSee('Rahim’s Claude Code habits', false)
            ->assertSee('Fixing bugs')
            ->assertSee('Invoice rounding fixes.')
            ->assertSee('Ask for a failing test first.')
            ->assertSee('Asked to fix rounding three times.');
        $this->get(route('claude-activity.habits.index'))->assertOk()->assertSee('Ready');
    }

    public function test_a_report_fails_cleanly_when_ai_is_switched_off(): void
    {
        ClaudeDeviceOwner::create(['device_id' => 'dev-1', 'user_id' => $this->dev->id]);
        $this->event('dev-1', 'UserPromptSubmit', ['prompt_excerpt' => 'fix it']);
        $this->app->instance(AiTransport::class, new FakeAiTransport);
        app(AiFeatures::class)->set('claude_habits', false, $this->admin);
        $report = ClaudeHabitReport::create(['user_id' => $this->dev->id, 'period_start' => today(), 'period_end' => today()]);

        GenerateClaudeHabitReport::dispatchSync($report->id);

        $report->refresh();
        $this->assertSame(ClaudeHabitReport::STATUS_FAILED, $report->status);
        $this->assertStringContainsString('switched off', $report->error);
        $this->actingAs($this->admin)->get(route('claude-activity.habits.index'))->assertSee('Failed');
    }

    public function test_only_log_viewers_see_or_write_reports(): void
    {
        $report = ClaudeHabitReport::create(['user_id' => $this->dev->id, 'period_start' => today(), 'period_end' => today(), 'status' => 'completed']);

        $this->actingAs($this->dev)->get(route('claude-activity.habits.index'))->assertForbidden();
        $this->get(route('claude-activity.habits.show', $report))->assertForbidden();
        $this->post(route('claude-activity.habits.store'), ['user_id' => $this->dev->id, 'from' => today()->toDateString(), 'to' => today()->toDateString()])->assertForbidden();
        $this->delete(route('claude-activity.habits.destroy', $report))->assertForbidden();

        $this->actingAs($this->admin)->delete(route('claude-activity.habits.destroy', $report))->assertRedirect();
        $this->assertSame(0, ClaudeHabitReport::count());
    }
}
