<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevQaAccessTest extends TestCase
{
    use RefreshDatabase;

    private function release(): Release
    {
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);

        return Release::create([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => 'Checkout v9',
            'year' => 2026, 'quarter' => 3, 'start_date' => '2026-07-10', 'end_date' => '2026-07-30',
        ]);
    }

    public function test_dev_and_qa_are_forbidden_from_planning_sections(): void
    {
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $qa = User::factory()->create(['role' => User::ROLE_QA]);

        // The releases *list* and other planning surfaces stay off-limits; the
        // release *detail* page is allowed (covered by its own tests below).
        $restricted = [
            ['get', route('releases.index')],
            ['get', route('projects.index')],
            ['get', route('teams.index')],
            ['get', route('activity.index')],
        ];

        foreach ([$dev, $qa] as $user) {
            foreach ($restricted as [$method, $url]) {
                $this->actingAs($user)->{$method}($url)->assertForbidden();
            }
        }
    }

    public function test_dev_keeps_access_to_work_surfaces(): void
    {
        $release = $this->release();
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $task = Task::create(['release_id' => $release->id, 'title' => 'Fix login', 'status' => 'todo']);

        foreach ([
            route('board.index'),
            route('calendar.index'),
            route('notes.index'),
            route('meeting-notes.index'),
            route('tasksheet.index'),
            route('tasks.show', $task),
            route('releases.show', $release),
        ] as $url) {
            $this->actingAs($dev)->get($url)->assertOk();
        }
    }

    public function test_dev_sees_restricted_release_view_without_timeline_or_edit(): void
    {
        $release = $this->release();
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);

        $this->actingAs($dev)->get(route('releases.show', $release))
            ->assertOk()
            ->assertSee('Checkout v9')       // the release is viewable
            ->assertSee('Documents')          // an allowed section
            ->assertDontSee('Timeline')       // no phase timeline
            ->assertDontSee('Edit release')   // no edit control
            ->assertDontSee('Off-days')       // no planning surface
            ->assertDontSee('Working days');  // no Details metadata card
    }

    public function test_dev_can_collaborate_on_a_release(): void
    {
        $release = $this->release();
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);

        $this->actingAs($dev)->post(route('releases.tasks.store', $release), ['title' => 'Dev task'])
            ->assertRedirect();
        $this->assertSame(1, $release->tasks()->count());

        $this->actingAs($dev)->post(route('releases.comments.store', $release), ['body' => 'A comment'])
            ->assertRedirect();
        $this->assertSame(1, $release->comments()->count());

        $this->actingAs($dev)->post(route('quick-links.store'), [
            'label' => 'Staging', 'url' => 'https://staging.example.com', 'release_id' => $release->id,
        ])->assertRedirect();

        $this->actingAs($dev)->post(route('releases.documents.store', $release), [
            'document' => \Illuminate\Http\UploadedFile::fake()->create('spec.pdf', 10, 'application/pdf'),
        ])->assertRedirect();
        $this->assertSame(1, $release->documents()->count());
    }

    public function test_viewer_cannot_upload_documents(): void
    {
        $release = $this->release();
        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);

        $this->actingAs($viewer)->post(route('releases.documents.store', $release), [
            'document' => \Illuminate\Http\UploadedFile::fake()->create('spec.pdf', 10, 'application/pdf'),
        ])->assertForbidden();
        $this->assertSame(0, $release->documents()->count());
    }

    public function test_other_roles_are_unaffected(): void
    {
        $this->release();

        foreach ([User::ROLE_ADMIN, User::ROLE_CTO, User::ROLE_TEAM_LEAD, User::ROLE_VIEWER] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('releases.index'))->assertOk();
            $this->actingAs($user)->get(route('activity.index'))->assertOk();
        }
    }

    public function test_nav_hides_planning_sections_for_dev(): void
    {
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);

        $this->actingAs($dev)->get(route('notes.index'))
            ->assertOk()
            ->assertDontSee('Projects')
            ->assertDontSee('Activity')
            ->assertSee('Tasksheet');

        $this->actingAs($viewer)->get(route('notes.index'))
            ->assertOk()
            ->assertSee('Projects')
            ->assertSee('Activity');
    }

    public function test_release_name_links_to_detail_page_for_dev_on_task_page(): void
    {
        $release = $this->release();
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $task = Task::create(['release_id' => $release->id, 'title' => 'Fix login', 'status' => 'todo']);

        // Dev can now reach release details, so the name is a link again.
        $this->actingAs($dev)->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee('Checkout v9')
            ->assertSee(route('releases.show', $release), false);
    }

    public function test_dev_gets_member_dashboard_with_tasks_sheet_and_meetings(): void
    {
        $release = $this->release();
        $team = Team::create(['name' => 'Alpha', 'color' => '#123456']);
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $team->members()->attach($dev);

        Task::create(['release_id' => $release->id, 'title' => 'Open assigned task', 'status' => 'in_progress', 'assignee_id' => $dev->id]);
        Task::create(['release_id' => $release->id, 'title' => 'Finished task', 'status' => 'done', 'assignee_id' => $dev->id]);

        $attended = Event::create([
            'title' => 'Sprint sync', 'type' => 'meeting',
            'starts_at' => now()->addDays(2)->setTime(10, 0), 'created_by' => User::factory()->create()->id,
        ]);
        $attended->attendees()->attach($dev);
        Event::create([
            'title' => 'Board meeting', 'type' => 'meeting',
            'starts_at' => now()->addDays(3)->setTime(10, 0), 'created_by' => User::factory()->create()->id,
        ]);

        $this->actingAs($dev)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('My day')
            ->assertSee('Open assigned task')
            ->assertDontSee('Finished task')
            ->assertSee('Not filled — fill now')
            ->assertSee('Alpha')
            ->assertSee('Sprint sync')
            ->assertDontSee('Board meeting');
    }

    public function test_tasksheet_states_on_member_dashboard_progress_from_partial_to_filled(): void
    {
        $team = Team::create(['name' => 'Alpha', 'color' => '#123456']);
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $team->members()->attach($dev);

        // Only the plan filled → partially filled, linking to the sheet.
        TasksheetEntry::create([
            'team_id' => $team->id, 'user_id' => $dev->id, 'date' => today()->toDateString(), 'plan' => 'Work',
        ]);

        $this->actingAs($dev)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Partially filled')
            ->assertDontSee('Filled ✓')
            ->assertDontSee('Not filled')
            ->assertSee('tasksheet?team='.$team->id, false);

        // Every task field filled → Filled ✓.
        TasksheetEntry::first()->update([
            'result' => 'Done', 'comment' => 'Smooth', 'tickets' => 'PM-1',
            'work_points' => 5, 'ticket_count' => 1, 'ticket_points' => 2,
        ]);

        $this->actingAs($dev)->get(route('dashboard'))
            ->assertOk()->assertSee('Filled ✓')->assertDontSee('Partially filled');

        TasksheetEntry::first()->update(['leave_type' => 'sick']);

        $this->actingAs($dev)->get(route('dashboard'))
            ->assertOk()->assertSee('Sick leave');
    }

    public function test_admin_keeps_planning_dashboard(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('My day');
    }
}
