<?php

namespace Tests\Feature\Api\V1;

use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the tasksheet requirements of the `api-resources` spec.
 */
class TasksheetTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $dev;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $this->dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->team->members()->attach($this->dev);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'team_id' => $this->team->id,
            'user_id' => $this->dev->id,
            'date' => today()->toDateString(),
        ], $overrides);
    }

    public function test_a_row_is_upserted_rather_than_duplicated(): void
    {
        Sanctum::actingAs($this->dev);

        $this->putJson('/api/v1/tasksheet/entries', $this->payload([
            'plan' => '<p>First</p>',
        ]))->assertOk();

        $this->putJson('/api/v1/tasksheet/entries', $this->payload([
            'plan' => '<p>Revised</p>',
        ]))->assertOk();

        $this->assertSame(1, TasksheetEntry::count());
        $this->assertStringContainsString('Revised', TasksheetEntry::first()->plan);
    }

    public function test_a_full_day_leave_clears_the_task_fields(): void
    {
        Sanctum::actingAs($this->dev);

        $this->putJson('/api/v1/tasksheet/entries', $this->payload([
            'plan' => '<p>Ship the thing</p>',
            'work_points' => 8,
        ]))->assertOk();

        $this->putJson('/api/v1/tasksheet/entries', $this->payload([
            'leave_type' => 'sick',
            'plan' => '<p>Ship the thing</p>',
            'work_points' => 8,
        ]))->assertOk()
            ->assertJsonPath('data.is_full_day_leave', true)
            ->assertJsonPath('data.plan', null)
            ->assertJsonPath('data.work_points', null);
    }

    public function test_a_half_day_leave_keeps_the_task_fields(): void
    {
        Sanctum::actingAs($this->dev);

        $this->putJson('/api/v1/tasksheet/entries', $this->payload([
            'leave_type' => 'half_day',
            'plan' => '<p>Half a day of work</p>',
            'work_points' => 4,
        ]))->assertOk()
            ->assertJsonPath('data.is_half_day', true)
            ->assertJsonPath('data.is_full_day_leave', false)
            ->assertJsonPath('data.work_points', 4);
    }

    public function test_a_departed_members_row_remains_on_a_past_sheet(): void
    {
        $past = today()->subDays(10)->toDateString();

        TasksheetEntry::create([
            'team_id' => $this->team->id,
            'user_id' => $this->dev->id,
            'date' => $past,
            'plan' => '<p>Worked that day</p>',
        ]);

        // The member leaves today; the sheet for a day they *were* on the team
        // must still show them, or the record silently rewrites itself.
        $this->team->memberRecords()->updateExistingPivot($this->dev->id, ['left_at' => now()]);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->getJson("/api/v1/tasksheet?team_id={$this->team->id}&date={$past}")
            ->assertOk()
            ->assertJsonPath('data.rows.0.user.id', $this->dev->id)
            ->assertJsonPath('data.rows.0.entry.plan', '<p>Worked that day</p>');
    }

    public function test_the_day_grid_reports_the_teams_output_trend(): void
    {
        Sanctum::actingAs($this->dev);

        $this->putJson('/api/v1/tasksheet/entries', $this->payload(['work_points' => 6]))->assertOk();

        $response = $this->getJson("/api/v1/tasksheet?team_id={$this->team->id}")->assertOk();

        // A fortnight of continuous days, ending on the viewed one.
        $this->assertCount(14, $response->json('data.trend'));
        $this->assertSame(6, $response->json('data.trend.13.work_points'));
        $this->assertTrue($response->json('data.trend.13.is_current'));
    }

    public function test_a_member_removed_from_the_team_can_no_longer_save(): void
    {
        $this->team->memberRecords()->updateExistingPivot($this->dev->id, ['left_at' => now()]);

        Sanctum::actingAs($this->dev);

        // The request still validates (they have no prior entry, so the member
        // check fails first) — either way the save must not succeed.
        $response = $this->putJson('/api/v1/tasksheet/entries', $this->payload([
            'plan' => '<p>Sneaking one in</p>',
        ]));

        $this->assertContains($response->status(), [403, 422]);
        $this->assertSame(0, TasksheetEntry::count());
    }

    public function test_a_lead_may_write_feedback(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_TEAM_LEAD]));

        $this->putJson('/api/v1/tasksheet/entries', $this->payload([
            'plan' => '<p>Their plan</p>',
            'feedback' => '<p>Good progress this week</p>',
        ]))->assertOk()
            ->assertJsonPath('data.feedback', '<p>Good progress this week</p>');
    }
}
