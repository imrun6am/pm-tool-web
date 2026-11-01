<?php

namespace Tests\Feature;

use App\Jobs\MergeTasksheetExport;
use App\Jobs\RenderTasksheetExportChunk;
use App\Models\TasksheetEntry;
use App\Models\TasksheetExport;
use App\Models\Team;
use App\Models\User;
use App\Services\TasksheetExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

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

    private function pageCount(string $path): int
    {
        return (new Fpdi)->setSourceFile(Storage::disk('local')->path($path));
    }

    // ------------------------------------------------------------ queueing

    public function test_requesting_an_export_queues_it_instead_of_rendering_inline(): void
    {
        Queue::fake();
        $team = $this->team();
        $lead = $this->lead();
        $dev = $this->member($team);
        $this->entry($team, $dev, '2026-08-03');

        $this->actingAs($lead)
            ->post(route('tasksheet.exports.store'), [
                'team' => $team->id, 'from' => '2026-08-01', 'to' => '2026-08-31', 'attendance' => 'missed',
            ])
            ->assertRedirect(route('tasksheet.exports.index'))
            ->assertSessionHas('success');

        $export = TasksheetExport::sole();
        $this->assertSame(TasksheetExport::STATUS_PENDING, $export->status);
        $this->assertSame($team->id, $export->team_id);
        $this->assertSame(['attendance' => 'missed'], $export->filters);
        $this->assertSame(['2026-08-01', '2026-08-31'], [$export->date_from->toDateString(), $export->date_to->toDateString()]);
        $this->assertTrue($export->include_feedback);
        $this->assertNotNull($export->batch_id);

        Queue::assertPushed(RenderTasksheetExportChunk::class);
        Queue::assertNotPushed(MergeTasksheetExport::class);
        // Nothing was rendered during the request.
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_the_queued_job_produces_a_downloadable_pdf(): void
    {
        $team = $this->team();
        $lead = $this->lead();
        $dev = $this->member($team);
        $this->entry($team, $dev, '2026-08-03');

        // The test queue is sync, so the batch runs within the request.
        $this->actingAs($lead)->post(route('tasksheet.exports.store'), [
            'team' => $team->id, 'from' => '2026-08-01', 'to' => '2026-08-31',
        ]);

        $export = TasksheetExport::sole();
        $this->assertSame(TasksheetExport::STATUS_READY, $export->status);
        $this->assertSame(100, $export->progressPercent());
        $this->assertSame(1, $export->rows_total);
        $this->assertNotNull($export->started_at);
        $this->assertNotNull($export->completed_at);
        Storage::disk('local')->assertExists($export->file_path);
        // Parts are cleaned up once merged.
        $this->assertSame([], Storage::disk('local')->allFiles($export->directory().'/parts'));

        $response = $this->actingAs($lead)->get(route('tasksheet.exports.download', $export))->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('tasksheet-core-2026-08-01-2026-08-31.pdf', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->streamedContent());
    }

    public function test_an_empty_selection_still_produces_a_document(): void
    {
        $team = $this->team();
        $lead = $this->lead();

        $this->actingAs($lead)->post(route('tasksheet.exports.store'), ['team' => $team->id, 'date' => '2026-08-03']);

        $export = TasksheetExport::sole();
        $this->assertSame(TasksheetExport::STATUS_READY, $export->status);
        $this->assertSame(1, $export->chunks_total);
        $this->assertSame(1, $this->pageCount($export->file_path));
    }

    // ------------------------------------------------------------ chunking

    public function test_a_large_dataset_is_rendered_in_bounded_chunks_and_merged(): void
    {
        config(['tasksheet.export.chunk_rows' => 10]);
        $team = $this->team();
        $lead = $this->lead();
        $members = collect(range(1, 4))->map(fn () => $this->member($team));

        $start = Carbon::parse('2026-08-01');
        foreach (range(0, 14) as $d) {
            foreach ($members as $m) {
                $this->entry($team, $m, $start->copy()->addDays($d)->toDateString());
            }
        }
        // Another team's rows must stay out of a team export.
        $other = $this->team('Other');
        $this->entry($other, $this->member($other), '2026-08-05');

        $exports = app(TasksheetExportService::class);
        $export = $this->export($lead, ['team_id' => $team->id]);
        $plan = $exports->plan($export);

        $this->assertSame(60, $plan['rows']);
        $this->assertGreaterThanOrEqual(6, count($plan['chunks']));

        // Bounds tile the range with no gap or overlap…
        $this->assertSame('2026-08-01', $plan['chunks'][0]['from']);
        $this->assertSame('2026-08-31', last($plan['chunks'])['to']);
        foreach (array_slice($plan['chunks'], 1) as $i => $chunk) {
            $this->assertSame(Carbon::parse($plan['chunks'][$i]['to'])->addDay()->toDateString(), $chunk['from']);
        }

        // …and every chunk loads at most the budget, each row exactly once.
        $seen = collect();
        foreach ($plan['chunks'] as $chunk) {
            $rows = $exports->chunkRows($export, $chunk);
            $this->assertLessThanOrEqual(10, $rows->count());
            $seen = $seen->concat($rows->pluck('id'));
        }
        $this->assertSame(60, $seen->count());
        $this->assertSame(60, $seen->unique()->count());

        // End to end: every part renders, and the merge keeps every page.
        $this->actingAs($lead)->post(route('tasksheet.exports.store'), [
            'team' => $team->id, 'from' => '2026-08-01', 'to' => '2026-08-31',
        ]);
        $done = TasksheetExport::latest('id')->first();

        $this->assertSame(TasksheetExport::STATUS_READY, $done->status);
        $this->assertSame($done->chunks_total, $done->chunks_done);
        $this->assertGreaterThanOrEqual($done->chunks_total, $this->pageCount($done->file_path));
    }

    public function test_a_day_larger_than_a_chunk_is_split_by_member(): void
    {
        config(['tasksheet.export.chunk_rows' => 3]);
        $team = $this->team();
        $lead = $this->lead();
        foreach (range(1, 8) as $i) {
            $this->entry($team, $this->member($team), '2026-08-03');
        }
        $this->entry($team, $this->member($team), '2026-08-04');

        $exports = app(TasksheetExportService::class);
        $export = $this->export($lead, ['team_id' => $team->id, 'date_from' => '2026-08-03', 'date_to' => '2026-08-04']);
        $plan = $exports->plan($export);

        // Eight members on the 3rd → three member slices, then the 4th.
        $this->assertCount(4, $plan['chunks']);
        $this->assertSame(['2026-08-03', '2026-08-03'], [$plan['chunks'][0]['from'], $plan['chunks'][2]['to']]);
        $this->assertNull($plan['chunks'][0]['after']);
        $this->assertNull($plan['chunks'][2]['upTo']);

        $ids = collect($plan['chunks'])->flatMap(function (array $chunk) use ($exports, $export) {
            $rows = $exports->chunkRows($export, $chunk);
            $this->assertLessThanOrEqual(3, $rows->count());

            return $rows->pluck('id');
        });
        $this->assertSame(9, $ids->unique()->count());
        $this->assertSame(9, $ids->count());
    }

    // ------------------------------------------------------------ status & UI

    public function test_status_json_reports_progress_and_only_the_viewers_exports(): void
    {
        $lead = $this->lead();
        $someoneElse = $this->lead();

        $mine = $this->export($lead, ['status' => TasksheetExport::STATUS_PROCESSING, 'chunks_total' => 4, 'chunks_done' => 2]);
        $theirs = $this->export($someoneElse, ['status' => TasksheetExport::STATUS_PROCESSING, 'chunks_total' => 4]);

        $this->actingAs($lead)
            ->getJson(route('tasksheet.exports.status', ['ids' => "{$mine->id},{$theirs->id}"]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.status', 'processing')
            ->assertJsonPath('data.0.status_label', 'Generating')
            // The merge is the last step: 2 of (4 + 1).
            ->assertJsonPath('data.0.progress', 40)
            ->assertJsonPath('data.0.chunks_done', 2)
            ->assertJsonPath('data.0.download_url', null);

        $mine->update(['status' => TasksheetExport::STATUS_READY, 'chunks_done' => 4]);

        $this->actingAs($lead)
            ->getJson(route('tasksheet.exports.status', ['ids' => [$mine->id]]))
            ->assertJsonPath('data.0.progress', 100)
            ->assertJsonPath('data.0.download_url', route('tasksheet.exports.download', $mine));
    }

    public function test_the_exports_page_lists_only_the_viewers_own_exports(): void
    {
        $team = $this->team();
        $lead = $this->lead();
        $otherLead = $this->lead();

        $this->export($lead, ['team_id' => $team->id, 'status' => TasksheetExport::STATUS_PROCESSING, 'chunks_total' => 3, 'chunks_done' => 1]);
        $this->export($otherLead, ['team_id' => $team->id, 'date_from' => '2026-05-01', 'date_to' => '2026-05-02']);

        $response = $this->actingAs($lead)->get(route('tasksheet.exports.index'))->assertOk();

        $this->assertCount(1, $response->viewData('exports'));
        $response->assertSee('tasksheetExports(', false)
            ->assertSee('1 Aug 2026')
            ->assertDontSee('1 May 2026');
    }

    public function test_the_exports_form_is_prefilled_from_the_old_report_url(): void
    {
        $team = $this->team();
        $lead = $this->lead();
        $dev = $this->member($team);

        $this->actingAs($lead)
            ->get(route('tasksheet.report', ['member' => $dev->id, 'team' => $team->id, 'from' => '2026-08-01', 'to' => '2026-08-15', 'fill' => 'empty']))
            ->assertRedirect(route('tasksheet.exports.index', [
                'team' => $team->id, 'member' => $dev->id, 'from' => '2026-08-01', 'to' => '2026-08-15', 'fill' => 'empty',
            ]));

        // A GET never creates the export itself.
        $this->assertSame(0, TasksheetExport::count());

        $response = $this->actingAs($lead)->get(route('tasksheet.exports.index', [
            'team' => $team->id, 'member' => $dev->id, 'from' => '2026-08-01', 'to' => '2026-08-15', 'fill' => 'empty',
        ]))->assertOk();

        $this->assertSame($dev->id, $response->viewData('prefill')['member_id']);
        $this->assertSame(['fill' => 'empty'], $response->viewData('prefill')['filters']);
        $response->assertSee('value="2026-08-15"', false)->assertSee('Empty rows');
    }

    // ------------------------------------------------------------ authorization

    public function test_a_member_may_export_only_their_own_rows(): void
    {
        Queue::fake();
        $team = $this->team();
        $dev = $this->member($team);
        $other = $this->member($team);

        $this->actingAs($dev)->post(route('tasksheet.exports.store'), ['team' => $team->id])->assertForbidden();
        $this->actingAs($dev)->post(route('tasksheet.exports.store'), ['member' => $other->id])->assertForbidden();
        $this->assertSame(0, TasksheetExport::count());

        $this->actingAs($dev)->post(route('tasksheet.exports.store'), ['member' => $dev->id, 'team' => $team->id])
            ->assertRedirect(route('tasksheet.exports.index'));

        // Feedback is the lead's note about the member: never in their own export.
        $this->assertFalse(TasksheetExport::sole()->include_feedback);
    }

    public function test_feedback_is_rendered_only_for_a_requester_who_is_still_a_lead(): void
    {
        $team = $this->team();
        $lead = $this->lead();
        $dev = $this->member($team);
        $this->entry($team, $dev, '2026-08-03', ['feedback' => 'Private note']);

        $captured = [];
        View::composer('tasksheet.report', function ($view) use (&$captured) {
            $captured[] = $view->getData()['includeFeedback'];
        });

        $this->actingAs($lead)->post(route('tasksheet.exports.store'), ['team' => $team->id, 'date' => '2026-08-03']);
        $this->actingAs($dev)->post(route('tasksheet.exports.store'), ['member' => $dev->id, 'date' => '2026-08-03']);

        $this->assertSame([true, false], $captured);

        // A lead demoted after asking gets no feedback in what is rendered next.
        $export = $this->export($lead, ['team_id' => $team->id, 'date_from' => '2026-08-03', 'date_to' => '2026-08-03', 'rows_total' => 1]);
        $lead->update(['role' => User::ROLE_DEVELOPER]);
        $captured = [];
        app(TasksheetExportService::class)->renderChunk($export->fresh(), 0, ['from' => '2026-08-03', 'to' => '2026-08-03', 'after' => null, 'upTo' => null]);
        $this->assertSame([false], $captured);
    }

    public function test_only_the_requester_may_download_retry_or_delete_an_export(): void
    {
        $team = $this->team();
        $lead = $this->lead();
        $admin = $this->lead();
        $teamLead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);

        Storage::disk('local')->put('tasksheet-exports/x/tasksheet.pdf', '%PDF-1.7');
        $ready = $this->export($lead, ['team_id' => $team->id, 'status' => TasksheetExport::STATUS_READY, 'file_path' => 'tasksheet-exports/x/tasksheet.pdf']);
        $failed = $this->export($lead, ['team_id' => $team->id, 'status' => TasksheetExport::STATUS_FAILED, 'error' => 'boom']);

        foreach ([$admin, $teamLead] as $intruder) {
            $this->actingAs($intruder)->get(route('tasksheet.exports.download', $ready))->assertForbidden();
            $this->actingAs($intruder)->post(route('tasksheet.exports.retry', $failed))->assertForbidden();
            $this->actingAs($intruder)->delete(route('tasksheet.exports.destroy', $ready))->assertForbidden();
        }

        $this->actingAs($lead)->get(route('tasksheet.exports.download', $ready))->assertOk();
        $this->actingAs($lead)->delete(route('tasksheet.exports.destroy', $ready))->assertRedirect();
        $this->assertModelMissing($ready);
    }

    public function test_download_rechecks_the_export_rule_for_a_demoted_requester(): void
    {
        $team = $this->team();
        $lead = $this->lead();

        Storage::disk('local')->put('tasksheet-exports/1/tasksheet.pdf', '%PDF-1.7');
        $export = $this->export($lead, ['team_id' => $team->id, 'status' => TasksheetExport::STATUS_READY, 'file_path' => 'tasksheet-exports/1/tasksheet.pdf']);

        $lead->update(['role' => User::ROLE_DEVELOPER]);

        $this->actingAs($lead->fresh())->get(route('tasksheet.exports.download', $export))->assertForbidden();
    }

    public function test_a_running_export_cannot_be_downloaded_yet(): void
    {
        $lead = $this->lead();
        $export = $this->export($lead, ['status' => TasksheetExport::STATUS_PROCESSING]);

        $this->actingAs($lead)->get(route('tasksheet.exports.download', $export))->assertNotFound();
    }

    public function test_a_user_cannot_flood_the_queue_with_exports(): void
    {
        Queue::fake();
        config(['tasksheet.export.max_running' => 2]);
        $lead = $this->lead();
        $this->export($lead, ['status' => TasksheetExport::STATUS_PENDING]);
        $this->export($lead, ['status' => TasksheetExport::STATUS_PROCESSING]);

        $this->actingAs($lead)->post(route('tasksheet.exports.store'), ['date' => '2026-08-03'])
            ->assertSessionHas('error');

        $this->assertSame(2, TasksheetExport::count());
    }

    // ------------------------------------------------------------ failure

    public function test_a_failed_chunk_marks_the_export_failed_and_removes_partial_files(): void
    {
        $lead = $this->lead();
        $export = $this->export($lead, ['status' => TasksheetExport::STATUS_PROCESSING, 'chunks_total' => 3, 'chunks_done' => 1]);
        $exports = app(TasksheetExportService::class);
        Storage::disk('local')->put($exports->partPath($export, 0), '%PDF-1.7');

        $job = new RenderTasksheetExportChunk($export->id, 1, ['from' => '2026-08-01', 'to' => '2026-08-31', 'after' => null, 'upTo' => null]);
        $this->assertSame(3, $job->tries);
        $this->assertGreaterThan(0, $job->timeout);
        $job->failed(new RuntimeException('Out of memory'));

        $export->refresh();
        $this->assertSame(TasksheetExport::STATUS_FAILED, $export->status);
        $this->assertSame('Rendering part 2 failed: Out of memory', $export->error);
        $this->assertSame([], Storage::disk('local')->allFiles($export->directory()));

        // A late failure cannot overwrite a finished export.
        $ready = $this->export($lead, ['status' => TasksheetExport::STATUS_READY, 'file_path' => 'x.pdf']);
        (new MergeTasksheetExport($ready->id))->failed(new RuntimeException('late'));
        $this->assertSame(TasksheetExport::STATUS_READY, $ready->fresh()->status);
    }

    public function test_a_merge_with_no_parts_fails_cleanly(): void
    {
        $lead = $this->lead();
        $export = $this->export($lead, ['status' => TasksheetExport::STATUS_PROCESSING, 'chunks_total' => 2, 'chunks_done' => 2]);

        $job = new MergeTasksheetExport($export->id);
        try {
            $job->handle(app(TasksheetExportService::class));
            $this->fail('The merge should have thrown.');
        } catch (RuntimeException $e) {
            $job->failed($e);
        }

        $export->refresh();
        $this->assertSame(TasksheetExport::STATUS_FAILED, $export->status);
        $this->assertStringContainsString('No rendered parts', $export->error);
        $this->assertNull($export->file_path);
    }

    public function test_a_failed_export_can_be_retried_from_scratch(): void
    {
        Queue::fake();
        $team = $this->team();
        $lead = $this->lead();
        $this->entry($team, $this->member($team), '2026-08-03');
        $export = $this->export($lead, [
            'team_id' => $team->id, 'status' => TasksheetExport::STATUS_FAILED,
            'chunks_total' => 3, 'chunks_done' => 2, 'error' => 'boom',
        ]);

        $this->actingAs($lead)->post(route('tasksheet.exports.retry', $export))->assertSessionHas('success');

        $export->refresh();
        $this->assertSame(TasksheetExport::STATUS_PENDING, $export->status);
        $this->assertSame(0, $export->chunks_done);
        $this->assertSame(1, $export->chunks_total);
        $this->assertNull($export->error);
        Queue::assertPushed(RenderTasksheetExportChunk::class, fn ($job) => $job->exportId === $export->id);

        // Only a failed export is retryable.
        $this->actingAs($lead)->post(route('tasksheet.exports.retry', $export))->assertSessionHas('error');
    }

    public function test_expired_exports_are_pruned_with_their_files(): void
    {
        $lead = $this->lead();
        $old = $this->export($lead, ['status' => TasksheetExport::STATUS_READY]);
        $old->forceFill(['created_at' => now()->subDays(15)])->save();
        $fresh = $this->export($lead, ['status' => TasksheetExport::STATUS_READY]);
        Storage::disk('local')->put($old->directory().'/tasksheet.pdf', '%PDF-1.7');

        $this->artisan('model:prune', ['--model' => [TasksheetExport::class]])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($fresh);
        $this->assertSame([], Storage::disk('local')->allFiles($old->directory()));
    }
}
