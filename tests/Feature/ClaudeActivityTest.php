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

    public function test_rows_cannot_be_changed(): void
    {
        $this->sendHook(['hook_event_name' => 'SessionStart'])->assertCreated();
        $log = ClaudeActivityLog::sole();

        try {
            $log->update(['hostname' => 'someone-else']);
            $this->fail('Update should be refused.');
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

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    /** Two devices: dev-1 (office-mac, two prompts) and dev-2 (home-pc, one). */
    private function twoDevices(): void
    {
        $this->sendHook(['hook_event_name' => 'UserPromptSubmit', 'session_id' => 's1', 'cwd' => '/work/billing', 'prompt' => 'fix the invoice'])->assertCreated();
        $this->sendHook(['hook_event_name' => 'UserPromptSubmit', 'session_id' => 's2', 'cwd' => '/work/api', 'prompt' => 'add 100% coverage'], headers: ['X-Claude-User' => 'guest'])->assertCreated();
        $this->sendHook(['hook_event_name' => 'UserPromptSubmit', 'session_id' => 's3', 'prompt' => 'home stuff'], headers: ['X-Claude-Device-Id' => 'dev-2', 'X-Claude-Host' => 'home-pc'])->assertCreated();
    }

    public function test_show_events_opens_the_device_page(): void
    {
        $this->twoDevices();

        $this->actingAs($this->admin())->get('/claude-activity')
            ->assertOk()
            ->assertSee(route('claude-activity.device', 'dev-1'), false);

        $this->get(route('claude-activity.device', 'dev-1'))
            ->assertOk()
            ->assertSee('office-mac')
            ->assertSee('fix the invoice')
            ->assertDontSee('home stuff')
            ->assertViewHas('summary', fn ($s) => (int) $s->events === 2 && (int) $s->prompts === 2 && (int) $s->sessions === 2);
    }

    public function test_device_page_filters(): void
    {
        $this->twoDevices();
        $this->actingAs($this->admin());
        $ids = fn (array $query) => $this->get(route('claude-activity.device', ['device' => 'dev-1', ...$query]))
            ->assertOk()->viewData('logs')->pluck('session_id')->all();

        $this->assertSame(['s1'], $ids(['q' => 'billing']));
        $this->assertSame(['s2'], $ids(['q' => '100%']));
        $this->assertSame(['s2'], $ids(['q' => '%'])); // a literal %, not a wildcard
        $this->assertSame(['s2'], $ids(['os_user' => 'guest']));
        $this->assertSame(['s1'], $ids(['session' => 's1']));
        $this->assertSame(['s1', 's2'], $ids(['sort' => 'oldest']));
        $this->assertSame([], $ids(['from' => now()->addDay()->toDateString()]));
        $this->assertSame(['s2', 's1'], $ids(['to' => now()->toDateString()]));
    }

    public function test_events_without_a_device_id_have_their_own_page(): void
    {
        $this->sendHook(['hook_event_name' => 'SessionStart'], headers: ['X-Claude-Device-Id' => ''])->assertCreated();
        $this->sendHook(['hook_event_name' => 'SessionStart'])->assertCreated();

        $this->actingAs($this->admin())->get(route('claude-activity.device', 'none'))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 1 && $logs->first()->device_id === null);

        $this->get(route('claude-activity.device', 'no-such-device'))->assertNotFound();
    }

    public function test_delete_one_and_the_ticked_events(): void
    {
        $this->twoDevices();
        $this->actingAs($this->admin());
        [$first, $second, $third] = ClaudeActivityLog::orderBy('id')->get()->all();

        $this->delete(route('claude-activity.destroy', $first))->assertRedirect()->assertSessionHas('success', 'Event deleted.');
        $this->post(route('claude-activity.bulk-destroy'), ['ids' => [$second->id]])->assertRedirect()->assertSessionHas('success', '1 event deleted.');

        $this->assertSame([$third->id], ClaudeActivityLog::pluck('id')->all());
    }

    public function test_delete_all_matching_stays_within_the_device_and_filters(): void
    {
        $this->twoDevices();

        $this->actingAs($this->admin())
            ->post(route('claude-activity.bulk-destroy'), ['all' => 1, 'device' => 'dev-1', 'os_user' => 'guest'])
            ->assertSessionHas('success', '1 event deleted.');
        $this->assertSame(['s1', 's3'], ClaudeActivityLog::orderBy('id')->pluck('session_id')->all());

        $this->post(route('claude-activity.bulk-destroy'), ['all' => 1, 'device' => 'dev-1'])->assertSessionHas('success', '1 event deleted.');
        $this->assertSame(['s3'], ClaudeActivityLog::pluck('session_id')->all());
    }

    public function test_bulk_delete_needs_ids_or_all(): void
    {
        $this->twoDevices();

        $this->actingAs($this->admin())->post(route('claude-activity.bulk-destroy'), [])->assertSessionHasErrors('ids');
        $this->assertSame(3, ClaudeActivityLog::count());
    }

    public function test_only_viewers_open_the_device_page_or_delete(): void
    {
        $this->twoDevices();
        $log = ClaudeActivityLog::first();
        $lead = User::factory()->create(['role' => User::ROLE_CTO]);

        $this->actingAs($lead)->get(route('claude-activity.device', 'dev-1'))->assertForbidden();
        $this->delete(route('claude-activity.destroy', $log))->assertForbidden();
        $this->post(route('claude-activity.bulk-destroy'), ['all' => 1])->assertForbidden();

        $this->assertSame(3, ClaudeActivityLog::count());
    }
}
