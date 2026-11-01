<?php

namespace Tests\Feature\Ai;

use App\Jobs\GenerateAiInsight;
use App\Models\AiInsight;
use App\Models\ReleaseDocument;
use App\Models\Task;
use App\Models\User;
use App\Services\Ai\Insights\AiInsightService;
use App\Services\Ai\ScheduleAssistant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/** #1 release notes, #3 risk brief, #6 release catch-up, #7 document insights, #8 schedule assistant. */
class ReleaseAiTest extends AiTestCase
{
    // -- #1 Release notes ------------------------------------------------------

    public function test_release_notes_are_drafted_from_done_tasks_only_and_saved_sanitized(): void
    {
        $release = $this->release();
        Task::create(['release_id' => $release->id, 'title' => 'Ship coupon codes PAY-142', 'status' => 'done']);
        Task::create(['release_id' => $release->id, 'title' => 'Still open work', 'status' => 'todo']);
        Task::create(['release_id' => $release->id, 'title' => 'Filed away idea', 'status' => 'archive']);
        $lead = $this->user(User::ROLE_TEAM_LEAD);

        $this->claude->respond([
            'summary' => 'Coupons arrive.',
            'sections' => [['title' => 'New', 'items' => ['Coupon codes at checkout <script>x</script> (PAY-142)']]],
            'known_issues' => [],
        ]);

        $html = $this->actingAs($lead)->postJson(route('ai.release-notes.draft', $release))
            ->assertOk()->assertJsonPath('task_count', 1)->json('html');

        $this->assertStringContainsString('PAY-142', $this->claude->prompt());
        $this->assertStringNotContainsString('Still open work', $this->claude->prompt());
        $this->assertStringNotContainsString('Filed away idea', $this->claude->prompt());
        $this->assertStringContainsString('<h1>New</h1>', $html);
        $this->assertStringNotContainsString('<script>', $html);

        $this->actingAs($lead)->put(route('ai.release-notes.update', $release), ['release_notes' => '<div>Hi</div><script>alert(1)</script>'])
            ->assertRedirect();
        $this->assertSame('<div>Hi</div>', $release->fresh()->release_notes);
    }

    public function test_only_release_planners_draft_or_save_release_notes(): void
    {
        $release = $this->release();
        foreach ([User::ROLE_DEVELOPER, User::ROLE_VIEWER] as $role) {
            $user = $this->user($role, $this->team);
            $this->actingAs($user)->postJson(route('ai.release-notes.draft', $release))->assertForbidden();
            $this->actingAs($user)->put(route('ai.release-notes.update', $release), ['release_notes' => 'x'])->assertForbidden();
        }
        $this->assertSame([], $this->claude->requests);
    }

    // -- #3 Risk brief ---------------------------------------------------------

    public function test_risk_brief_is_queued_then_generated_from_computed_signals(): void
    {
        Queue::fake();
        $release = $this->release();
        Task::create(['release_id' => $release->id, 'title' => 'Late API', 'status' => 'in_progress', 'due_date' => today()->subDays(2)]);
        Task::create(['release_id' => $release->id, 'title' => 'Done thing', 'status' => 'done']);
        $lead = $this->user(User::ROLE_TECH_LEAD);

        $id = $this->actingAs($lead)->postJson(route('ai.insights.store'), ['kind' => 'release_risk', 'subject_id' => $release->id])
            ->assertOk()->assertJsonPath('status', 'pending')->json('id');
        Queue::assertPushed(GenerateAiInsight::class);

        // A second request while one runs reuses it.
        $this->actingAs($lead)->postJson(route('ai.insights.store'), ['kind' => 'release_risk', 'subject_id' => $release->id])
            ->assertJsonPath('id', $id);

        $this->claude->respond([
            'status' => 'at_risk', 'headline' => 'One overdue task.',
            'reasons' => [['severity' => 'high', 'text' => 'Late API is 2 days overdue']],
            'recommendations' => ['Unblock Late API'], 'stakeholder_update' => 'On watch.',
        ]);
        (new GenerateAiInsight($id))->handle(app(AiInsightService::class));

        $insight = AiInsight::find($id);
        $this->assertSame('ready', $insight->status);
        $this->assertSame(1, $insight->payload['signals']['overdue']);
        $this->assertSame(50, $insight->payload['signals']['done_pct']);
        $this->assertStringContainsString('Late API', $this->claude->prompt());

        $this->actingAs($this->user(User::ROLE_VIEWER))->getJson(route('ai.insights.show', $id))
            ->assertOk()->assertJsonPath('status', 'ready')->assertSee('One overdue task.');
    }

    public function test_risk_brief_access_rules(): void
    {
        $release = $this->release();
        $insight = AiInsight::create(['kind' => 'release_risk', 'subject_type' => $release->getMorphClass(), 'subject_id' => $release->id, 'status' => 'ready', 'payload' => []]);

        // Developers and QA never see the planning brief; viewers see it but cannot generate.
        $dev = $this->user(User::ROLE_DEVELOPER, $this->team);
        $this->actingAs($dev)->getJson(route('ai.insights.show', $insight))->assertForbidden();
        $this->actingAs($this->user(User::ROLE_VIEWER))->postJson(route('ai.insights.store'), ['kind' => 'release_risk', 'subject_id' => $release->id])
            ->assertForbidden();
    }

    public function test_release_page_shows_the_ai_cards_to_the_right_people(): void
    {
        $release = $this->release();

        $this->actingAs($this->user(User::ROLE_ADMIN))->get(route('releases.show', $release))
            ->assertOk()->assertSee('Risk brief')->assertSee('Assess risk with Claude')->assertSee('Release notes');
        $this->actingAs($this->user(User::ROLE_DEVELOPER, $this->team))->get(route('releases.show', $release))
            ->assertOk()->assertDontSee('Risk brief')->assertDontSee('Assess risk with Claude');
    }

    // -- #6 Catch me up (release) ----------------------------------------------

    public function test_release_thread_catch_up_needs_three_comments_and_is_cached(): void
    {
        $release = $this->release();
        $lead = $this->user(User::ROLE_ADMIN);
        $release->comments()->create(['user_id' => $lead->id, 'body' => 'First']);

        $this->actingAs($lead)->postJson(route('ai.catch-up.release', $release))->assertStatus(409);

        $release->comments()->create(['user_id' => $lead->id, 'body' => 'We agreed to ship Friday']);
        $release->comments()->create(['user_id' => $lead->id, 'body' => 'QA will retest Thursday']);
        $this->claude->respond(['summary' => 'Shipping Friday.', 'decisions' => ['Ship Friday'], 'open_questions' => [], 'next_steps' => [['text' => 'Retest', 'owner' => 'QA']]]);

        $this->actingAs($lead)->postJson(route('ai.catch-up.release', $release))->assertOk()->assertJsonPath('decisions.0', 'Ship Friday');
        $this->actingAs($lead)->postJson(route('ai.catch-up.release', $release))->assertOk();
        $this->assertCount(1, $this->claude->requests); // second call served from cache
    }

    public function test_developers_cannot_catch_up_on_another_teams_release(): void
    {
        $release = $this->release($this->otherTeam);
        $this->actingAs($this->user(User::ROLE_DEVELOPER, $this->team))->postJson(route('ai.catch-up.release', $release))->assertForbidden();
    }

    // -- #7 Document insights --------------------------------------------------

    public function test_text_documents_are_read_in_chunks_merged_and_turned_into_tasks(): void
    {
        Storage::fake('local');
        $release = $this->release();
        $path = "releases/{$release->id}/spec.txt";
        Storage::disk('local')->put($path, str_repeat("The checkout MUST support coupons.\n\n", 2000)); // > one chunk
        $lead = $this->user(User::ROLE_TEAM_LEAD);
        $doc = ReleaseDocument::create([
            'release_id' => $release->id, 'uploaded_by' => $lead->id, 'original_name' => 'spec.txt',
            'path' => $path, 'mime_type' => 'text/plain', 'size' => 70000,
        ]);

        $part = ['summary' => 'Coupons.', 'key_points' => ['Coupons'], 'requirements' => [['text' => 'Support coupons', 'priority' => 'must']],
            'suggested_tasks' => [['title' => 'Build coupon field', 'description' => null, 'phase' => 'development']], 'open_questions' => [], 'risks' => []];
        $this->claude->respondUsing(fn () => $part);

        Queue::fake();
        $id = $this->actingAs($lead)->postJson(route('ai.insights.store'), ['kind' => 'document_insights', 'subject_id' => $doc->id, 'release_id' => $release->id])
            ->assertOk()->json('id');
        (new GenerateAiInsight($id))->handle(app(AiInsightService::class));

        $insight = AiInsight::find($id);
        $this->assertSame('ready', $insight->status, (string) $insight->error);
        $this->assertGreaterThan(2, count($this->claude->requests)); // two+ chunks plus the merge
        $this->assertSame('must', $insight->payload['requirements'][0]['priority']);

        $this->actingAs($lead)->postJson(route('ai.documents.tasks', [$release, $doc]), ['tasks' => [['title' => 'Build coupon field', 'phase' => 'development']]])
            ->assertOk()->assertJsonPath('created', 1);
        $this->assertSame($release->id, Task::where('title', 'Build coupon field')->sole()->release_id);

        $this->actingAs($this->user(User::ROLE_VIEWER))->postJson(route('ai.documents.tasks', [$release, $doc]), ['tasks' => [['title' => 'x']]])->assertForbidden();
    }

    public function test_unsupported_document_types_fail_with_a_clear_message(): void
    {
        Storage::fake('local');
        $release = $this->release();
        Storage::disk('local')->put('releases/x/deck.pptx', 'binary');
        $doc = ReleaseDocument::create([
            'release_id' => $release->id, 'uploaded_by' => $this->user(User::ROLE_ADMIN)->id, 'original_name' => 'deck.pptx',
            'path' => 'releases/x/deck.pptx', 'mime_type' => 'application/vnd.ms-powerpoint', 'size' => 6,
        ]);
        $insight = AiInsight::create(['kind' => 'document_insights', 'subject_type' => $doc->getMorphClass(), 'subject_id' => $doc->id, 'status' => 'pending']);

        $job = new GenerateAiInsight($insight->id);
        $job->withFakeQueueInteractions();
        $job->handle(app(AiInsightService::class));

        $job->assertFailed();
        $this->assertSame([], $this->claude->requests);
    }

    // -- #8 Schedule assistant -------------------------------------------------

    public function test_schedule_suggestions_are_repaired_into_valid_contiguous_phases(): void
    {
        $start = Carbon::parse('2026-07-06');
        $end = Carbon::parse('2026-07-31');

        // Out-of-order, out-of-window and missing dates still come back valid.
        $phases = ScheduleAssistant::repair([
            'development' => ['start' => '2026-07-01', 'end' => '2026-08-20'],
            'qa' => ['start' => 'x', 'end' => '2026-07-10'],
        ], $start, $end);

        $this->assertSame('2026-07-06', $phases['development']['start']);
        $this->assertSame('2026-07-31', $phases['release']['end']);
        $prevEnd = null;
        foreach ($phases as $p) {
            $this->assertLessThanOrEqual($p['end'], $p['start']);
            if ($prevEnd) {
                $this->assertSame(Carbon::parse($prevEnd)->addDay()->toDateString(), $p['start']);
            }
            $prevEnd = $p['end'];
        }
    }

    public function test_schedule_endpoint_is_for_release_planners(): void
    {
        $payload = ['start_date' => '2026-07-06', 'end_date' => '2026-07-31', 'team_id' => $this->team->id, 'off_days' => ['2026-07-10', ['date' => '2026-07-13']]];
        $this->claude->respond(['phases' => [
            'development' => ['start' => '2026-07-06', 'end' => '2026-07-17'], 'qa' => ['start' => '2026-07-20', 'end' => '2026-07-24'],
            'retest' => ['start' => '2026-07-27', 'end' => '2026-07-29'], 'release' => ['start' => '2026-07-30', 'end' => '2026-07-31'],
        ], 'rationale' => 'By working days.', 'warnings' => []]);

        $this->actingAs($this->user(User::ROLE_TEAM_LEAD))->postJson(route('ai.schedule'), $payload)
            ->assertOk()->assertJsonPath('phases.qa.start', '2026-07-18')->assertJsonPath('working_days', 18);
        $this->assertStringContainsString('2026-07-13', $this->claude->prompt());

        $this->actingAs($this->user(User::ROLE_DEVELOPER, $this->team))->postJson(route('ai.schedule'), $payload)->assertForbidden();
    }
}
