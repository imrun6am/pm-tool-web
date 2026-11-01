<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\Navigation;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // "Ask Claude" shows only when Claude is configured; pin it for the shape tests.
        config(['services.anthropic.key' => 'test-key']);
    }

    private function user(string $role): User
    {
        return new User(['role' => $role]);
    }

    /** @return array<string, list<string>> group key => item keys */
    private function shape(User $user): array
    {
        return collect(Navigation::groups($user))
            ->mapWithKeys(fn ($g) => [$g['key'] => array_column($g['items'], 'key')])
            ->all();
    }

    public function test_leads_get_every_group_in_order(): void
    {
        foreach (User::LEAD_ROLES as $role) {
            // KPI reports are admin-only (Claude-drafted HR reviews).
            $administration = $role === User::ROLE_ADMIN ? ['performance', 'kpi-reports', 'users', 'ai-usage'] : ['performance', 'users'];

            $this->assertSame([
                'overview' => ['dashboard', 'ask'],
                'planning' => ['releases', 'projects', 'teams'],
                'work' => ['board', 'tasksheet', 'calendar'],
                'collaboration' => ['notes', 'meetings', 'activity'],
                'administration' => $administration,
            ], $this->shape($this->user($role)), "role: {$role}");
        }
    }

    public function test_viewer_gets_no_administration_group(): void
    {
        $this->assertSame(
            ['overview', 'planning', 'work', 'collaboration'],
            array_keys($this->shape($this->user(User::ROLE_VIEWER))),
        );
    }

    public function test_developer_and_qa_get_only_work_surfaces(): void
    {
        foreach ([User::ROLE_DEVELOPER, User::ROLE_QA] as $role) {
            $this->assertSame([
                'overview' => ['dashboard', 'ask'],
                'work' => ['board', 'tasksheet', 'calendar'],
                'collaboration' => ['notes', 'meetings'],
            ], $this->shape($this->user($role)), "role: {$role}");
        }
    }

    public function test_every_item_has_a_unique_icon(): void
    {
        $items = collect(Navigation::groups($this->user(User::ROLE_ADMIN)))->flatMap(fn ($g) => $g['items']);

        $this->assertCount(15, $items);
        $this->assertSame($items->count(), $items->pluck('icon')->unique()->count());
    }

    public function test_ask_claude_is_hidden_until_claude_is_configured(): void
    {
        config(['services.anthropic.key' => null]);

        $this->assertSame(['dashboard'], $this->shape($this->user(User::ROLE_ADMIN))['overview']);
    }

    public function test_internal_visibility_rules_are_not_exposed(): void
    {
        foreach (Navigation::groups($this->user(User::ROLE_ADMIN)) as $group) {
            foreach ($group['items'] as $item) {
                $this->assertArrayNotHasKey('visible', $item);
            }
        }
    }

    public function test_new_release_is_offered_only_to_release_managers(): void
    {
        $labels = fn (string $role) => array_column(Navigation::createActions($this->user($role)), 'label');

        $this->assertSame(['New task', 'New release', 'New event', 'New meeting note'], $labels(User::ROLE_TEAM_LEAD));

        foreach ([User::ROLE_DEVELOPER, User::ROLE_QA, User::ROLE_VIEWER] as $role) {
            $this->assertSame(['New task', 'New event', 'New meeting note'], $labels($role), "role: {$role}");
        }
    }
}
