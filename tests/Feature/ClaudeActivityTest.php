<?php

namespace Tests\Feature;

use App\Models\ClaudeActivityLog;
use App\Models\ClaudeDeviceOwner;
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
            ->assertSee('127.0.0.1')
            // Events are on each device's own page, not here.
            ->assertDontSee('hello there')
            ->assertViewMissing('logs');
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

        $this->actingAs($admin)->get(route('claude-activity.device', ['device' => 'dev-2', 'event' => 'SessionStart']))
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
            ->post(route('claude-activity.bulk-destroy'), ['all' => 1, 'device' => 'dev-1', 'os_user' => 'guest', 'password' => 'password'])
            ->assertSessionHas('success', '1 event deleted.');
        $this->assertSame(['s1', 's3'], ClaudeActivityLog::orderBy('id')->pluck('session_id')->all());

        $this->post(route('claude-activity.bulk-destroy'), ['all' => 1, 'device' => 'dev-1', 'password' => 'password'])->assertSessionHas('success', '1 event deleted.');
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

    public function test_a_device_can_be_deleted_from_the_device_list(): void
    {
        $this->twoDevices();
        $admin = $this->admin();

        $this->actingAs($admin)->get('/claude-activity')
            ->assertOk()
            ->assertSee('All 2 events from office-mac will be deleted.', false)
            ->assertSee('Enter your password to confirm.');

        $this->from('/claude-activity')->post(route('claude-activity.bulk-destroy'), ['all' => 1, 'device' => 'dev-1', 'password' => 'password'])
            ->assertRedirect('/claude-activity')
            ->assertSessionHas('success', '2 events deleted.');

        $this->assertSame(['dev-2'], ClaudeActivityLog::pluck('device_id')->all());
        $this->get('/claude-activity')->assertViewHas('devices', fn ($devices) => $devices->pluck('device_id')->all() === ['dev-2']);
    }

    public function test_deleting_a_device_from_its_own_page_goes_back_to_the_list(): void
    {
        $this->twoDevices();
        $this->actingAs($this->admin());

        $this->get(route('claude-activity.device', 'dev-1'))->assertOk()->assertSee('Delete device');

        $this->from(route('claude-activity.device', 'dev-1'))
            ->post(route('claude-activity.bulk-destroy'), ['all' => 1, 'device' => 'dev-1', 'password' => 'password'])
            ->assertRedirect(route('claude-activity.index'))
            ->assertSessionHas('success', '2 events deleted.');

        // Deleting only some of a device's events stays on its page.
        $this->from(route('claude-activity.device', 'dev-2'))
            ->post(route('claude-activity.bulk-destroy'), ['all' => 1, 'device' => 'dev-2', 'event' => 'SessionEnd', 'password' => 'password'])
            ->assertRedirect(route('claude-activity.device', 'dev-2'));
    }

    public function test_device_page_shows_three_ip_addresses_then_the_rest_on_show_all(): void
    {
        foreach (range(1, 13) as $n) {
            ClaudeActivityLog::forceCreate(['event' => 'SessionStart', 'device_id' => 'dev-1', 'hostname' => 'office-mac',
                'ip' => "10.0.0.{$n}", 'created_at' => now()->subMinutes(100 - $n)]);
        }
        $owner = User::factory()->create(['name' => 'Rahim Khan']);
        ClaudeDeviceOwner::create(['device_id' => 'dev-1', 'user_id' => $owner->id]);

        $html = $this->actingAs($this->admin())->get(route('claude-activity.device', 'dev-1'))
            ->assertOk()
            ->assertSee('+10 more IP addresses')
            ->assertSee('Show all')
            ->assertSee('Used by Rahim Khan')
            ->assertViewHas('summary', fn ($s) => (int) $s->active_days === 1)
            ->getContent();

        // Newest first; from the fourth on, hidden until Show all.
        $this->assertLessThan(strpos($html, '>10.0.0.12<'), strpos($html, '>10.0.0.13<'));
        $this->assertMatchesRegularExpression('/x-show="all" x-cloak[^>]*>\s*<div[^>]*>\s*<a[^>]*>10\.0\.0\.10</', $html);
        $this->assertDoesNotMatchRegularExpression('/x-show="all" x-cloak[^>]*>\s*<div[^>]*>\s*<a[^>]*>10\.0\.0\.11</', $html);
    }

    public function test_a_reinstalled_computer_stays_one_device_by_its_mac_address(): void
    {
        $mac = ['X-Claude-Mac' => 'AA-BB-CC-DD-EE-01'];
        $this->sendHook(['hook_event_name' => 'SessionStart', 'session_id' => 'old'], headers: $mac)->assertCreated();
        $owner = User::factory()->create();
        ClaudeDeviceOwner::create(['device_id' => 'dev-1', 'user_id' => $owner->id]);

        // Installed again: a new device id. The installer's own event has no MAC yet.
        $this->sendHook(['hook_event_name' => 'MonitorInstalled'], headers: ['X-Claude-Device-Id' => 'dev-new'])->assertCreated();
        $this->sendHook(['hook_event_name' => 'SessionStart', 'session_id' => 'new'], headers: ['X-Claude-Device-Id' => 'dev-new'] + $mac)->assertCreated();
        // Another computer stays apart.
        $this->sendHook(['hook_event_name' => 'SessionStart'], headers: ['X-Claude-Device-Id' => 'dev-2', 'X-Claude-Mac' => 'aa:bb:cc:dd:ee:02'])->assertCreated();

        $this->assertSame(['dev-1' => 3, 'dev-2' => 1], ClaudeActivityLog::selectRaw('device_id, count(*) as n')->groupBy('device_id')->orderBy('device_id')->pluck('n', 'device_id')->map(fn ($n) => (int) $n)->all());
        $this->assertSame([$owner->id], ClaudeDeviceOwner::where('device_id', 'dev-1')->pluck('user_id')->all());
        $this->assertSame(1, ClaudeDeviceOwner::count());
    }

    public function test_the_merge_command_folds_devices_already_split(): void
    {
        foreach (['dev-a' => 'aa:bb:cc:dd:ee:01', 'dev-b' => 'aa:bb:cc:dd:ee:01', 'dev-c' => 'aa:bb:cc:dd:ee:02'] as $id => $mac) {
            ClaudeActivityLog::create(['event' => 'SessionStart', 'device_id' => $id, 'mac_address' => $mac]);
        }
        $person = User::factory()->create();
        ClaudeDeviceOwner::create(['device_id' => 'dev-b', 'user_id' => $person->id]);

        $this->artisan('claude-activity:merge-devices')->expectsOutputToContain('Merged 1 device(s)')->assertSuccessful();

        $this->assertSame(['dev-a', 'dev-a', 'dev-c'], ClaudeActivityLog::orderBy('id')->pluck('device_id')->all());
        // dev-a had no person, so it takes dev-b's.
        $this->assertSame('dev-a', ClaudeDeviceOwner::sole()->device_id);
        $this->artisan('claude-activity:merge-devices')->expectsOutputToContain('No devices to merge.');
    }

    public function test_prompts_keep_500_characters_by_default(): void
    {
        config(['claude_monitor.prompt_excerpt_length' => (require config_path('claude_monitor.php'))['prompt_excerpt_length']]);

        $this->sendHook(['hook_event_name' => 'UserPromptSubmit', 'prompt' => str_repeat('a', 600)])->assertCreated();

        $this->assertSame(500, strlen(ClaudeActivityLog::sole()->prompt_excerpt));
    }

    public function test_model_comes_from_session_start_and_switches_and_carries_to_prompts(): void
    {
        $this->sendHook(['hook_event_name' => 'SessionStart', 'session_id' => 'm1', 'model' => 'claude-sonnet-5-5'])->assertCreated();
        $this->sendHook(['hook_event_name' => 'UserPromptSubmit', 'session_id' => 'm1', 'prompt' => 'first'])->assertCreated();
        $this->sendHook(['hook_event_name' => 'PostModelSwitch', 'session_id' => 'm1', 'from_model' => 'claude-sonnet-5-5', 'to_model' => 'claude-opus-5-5'])->assertCreated();
        $this->sendHook(['hook_event_name' => 'UserPromptSubmit', 'session_id' => 'm1', 'prompt' => 'second'])->assertCreated();
        $this->sendHook(['hook_event_name' => 'UserPromptSubmit', 'session_id' => 'other', 'prompt' => 'unknown'])->assertCreated();

        $this->assertSame(
            ['claude-sonnet-5-5', 'claude-sonnet-5-5', 'claude-opus-5-5', 'claude-opus-5-5', null],
            ClaudeActivityLog::orderBy('id')->pluck('model')->all(),
        );
        $switch = ClaudeActivityLog::where('event', 'PostModelSwitch')->sole();
        $this->assertSame('claude-sonnet-5-5', $switch->detail);

        $this->actingAs($this->admin())->get(route('claude-activity.device', ['device' => 'dev-1', 'model' => 'claude-opus-5-5']))
            ->assertOk()
            ->assertSee('Model switched')
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 2)
            ->assertViewHas('models', fn ($models) => $models->pluck('model')->sort()->values()->all() === ['claude-opus-5-5', 'claude-sonnet-5-5']);
    }

    public function test_mac_address_is_kept_in_one_format(): void
    {
        $this->sendHook(['hook_event_name' => 'SessionStart'], headers: ['X-Claude-Mac' => 'A0-78-17-A8-3B-0D'])->assertCreated();
        $this->sendHook(['hook_event_name' => 'SessionStart'], headers: ['X-Claude-Mac' => 'a0:78:17:a8:3b:0d'])->assertCreated();
        $this->sendHook(['hook_event_name' => 'SessionStart'], headers: ['X-Claude-Mac' => 'not-a-mac'])->assertCreated();
        $this->sendHook(['hook_event_name' => 'SessionStart'], headers: ['X-Claude-Mac' => '00:00:00:00:00:00'])->assertCreated();

        $this->assertSame(['a0:78:17:a8:3b:0d', 'a0:78:17:a8:3b:0d', null, null], ClaudeActivityLog::orderBy('id')->pluck('mac_address')->all());

        $this->actingAs($this->admin())->get('/claude-activity')->assertSee('a0:78:17:a8:3b:0d');
        $this->get(route('claude-activity.device', ['device' => 'dev-1', 'mac' => 'A0-78-17-A8-3B-0D']))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 2)
            ->assertViewHas('macs', fn ($macs) => $macs->pluck('mac_address')->all() === ['a0:78:17:a8:3b:0d']);
    }

    public function test_browser_actions_keep_the_tool_and_page_but_not_what_was_typed(): void
    {
        $this->sendHook(['hook_event_name' => 'SessionStart', 'session_id' => 'b1', 'model' => 'claude-sonnet-5-5'])->assertCreated();
        $this->sendHook(['hook_event_name' => 'PreToolUse', 'session_id' => 'b1', 'tool_name' => 'mcp__claude-in-chrome__navigate',
            'tool_input' => ['url' => 'https://mail.example.com/inbox?token=secret#top', 'tabId' => 3]])->assertCreated();
        $this->sendHook(['hook_event_name' => 'PreToolUse', 'session_id' => 'b1', 'tool_name' => 'mcp__claude-in-chrome__computer',
            'tool_input' => ['action' => 'type', 'text' => 'my password']])->assertCreated();
        $this->sendHook(['hook_event_name' => 'PreToolUse', 'session_id' => 'b1', 'tool_name' => 'mcp__claude-in-chrome__navigate',
            'tool_input' => ['url' => 'back']])->assertCreated();

        [$nav, $typed, $back] = ClaudeActivityLog::where('event', 'PreToolUse')->orderBy('id')->get()->all();
        $this->assertSame(['navigate', 'https://mail.example.com/inbox', 'claude-sonnet-5-5'], [$nav->detail, $nav->url, $nav->model]);
        $this->assertSame(['computer: type', null], [$typed->detail, $typed->url]);
        $this->assertSame('navigate: back', $back->detail);
        $this->assertStringNotContainsString('my password', json_encode(ClaudeActivityLog::all()->toArray()));
        $this->assertStringNotContainsString('secret', json_encode(ClaudeActivityLog::all()->toArray()));

        $this->actingAs($this->admin())->get(route('claude-activity.device', ['device' => 'dev-1', 'event' => 'PreToolUse', 'q' => 'mail.example']))
            ->assertOk()
            ->assertSee('Browser action')
            ->assertSee('https://mail.example.com/inbox')
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 1)
            ->assertViewHas('summary', fn ($s) => (int) $s->browser_actions === 3);
    }

    public function test_deleting_a_device_needs_the_right_password(): void
    {
        $this->twoDevices();
        $admin = $this->admin();

        $this->actingAs($admin)->from('/claude-activity')
            ->post(route('claude-activity.bulk-destroy'), ['all' => 1, 'device' => 'dev-1', 'password' => 'wrong', '_title' => 'Delete office-mac?'])
            ->assertRedirect('/claude-activity')
            ->assertSessionHasErrors('password', null, 'deleteAll');
        $this->from('/claude-activity')
            ->post(route('claude-activity.bulk-destroy'), ['all' => 1, 'device' => 'dev-1'])
            ->assertSessionHasErrors('password', null, 'deleteAll');
        $this->assertSame(3, ClaudeActivityLog::count());

        // The dialog opens again, still about the same device.
        $this->post(route('claude-activity.bulk-destroy'), ['all' => 1, 'device' => 'dev-1', 'password' => 'wrong', '_title' => 'Delete office-mac?']);
        $this->get('/claude-activity')->assertSee('Delete office-mac?')->assertSee('The password is incorrect.');

        // Ticked events still delete with a click.
        $this->post(route('claude-activity.bulk-destroy'), ['ids' => [ClaudeActivityLog::first()->id]])->assertSessionHas('success', '1 event deleted.');
    }

    public function test_each_event_carries_its_full_details_for_the_drawer(): void
    {
        $this->sendHook(['hook_event_name' => 'UserPromptSubmit', 'session_id' => 'd1', 'cwd' => '/work/billing',
            'transcript_path' => '/Users/imrun/.claude/projects/billing/d1.jsonl', 'prompt' => 'Explain the billing module'])->assertCreated();

        $html = $this->actingAs($this->admin())->get(route('claude-activity.device', 'dev-1'))->assertOk()->getContent();

        $this->assertStringContainsString('aria-labelledby="claude-event-title"', $html);
        // Only the drawer shows the transcript path; it comes in the row's details.
        // @js writes each "/" of a path as \\\/ inside its JSON.parse('…').
        $this->assertStringContainsString(str_replace('/', '\\\\\\/', 'projects/billing/d1.jsonl'), $html);
    }
}
