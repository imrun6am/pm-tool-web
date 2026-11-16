<?php

namespace Tests\Feature;

use App\Models\ClaudeActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ClaudeActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['claude_monitor.token' => 'secret-token', 'claude_monitor.viewers' => [], 'claude_monitor.prompt_excerpt_length' => 20]);
    }

    private function sendHook(array $payload, string $token = 'secret-token', array $headers = [])
    {
        return $this->call('POST', '/api/claude-activity', [], [], [], $this->transformHeadersToServerVars(array_merge([
            'Authorization' => "Bearer {$token}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Claude-Device-Id' => 'dev-1',
            'X-Claude-Host' => 'office-mac',
            'X-Claude-User' => 'imrun',
            'X-Claude-Platform' => 'Darwin',
            'X-Claude-Entrypoint' => 'cli',
        ], $headers)), json_encode($payload));
    }

    public function test_hook_event_is_recorded_with_server_seen_ip_and_short_prompt(): void
    {
        $this->sendHook([
            'hook_event_name' => 'UserPromptSubmit',
            'session_id' => 'abc-123',
            'cwd' => '/Users/imrun/project',
            'transcript_path' => '/Users/imrun/.claude/projects/x.jsonl',
            'prompt' => 'Please refactor the entire billing module now',
        ])->assertCreated();

        $log = ClaudeActivityLog::sole();
        $this->assertSame('UserPromptSubmit', $log->event);
        $this->assertSame('abc-123', $log->session_id);
        $this->assertSame('dev-1', $log->device_id);
        $this->assertSame('office-mac', $log->hostname);
        $this->assertSame('cli', $log->entrypoint);
        $this->assertSame('127.0.0.1', $log->ip);
        $this->assertSame('/Users/imrun/project', $log->cwd);
        $this->assertSame('Please refactor the', $log->prompt_excerpt);
    }

    public function test_prompt_is_not_kept_for_other_events_and_source_is_kept(): void
    {
        $this->sendHook(['hook_event_name' => 'SessionStart', 'source' => 'resume', 'prompt' => 'ignored'])->assertCreated();

        $log = ClaudeActivityLog::sole();
        $this->assertNull($log->prompt_excerpt);
        $this->assertSame('resume', $log->detail);
    }

    public function test_wrong_or_missing_token_is_refused(): void
    {
        $this->sendHook(['hook_event_name' => 'SessionStart'], 'wrong')->assertUnauthorized();
        $this->sendHook(['hook_event_name' => 'SessionStart'], '')->assertUnauthorized();

        config(['claude_monitor.token' => '']);
        $this->sendHook(['hook_event_name' => 'SessionStart'], '')->assertStatus(503);

        $this->assertSame(0, ClaudeActivityLog::count());
    }

    public function test_body_that_is_not_a_hook_payload_is_refused(): void
    {
        $this->sendHook(['hello' => 'world'])->assertStatus(422);
        $this->assertSame(0, ClaudeActivityLog::count());
    }

    public function test_rows_cannot_be_changed_or_deleted(): void
    {
        $this->sendHook(['hook_event_name' => 'SessionStart'])->assertCreated();
        $log = ClaudeActivityLog::sole();

        try {
            $log->update(['hostname' => 'someone-else']);
            $this->fail('Update should be refused.');
        } catch (LogicException) {
        }

        try {
            $log->delete();
            $this->fail('Delete should be refused.');
        } catch (LogicException) {
        }

        $this->assertSame('office-mac', $log->fresh()->hostname);
    }

    public function test_admin_sees_the_page_when_no_viewers_are_set(): void
    {
        $this->sendHook(['hook_event_name' => 'UserPromptSubmit', 'prompt' => 'hello there'])->assertCreated();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get('/claude-activity')
            ->assertOk()
            ->assertSee('office-mac')
            ->assertSee('hello there')
            ->assertSee('127.0.0.1');
    }

    public function test_viewer_list_limits_access_even_for_admins(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        config(['claude_monitor.viewers' => [(string) $owner->id]]);

        $this->actingAs($owner)->get('/claude-activity')->assertOk();
        $this->actingAs($admin)->get('/claude-activity')->assertForbidden();
    }

    public function test_taking_an_email_does_not_open_the_page(): void
    {
        // An email entry matches nobody, so changing your own email to it in
        // Profile gets you nowhere.
        config(['claude_monitor.viewers' => ['owner@example.com']]);
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);

        $this->actingAs($dev)->patch('/profile', ['name' => $dev->name, 'email' => 'owner@example.com'])->assertRedirect();

        $this->assertSame('owner@example.com', $dev->fresh()->email);
        $this->actingAs($dev->fresh())->get('/claude-activity')->assertForbidden();
    }

    public function test_a_device_that_changes_its_name_stays_one_row(): void
    {
        $this->travelTo(now()->subHour());
        $this->sendHook(['hook_event_name' => 'SessionStart', 'session_id' => 's1'], headers: ['X-Claude-Host' => 'Mds-MacBook-Pro.local'])->assertCreated();
        $this->travelBack();
        $this->sendHook(['hook_event_name' => 'SessionStart', 'session_id' => 's2'], headers: ['X-Claude-Host' => 'imrun-mac'])->assertCreated();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get('/claude-activity')
            ->assertOk()
            ->assertViewHas('devices', fn ($devices) => $devices->count() === 1
                && $devices->first()->hostname === 'imrun-mac'
                && (int) $devices->first()->sessions === 2
                && (int) $devices->first()->events === 2)
            ->assertSee('also seen under 1 other name');
    }

    public function test_prompt_excerpt_never_outgrows_its_column(): void
    {
        config(['claude_monitor.prompt_excerpt_length' => 5000]);

        $this->sendHook(['hook_event_name' => 'UserPromptSubmit', 'prompt' => str_repeat('a', 3000)])->assertCreated();

        $this->assertSame(ClaudeActivityLog::PROMPT_EXCERPT_MAX, strlen(ClaudeActivityLog::sole()->prompt_excerpt));
    }

    public function test_device_and_event_filters(): void
    {
        $this->sendHook(['hook_event_name' => 'SessionStart'])->assertCreated();
        $this->sendHook(['hook_event_name' => 'SessionStart'], headers: ['X-Claude-Device-Id' => 'dev-2', 'X-Claude-Host' => 'unknown-laptop'])->assertCreated();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get('/claude-activity?device=dev-2&event=SessionStart')
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 1 && $logs->first()->hostname === 'unknown-laptop');
    }
}
