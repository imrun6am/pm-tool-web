<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\MergeTasksheetExport;
use App\Jobs\RenderTasksheetExportChunk;
use App\Models\TasksheetEntry;
use App\Models\TasksheetExport;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The API side of background tasksheet PDFs. Generation itself (chunking,
 * merging, failure clean-up) is covered by tests/Feature/TasksheetExportTest;
 * this pins the HTTP contract a desktop client polls against, and that the
 * API applies the same access rules as the web.
 */
class TasksheetExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function team(string $name = 'Core'): Team
    {
        return Team::create(['name' => $name, 'color' => '#0891b2']);
    }

    private function member(Team $team, string $role = User::ROLE_DEVELOPER): User
    {
        $user = User::factory()->create(['role' => $role]);
        $team->members()->attach($user);

        return $user;
    }

    private function lead(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function entry(Team $team, User $user, string $date, array $attrs = []): TasksheetEntry
    {
        return TasksheetEntry::create(array_merge([
            'team_id' => $team->id, 'user_id' => $user->id, 'date' => $date, 'plan' => 'Plan for '.$date,
        ], $attrs));
    }

    /** An export row as the service would create it, without queueing anything. */
    private function export(User $requester, array $attrs = []): TasksheetExport
    {
        return TasksheetExport::create(array_merge([
            'requested_by' => $requester->id,
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
            'include_feedback' => $requester->isLead(),
        ], $attrs));
    }

    private function asUser(User $user): static
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user);

        return $this;
    }

    // ------------------------------------------------------------ create & poll

    public function test_creating_an_export_queues_it_and_answers_202(): void
    {
        Queue::fake();
        $team = $this->team();
        $lead = $this->lead();
        $this->entry($team, $this->member($team), '2026-08-03');

        $response = $this->asUser($lead)
            ->postJson('/api/v1/tasksheet/exports', [
                'team' => $team->id, 'from' => '2026-08-31', 'to' => '2026-08-01', 'attendance' => 'missed',
            ])
            ->assertStatus(202)
            ->assertJsonPath('message', 'Export queued.')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.status_label', 'Queued')
            ->assertJsonPath('data.progress', 0)
            ->assertJsonPath('data.download_url', null)
            ->assertJsonPath('data.team.id', $team->id)
            ->assertJsonPath('data.member', null)
            // A reversed range is swapped, not refused.
            ->assertJsonPath('data.date_from', '2026-08-01')
            ->assertJsonPath('data.date_to', '2026-08-31')
            ->assertJsonPath('data.filters', ['attendance' => 'missed'])
            ->assertJsonPath('data.filter_labels', ['Did not attend standup'])
            ->assertJsonPath('data.includes_feedback', true);

        $export = TasksheetExport::sole();
        $response->assertJsonPath('data.id', $export->id)
            ->assertHeader('Location', route('api-v1.tasksheet.exports.show', $export));

        Queue::assertPushed(RenderTasksheetExportChunk::class);
        Queue::assertNotPushed(MergeTasksheetExport::class);
        // Nothing was rendered during the request.
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_an_export_can_be_polled_to_completion_and_downloaded(): void
    {
        $team = $this->team();
        $lead = $this->lead();
        $this->entry($team, $this->member($team), '2026-08-03');

        // The test queue is sync, so the batch has run by the time the 202 returns.
        $id = $this->asUser($lead)
            ->postJson('/api/v1/tasksheet/exports', ['team' => $team->id, 'from' => '2026-08-01', 'to' => '2026-08-31'])
            ->assertStatus(202)
            ->json('data.id');

        $export = TasksheetExport::findOrFail($id);
        $url = route('api-v1.tasksheet.exports.download', $export);

        $this->getJson("/api/v1/tasksheet/exports/{$id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.progress', 100)
            ->assertJsonPath('data.rows_total', 1)
            ->assertJsonPath('data.download_url', $url);

        $response = $this->get($url)->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('tasksheet-core-2026-08-01-2026-08-31.pdf', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->streamedContent());
    }

    public function test_status_and_list_return_only_the_callers_exports(): void
    {
        $lead = $this->lead();
        $someoneElse = $this->lead();

        $mine = $this->export($lead, ['status' => TasksheetExport::STATUS_PROCESSING, 'chunks_total' => 4, 'chunks_done' => 2]);
        $theirs = $this->export($someoneElse, ['status' => TasksheetExport::STATUS_PROCESSING, 'chunks_total' => 4]);

        $this->asUser($lead)
            ->getJson("/api/v1/tasksheet/exports/status?ids={$mine->id},{$theirs->id},nope")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.status_label', 'Generating')
            // The merge is the last step: 2 of (4 + 1).
            ->assertJsonPath('data.0.progress', 40)
            ->assertJsonPath('data.0.merging', false);

        $this->getJson('/api/v1/tasksheet/exports/status?ids[]='.$mine->id)->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/tasksheet/exports/status')->assertOk()->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/tasksheet/exports')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.filters', [])
            ->assertJsonPath('meta.has_capacity', true)
            ->assertJsonPath('meta.max_running', 3);
    }

    // ------------------------------------------------------------ authorization

    public function test_a_member_may_export_only_their_own_rows(): void
    {
        Queue::fake();
        $team = $this->team();
        $dev = $this->member($team);
        $other = $this->member($team);

        $this->asUser($dev)->postJson('/api/v1/tasksheet/exports', ['team' => $team->id])->assertForbidden();
        $this->postJson('/api/v1/tasksheet/exports', ['member' => $other->id])->assertForbidden();
        $this->assertSame(0, TasksheetExport::count());

        $this->postJson('/api/v1/tasksheet/exports', ['member' => $dev->id, 'team' => $team->id])
            ->assertStatus(202)
            ->assertJsonPath('data.member.id', $dev->id)
            // Feedback is the lead's note about the member: never in their own export.
            ->assertJsonPath('data.includes_feedback', false);
    }

    public function test_only_the_requester_may_show_download_retry_or_delete(): void
    {
        $team = $this->team();
        $lead = $this->lead();
        $admin = $this->lead();
        $teamLead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);

        Storage::disk('local')->put('tasksheet-exports/x/tasksheet.pdf', '%PDF-1.7');
        $ready = $this->export($lead, ['team_id' => $team->id, 'status' => TasksheetExport::STATUS_READY, 'file_path' => 'tasksheet-exports/x/tasksheet.pdf']);
        $failed = $this->export($lead, ['team_id' => $team->id, 'status' => TasksheetExport::STATUS_FAILED, 'error' => 'boom']);

        foreach ([$admin, $teamLead] as $intruder) {
            $this->asUser($intruder);
            $this->getJson("/api/v1/tasksheet/exports/{$ready->id}")->assertForbidden();
            $this->getJson("/api/v1/tasksheet/exports/{$ready->id}/download")->assertForbidden();
            $this->postJson("/api/v1/tasksheet/exports/{$failed->id}/retry")->assertForbidden();
            $this->deleteJson("/api/v1/tasksheet/exports/{$ready->id}")->assertForbidden();
        }

        $this->asUser($lead)->get("/api/v1/tasksheet/exports/{$ready->id}/download")->assertOk();
        $this->deleteJson("/api/v1/tasksheet/exports/{$ready->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Export deleted.');
        $this->assertModelMissing($ready);
        Storage::disk('local')->assertMissing('tasksheet-exports/'.$ready->id);
    }

    public function test_download_rechecks_the_export_rule_for_a_demoted_requester(): void
    {
        $team = $this->team();
        $lead = $this->lead();

        Storage::disk('local')->put('tasksheet-exports/1/tasksheet.pdf', '%PDF-1.7');
        $export = $this->export($lead, ['team_id' => $team->id, 'status' => TasksheetExport::STATUS_READY, 'file_path' => 'tasksheet-exports/1/tasksheet.pdf']);

        $lead->update(['role' => User::ROLE_DEVELOPER]);

        $this->asUser($lead->fresh())->getJson("/api/v1/tasksheet/exports/{$export->id}/download")->assertForbidden();
    }

    // ------------------------------------------------------------ domain rules

    public function test_a_running_export_cannot_be_downloaded_yet(): void
    {
        $lead = $this->lead();
        $export = $this->export($lead, ['status' => TasksheetExport::STATUS_PROCESSING]);

        $this->asUser($lead)->getJson("/api/v1/tasksheet/exports/{$export->id}/download")->assertNotFound();
    }

    public function test_a_caller_cannot_flood_the_queue_with_exports(): void
    {
        Queue::fake();
        config(['tasksheet.export.max_running' => 2]);
        $lead = $this->lead();
        $this->export($lead, ['status' => TasksheetExport::STATUS_PENDING]);
        $this->export($lead, ['status' => TasksheetExport::STATUS_PROCESSING]);
        $failed = $this->export($lead, ['status' => TasksheetExport::STATUS_FAILED, 'error' => 'boom']);

        $this->asUser($lead)->getJson('/api/v1/tasksheet/exports')->assertJsonPath('meta.has_capacity', false);

        $this->postJson('/api/v1/tasksheet/exports', ['date' => '2026-08-03'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'You already have exports in progress. Start another when one finishes.');
        $this->postJson("/api/v1/tasksheet/exports/{$failed->id}/retry")->assertStatus(422);

        $this->assertSame(3, TasksheetExport::count());
        $this->assertSame(TasksheetExport::STATUS_FAILED, $failed->fresh()->status);
    }

    public function test_only_a_failed_export_can_be_retried(): void
    {
        Queue::fake();
        $lead = $this->lead();
        $running = $this->export($lead, ['status' => TasksheetExport::STATUS_PROCESSING, 'chunks_total' => 3, 'chunks_done' => 1]);
        $failed = $this->export($lead, ['status' => TasksheetExport::STATUS_FAILED, 'error' => 'boom', 'chunks_total' => 3, 'chunks_done' => 1]);

        $this->asUser($lead)->postJson("/api/v1/tasksheet/exports/{$running->id}/retry")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only a failed export can be retried.');
        $this->assertSame(1, $running->fresh()->chunks_done);

        $this->postJson("/api/v1/tasksheet/exports/{$failed->id}/retry")
            ->assertStatus(202)
            ->assertHeader('Location', route('api-v1.tasksheet.exports.show', $failed))
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.error', null)
            ->assertJsonPath('data.chunks_done', 0);

        Queue::assertPushed(RenderTasksheetExportChunk::class);
    }

    public function test_deleting_a_running_export_cancels_it(): void
    {
        $lead = $this->lead();
        $export = $this->export($lead, ['status' => TasksheetExport::STATUS_PROCESSING]);

        $this->asUser($lead)->deleteJson("/api/v1/tasksheet/exports/{$export->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Export cancelled and deleted.');
        $this->assertModelMissing($export);
    }

    // ------------------------------------------------------------ retired report

    public function test_the_old_report_endpoint_is_gone_and_does_nothing(): void
    {
        Queue::fake();
        $team = $this->team();
        $lead = $this->lead();
        $this->entry($team, $this->member($team), today()->toDateString());

        $this->asUser($lead)
            ->getJson('/api/v1/tasksheet/report?team='.$team->id.'&from=2026-01-01&to=2026-12-31')
            ->assertStatus(410)
            ->assertHeader('Link', '<'.route('api-v1.tasksheet.exports.store').'>; rel="successor-version"')
            ->assertJsonPath('successor.method', 'POST')
            ->assertJsonPath('successor.url', route('api-v1.tasksheet.exports.store'));

        $this->assertSame(0, TasksheetExport::count());
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
