<?php

namespace Tests\Feature\Ai;

use App\Models\ClaudeActivityLog;
use App\Models\ClaudeDeviceOwner;
use App\Models\User;
use App\Services\Ai\Assistant\AssistantTools;
use App\Services\Ai\Assistant\Tools\ClaudeActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** The assistant's Claude activity tool: Claude Code usage, for admins and the CTO only. */
class AssistantClaudeActivityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $rahim;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->rahim = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Rahim Khan']);
        ClaudeDeviceOwner::create(['device_id' => 'dev-1', 'user_id' => $this->rahim->id]);

        $log = fn (string $device, string $host, string $session, string $prompt, string $cwd) => ClaudeActivityLog::create([
            'event' => 'UserPromptSubmit', 'device_id' => $device, 'hostname' => $host, 'session_id' => $session,
            'cwd' => $cwd, 'model' => 'claude-sonnet-5-5', 'prompt_excerpt' => $prompt,
        ]);
        $log('dev-1', 'rahim-mac', 's1', 'fix the invoice total', '/Users/rahim/Projects/billing');
        $log('dev-1', 'rahim-mac', 's1', 'add 100% test coverage', '/Users/rahim/Projects/billing');
        $log('dev-2', 'office-pc', 's2', 'explain the queue', '/home/x/api');
    }

    private function ask(array $input, ?User $as = null): string
    {
        return app(ClaudeActivity::class)->run($input, $as ?? $this->admin)->text;
    }

    public function test_only_admins_and_the_cto_are_offered_it_and_may_run_it(): void
    {
        $names = fn (User $u) => array_keys(app(AssistantTools::class)->for($u));

        $this->assertContains('claude_activity', $names($this->admin));
        $this->assertContains('claude_activity', $names(User::factory()->create(['role' => User::ROLE_CTO])));
        foreach ([User::ROLE_TECH_LEAD, User::ROLE_TEAM_LEAD, User::ROLE_DEVELOPER, User::ROLE_QA, User::ROLE_VIEWER] as $role) {
            $this->assertNotContains('claude_activity', $names(User::factory()->create(['role' => $role])), $role);
        }

        // Even if the model names it anyway.
        $this->expectException(ValidationException::class);
        $this->ask([], User::factory()->create(['role' => User::ROLE_TECH_LEAD]));
    }

    public function test_overview_counts_devices_people_and_projects(): void
    {
        $out = $this->ask([]);

        $this->assertStringContainsString('Totals: 3 prompts, 2 sessions, 2 devices', $out);
        $this->assertStringContainsString('rahim-mac; person Rahim Khan; 2 prompts', $out);
        $this->assertStringContainsString('office-pc; person not linked', $out);
        $this->assertStringContainsString('billing (2)', $out);
        $this->assertStringContainsString('claude-sonnet-5-5', $out);
    }

    public function test_prompts_for_one_person_and_a_literal_search(): void
    {
        $out = $this->ask(['view' => 'prompts', 'person' => 'Rahim']);
        $this->assertStringContainsString('fix the invoice total', $out);
        $this->assertStringNotContainsString('explain the queue', $out);

        $out = $this->ask(['view' => 'prompts', 'search' => '100%']);
        $this->assertStringContainsString('1 prompts', $out);
        $this->assertStringContainsString('add 100% test coverage', $out);
    }

    public function test_sessions_and_bad_input(): void
    {
        $this->assertStringContainsString('2 sessions', $this->ask(['view' => 'sessions']));
        $this->assertStringContainsString('No activity recorded', $this->ask(['from' => '2020-01-01', 'to' => '2020-01-31']));

        $this->expectException(ValidationException::class);
        $this->ask(['person' => 'Nobody Such']);
    }
}
