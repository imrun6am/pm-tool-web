<?php

namespace Tests\Feature\Api\V1;

use App\Models\Note;
use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the `api-foundation` and `api-resources` capability specs — the JSON
 * contract a desktop client is written against, and the domain rules the API
 * must preserve from the web app.
 */
class ResourceContractTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::create(['name' => 'Checkout', 'color' => '#4f46e5']);
        $this->team = Team::create(['name' => 'Alpha', 'color' => '#0891b2']);
    }

    private function lead(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function release(array $overrides = []): Release
    {
        return Release::create($overrides + [
            'project_id' => $this->project->id,
            'team_id' => $this->team->id,
            'name' => 'Checkout v9',
            'year' => 2026, 'quarter' => 3,
            'start_date' => '2026-07-10', 'end_date' => '2026-07-30',
        ]);
    }

    /** A valid release payload, so each test states only what it is varying. */
    private function releasePayload(array $overrides = []): array
    {
        return array_merge([
            'project_id' => $this->project->id,
            'team_id' => $this->team->id,
            'name' => 'Billing v2',
            'year' => 2026, 'quarter' => 3,
            'start_date' => '2026-07-01', 'end_date' => '2026-07-31',
            'phases' => [
                'development' => ['start' => '2026-07-01', 'end' => '2026-07-10'],
                'qa' => ['start' => '2026-07-11', 'end' => '2026-07-18'],
                'retest' => ['start' => '2026-07-19', 'end' => '2026-07-25'],
                'release' => ['start' => '2026-07-26', 'end' => '2026-07-31'],
            ],
        ], $overrides);
    }

    // -- Envelope, pagination, errors -------------------------------------

    public function test_single_resource_is_wrapped_and_dates_are_unambiguous(): void
    {
        $release = $this->release();
        Sanctum::actingAs($this->lead());

        $response = $this->getJson('/api/v1/releases/'.$release->id)->assertOk();

        // Date-only fields must not carry a time, or a client west of UTC
        // renders the day before the one the planner typed.
        $this->assertSame('2026-07-10', $response->json('data.start_date'));
        $this->assertSame('2026-07-30', $response->json('data.end_date'));
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T/',
            $response->json('data.created_at')
        );
    }

    public function test_collections_are_paginated_with_meta_and_links(): void
    {
        Sanctum::actingAs($this->lead());

        $this->getJson('/api/v1/releases')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
    }

    public function test_per_page_is_capped(): void
    {
        Sanctum::actingAs($this->lead());

        $this->getJson('/api/v1/releases?per_page=99999')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_bounded_sub_collection_is_not_paginated(): void
    {
        $release = $this->release();
        foreach (array_keys(Release::PHASES) as $i => $phase) {
            $release->phases()->create([
                'phase' => $phase, 'position' => $i,
                'start_date' => '2026-07-10', 'end_date' => '2026-07-30',
            ]);
        }

        Sanctum::actingAs($this->lead());

        $this->getJson("/api/v1/releases/{$release->id}/phases")
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonMissingPath('meta');
    }

    public function test_validation_failure_is_field_keyed(): void
    {
        Sanctum::actingAs($this->lead());

        $this->postJson('/api/v1/releases', $this->releasePayload([
            'name' => '',
            'end_date' => '2026-06-01', // before the start
        ]))->assertStatus(422)->assertJsonValidationErrors(['name', 'end_date']);
    }

    public function test_unknown_query_parameters_are_ignored(): void
    {
        Sanctum::actingAs($this->lead());

        $this->getJson('/api/v1/releases?not_a_real_filter=chaos')->assertOk();
    }

    public function test_missing_record_is_json_not_html(): void
    {
        Sanctum::actingAs($this->lead());

        $this->getJson('/api/v1/releases/99999')
            ->assertNotFound()
            ->assertHeader('content-type', 'application/json');
    }

    // -- Metadata ---------------------------------------------------------

    public function test_metadata_publishes_every_domain_enumeration(): void
    {
        Sanctum::actingAs($this->lead());

        $this->getJson('/api/v1/meta')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'roles', 'task_statuses', 'release_phases', 'event_types',
                'note_visibilities', 'meeting_note_visibilities', 'quick_link_visibilities',
                'tasksheet_leave_types', 'competency_categories', 'competency_role_scopes',
                'competency_cadences', 'performance_scale' => ['min', 'max', 'anchors'],
                'document_upload', 'pagination',
            ]])
            ->assertJsonPath('data.performance_scale.max', 5)
            ->assertJsonPath('data.task_statuses.0.value', 'todo')
            ->assertJsonPath('data.task_statuses.0.label', 'To Do');
    }

    // -- Releases: the overlap warning ------------------------------------

    public function test_overlapping_release_saves_and_returns_a_warning(): void
    {
        $this->release(['name' => 'Existing', 'start_date' => '2026-07-01', 'end_date' => '2026-07-20']);
        Sanctum::actingAs($this->lead());

        $response = $this->postJson('/api/v1/releases', $this->releasePayload())
            ->assertCreated()
            ->assertJsonPath('warning.type', 'team_overlap');

        // The point of the rule: it warns, it does not block.
        $this->assertDatabaseHas('releases', ['name' => 'Billing v2']);
        $this->assertNotEmpty($response->json('warning.conflicts'));
    }

    public function test_no_warning_when_the_team_is_free(): void
    {
        Sanctum::actingAs($this->lead());

        $this->postJson('/api/v1/releases', $this->releasePayload())
            ->assertCreated()
            ->assertJsonMissingPath('warning');
    }

    public function test_a_completed_release_no_longer_books_the_team(): void
    {
        $this->release([
            'name' => 'Shipped', 'start_date' => '2026-07-01', 'end_date' => '2026-07-20',
            'completed_at' => now(),
        ]);

        Sanctum::actingAs($this->lead());

        $this->postJson('/api/v1/releases', $this->releasePayload())
            ->assertCreated()
            ->assertJsonMissingPath('warning');
    }

    // -- Releases: structural rules ---------------------------------------

    public function test_a_phase_may_not_fall_outside_the_release_window(): void
    {
        Sanctum::actingAs($this->lead());

        $payload = $this->releasePayload();
        $payload['phases']['development']['start'] = '2026-06-01'; // before the release

        $this->postJson('/api/v1/releases', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('phases.development.start');
    }

    public function test_members_must_belong_to_the_owning_team(): void
    {
        $outsider = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        Sanctum::actingAs($this->lead());

        $this->postJson('/api/v1/releases', $this->releasePayload([
            'members' => [$outsider->id],
        ]))->assertStatus(422)->assertJsonValidationErrors('members');
    }

    public function test_completing_and_reopening_a_release(): void
    {
        $release = $this->release();
        $lead = $this->lead();
        Sanctum::actingAs($lead);

        $this->postJson("/api/v1/releases/{$release->id}/complete", [
            'completion_notes' => 'Shipped clean.',
        ])->assertOk()
            ->assertJsonPath('data.is_complete', true)
            ->assertJsonPath('data.completion_notes', 'Shipped clean.')
            ->assertJsonPath('data.completed_by.id', $lead->id);

        $this->postJson("/api/v1/releases/{$release->id}/reopen")
            ->assertOk()
            ->assertJsonPath('data.is_complete', false);
    }

    public function test_release_reports_working_days_after_off_days(): void
    {
        $release = $this->release(['start_date' => '2026-07-01', 'end_date' => '2026-07-10']);
        Sanctum::actingAs($this->lead());

        $this->postJson("/api/v1/releases/{$release->id}/off-days", [
            'date' => '2026-07-04', 'reason' => 'Holiday',
        ])->assertCreated();

        $this->getJson("/api/v1/releases/{$release->id}")
            ->assertOk()
            ->assertJsonPath('data.duration_days', 10)
            ->assertJsonPath('data.off_day_count', 1)
            ->assertJsonPath('data.working_days', 9);
    }

    public function test_off_day_outside_the_window_is_rejected(): void
    {
        $release = $this->release();
        Sanctum::actingAs($this->lead());

        $this->postJson("/api/v1/releases/{$release->id}/off-days", [
            'date' => '2026-08-15',
        ])->assertStatus(422)->assertJsonValidationErrors('date');
    }

    public function test_duplicate_off_day_is_rejected(): void
    {
        $release = $this->release();
        Sanctum::actingAs($this->lead());

        $this->postJson("/api/v1/releases/{$release->id}/off-days", ['date' => '2026-07-15'])
            ->assertCreated();
        $this->postJson("/api/v1/releases/{$release->id}/off-days", ['date' => '2026-07-15'])
            ->assertStatus(422)->assertJsonValidationErrors('date');
    }

    public function test_a_project_with_releases_cannot_be_deleted(): void
    {
        $this->release();
        Sanctum::actingAs($this->lead());

        $this->deleteJson('/api/v1/projects/'.$this->project->id)->assertStatus(422);
        $this->assertDatabaseHas('projects', ['id' => $this->project->id]);

        $this->postJson('/api/v1/projects/'.$this->project->id.'/archive')
            ->assertOk()
            ->assertJsonPath('data.is_archived', true);
    }

    // -- Tasks -------------------------------------------------------------

    public function test_a_subtask_cannot_have_its_own_subtasks(): void
    {
        $release = $this->release();
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        Sanctum::actingAs($dev);

        $parent = $this->postJson("/api/v1/releases/{$release->id}/tasks", ['title' => 'Parent'])
            ->assertCreated()->json('data.id');

        $child = $this->postJson("/api/v1/tasks/{$parent}/subtasks", ['title' => 'Child'])
            ->assertCreated()->json('data.id');

        $this->postJson("/api/v1/tasks/{$child}/subtasks", ['title' => 'Grandchild'])
            ->assertStatus(422);
    }

    public function test_assignee_must_be_on_the_release_team(): void
    {
        $release = $this->release();
        $outsider = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        Sanctum::actingAs($outsider);

        $this->postJson("/api/v1/releases/{$release->id}/tasks", [
            'title' => 'Fix login',
            'assignee_id' => $outsider->id,
        ])->assertStatus(422)->assertJsonValidationErrors('assignee_id');
    }

    public function test_deleting_a_task_removes_its_subtasks(): void
    {
        $release = $this->release();
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_DEVELOPER]));

        $parent = $this->postJson("/api/v1/releases/{$release->id}/tasks", ['title' => 'Parent'])
            ->json('data.id');
        $child = $this->postJson("/api/v1/tasks/{$parent}/subtasks", ['title' => 'Child'])
            ->json('data.id');

        $this->deleteJson("/api/v1/tasks/{$parent}")->assertOk();

        $this->assertDatabaseMissing('tasks', ['id' => $parent]);
        $this->assertDatabaseMissing('tasks', ['id' => $child]);
    }

    // -- Board -------------------------------------------------------------

    public function test_board_returns_every_status_column_even_when_empty(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_DEVELOPER]));

        $response = $this->getJson('/api/v1/board')->assertOk();

        $this->assertCount(count(Task::STATUSES), $response->json('data.columns'));
        $this->assertSame(
            array_keys(Task::STATUSES),
            array_column($response->json('data.columns'), 'status')
        );
    }

    public function test_moving_a_card_sets_status_and_reorders_the_column(): void
    {
        $release = $this->release();
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_DEVELOPER]));

        $a = Task::create(['release_id' => $release->id, 'title' => 'A', 'status' => 'todo', 'position' => 0]);
        $b = Task::create(['release_id' => $release->id, 'title' => 'B', 'status' => 'todo', 'position' => 1]);

        $this->patchJson("/api/v1/board/tasks/{$b->id}", [
            'status' => 'in_progress',
            'ordered_ids' => [$b->id, $a->id],
        ])->assertOk()->assertJsonPath('data.status', 'in_progress');

        $this->assertSame(0, $b->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
    }

    // -- Notes -------------------------------------------------------------

    public function test_a_visually_empty_note_body_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_DEVELOPER]));

        $this->postJson('/api/v1/notes', [
            'date' => '2026-07-20',
            'body' => '<div><br></div>',
            'visibility' => Note::VISIBILITY_PRIVATE,
        ])->assertStatus(422)->assertJsonValidationErrors('body');
    }

    public function test_a_specific_note_reaches_its_recipients_and_nobody_else(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $recipient = User::factory()->create(['role' => User::ROLE_QA]);
        $stranger = User::factory()->create(['role' => User::ROLE_QA]);

        Sanctum::actingAs($author);
        $this->postJson('/api/v1/notes', [
            'date' => '2026-07-20',
            'body' => '<p>For you</p>',
            'visibility' => Note::VISIBILITY_SPECIFIC,
            'recipients' => [$recipient->id],
        ])->assertCreated();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($recipient);
        $this->getJson('/api/v1/notes')->assertOk()->assertJsonCount(1, 'data');

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($stranger);
        $this->getJson('/api/v1/notes')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_note_body_is_sanitized_on_write(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_DEVELOPER]));

        $response = $this->postJson('/api/v1/notes', [
            'date' => '2026-07-20',
            'body' => '<p>Safe</p><script>alert(1)</script>',
            'visibility' => Note::VISIBILITY_PRIVATE,
        ])->assertCreated();

        $this->assertStringNotContainsString('<script>', $response->json('data.body'));
        $this->assertStringContainsString('Safe', $response->json('data.body'));
    }

    // -- Quick links -------------------------------------------------------

    public function test_a_non_http_quick_link_url_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->postJson('/api/v1/quick-links', [
            'label' => 'Bad',
            'url' => 'javascript:alert(1)',
        ])->assertStatus(422)->assertJsonValidationErrors('url');
    }

    // -- Calendar ----------------------------------------------------------

    public function test_event_end_before_start_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_DEVELOPER]));

        $this->postJson('/api/v1/events', [
            'title' => 'Backwards',
            'type' => 'meeting',
            'starts_at' => '2026-07-20 10:00:00',
            'ends_at' => '2026-07-20 09:00:00',
        ])->assertStatus(422)->assertJsonValidationErrors('ends_at');
    }

    public function test_event_index_reports_the_days_each_event_covers(): void
    {
        $creator = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        Sanctum::actingAs($creator);

        $this->postJson('/api/v1/events', [
            'title' => 'Offsite',
            'type' => 'meeting',
            'starts_at' => '2026-07-20 09:00:00',
            'ends_at' => '2026-07-22 17:00:00',
        ])->assertCreated();

        $response = $this->getJson('/api/v1/events?year=2026&month=7')->assertOk();

        $this->assertSame(
            ['2026-07-20', '2026-07-21', '2026-07-22'],
            $response->json('data.events.0.covered_dates')
        );
        $this->assertArrayHasKey('2026-07-21', $response->json('data.events_by_date'));
    }

    // -- Documents ---------------------------------------------------------

    public function test_document_resource_never_exposes_the_storage_path(): void
    {
        $release = $this->release();
        Sanctum::actingAs($this->lead());

        $document = $release->documents()->create([
            'uploaded_by' => $this->lead()->id,
            'original_name' => 'plan.pdf',
            'path' => 'releases/1/secret-name.pdf',
            'mime_type' => 'application/pdf',
            'size' => 2048,
        ]);

        $response = $this->getJson("/api/v1/releases/{$release->id}/documents")->assertOk();

        $this->assertArrayNotHasKey('path', $response->json('data.0'));
        $this->assertSame('2 KB', $response->json('data.0.human_size'));
        $this->assertStringContainsString(
            "/api/v1/releases/{$release->id}/documents/{$document->id}",
            $response->json('data.0.download_url')
        );
    }

    public function test_document_download_requires_authentication(): void
    {
        $release = $this->release();
        $document = $release->documents()->create([
            'uploaded_by' => $this->lead()->id,
            'original_name' => 'plan.pdf',
            'path' => 'releases/1/plan.pdf',
            'mime_type' => 'application/pdf',
            'size' => 10,
        ]);

        $this->getJson("/api/v1/releases/{$release->id}/documents/{$document->id}")
            ->assertUnauthorized();
    }
}
