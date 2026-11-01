<?php

namespace Tests\Feature\Ai;

use App\Models\Project;
use App\Models\Release;
use App\Models\Team;
use App\Models\User;
use App\Services\Ai\AiTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeAiTransport;
use Tests\TestCase;

/** Shared setup for the Claude feature tests: a fake Claude and a small workspace. */
abstract class AiTestCase extends TestCase
{
    use RefreshDatabase;

    protected FakeAiTransport $claude;

    protected Team $team;

    protected Team $otherTeam;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.anthropic.key' => 'test-key']);
        $this->claude = new FakeAiTransport;
        $this->app->instance(AiTransport::class, $this->claude);

        $this->project = Project::create(['name' => 'Shop', 'color' => '#0891b2']);
        $this->team = Team::create(['name' => 'Alpha', 'color' => '#4f46e5']);
        $this->otherTeam = Team::create(['name' => 'Beta', 'color' => '#db2777']);
    }

    protected function user(string $role, ?Team $team = null): User
    {
        $user = User::factory()->create(['role' => $role]);
        $team?->members()->attach($user);

        return $user;
    }

    protected function release(?Team $team = null, array $overrides = []): Release
    {
        $release = Release::create($overrides + [
            'project_id' => $this->project->id,
            'team_id' => ($team ?? $this->team)->id,
            'name' => 'Checkout v2.4',
            'year' => (int) now()->year, 'quarter' => now()->quarter,
            'start_date' => today()->subDays(10)->toDateString(),
            'end_date' => today()->addDays(10)->toDateString(),
        ]);

        $position = 0;
        foreach (array_keys(Release::PHASES) as $phase) {
            $release->phases()->create([
                'phase' => $phase, 'position' => $position++,
                'start_date' => $release->start_date, 'end_date' => $release->end_date,
            ]);
        }

        return $release;
    }
}
