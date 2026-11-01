<?php

namespace Tests\Feature\Ai;

use App\Jobs\GenerateAiInsight;
use App\Models\AiInsight;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Services\Ai\Insights\AiInsightService;
use Illuminate\Support\Facades\Queue;

/** #4 standup digest, #10 performance evidence, #11 feedback drafts. */
class LeadAiTest extends AiTestCase
{
    private function entry(User $member, array $overrides = []): TasksheetEntry
    {
        $entry = new TasksheetEntry(['team_id' => $this->team->id, 'user_id' => $member->id, 'date' => today()->subWeekday()->toDateString()]);
        $entry->fill($overrides + ['plan' => '<div>Fix login</div>', 'result' => '<div>Fixed login, blocked on API keys</div>']);
        $entry->forceFill(array_intersect_key($overrides, ['standup_attended' => 1]))->save();

        return $entry;
    }

    // -- #4 Standup digest -----------------------------------------------------

    public function test_digest_counts_are_exact_and_claude_writes_the_rest(): void
    {
        $ana = $this->user(User::ROLE_DEVELOPER, $this->team);
        $ben = $this->user(User::ROLE_QA, $this->team);
        $this->user(User::ROLE_DEVELOPER, $this->team); // never filled in
        $this->entry($ana, ['standup_attended' => false]);
        $this->entry($ben, ['leave_type' => 'half_day']);

        Queue::fake();
        $this->artisan('ai:standup-digests')->assertSuccessful();
        Queue::assertPushed(GenerateAiInsight::class, 1);
        $insight = AiInsight::sole();

        $this->claude->respond(['summary' => 'Login fixed.', 'highlights' => [['member' => $ana->name, 'text' => 'Fixed login']],
            'blockers' => [['member' => $ana->name, 'text' => 'Waiting on API keys']], 'follow_ups' => ['Get API keys']]);
        (new GenerateAiInsight($insight->id))->handle(app(AiInsightService::class));

        $p = $insight->fresh()->payload;
        $this->assertSame(3, $p['members']);
        $this->assertSame([$ana->name], $p['missed_standup']);
        $this->assertSame([$ben->name], $p['on_leave']);
        $this->assertCount(1, $p['not_filled']);
        $this->assertSame('Waiting on API keys', $p['blockers'][0]['text']);
        $this->assertStringContainsString('blocked on API keys', $this->claude->prompt());

        // Already summarized: the morning run does not queue it again.
        $this->artisan('ai:standup-digests')->assertSuccessful();
        Queue::assertPushed(GenerateAiInsight::class, 1);
    }

    public function test_digests_are_for_the_teams_a_lead_may_oversee(): void
    {
        $teamLead = $this->user(User::ROLE_TEAM_LEAD);
        $this->otherTeam->update(['team_lead_id' => $teamLead->id]);
        Queue::fake();

        // A team lead: only the team they lead.
        $this->actingAs($teamLead)->postJson(route('ai.insights.store'), ['kind' => 'standup_digest', 'subject_id' => $this->team->id])->assertForbidden();
        $this->actingAs($teamLead)->postJson(route('ai.insights.store'), ['kind' => 'standup_digest', 'subject_id' => $this->otherTeam->id])->assertOk();
        // Developers never.
        $this->actingAs($this->user(User::ROLE_DEVELOPER, $this->team))
            ->postJson(route('ai.insights.store'), ['kind' => 'standup_digest', 'subject_id' => $this->team->id])->assertForbidden();
    }

    public function test_tasksheet_shows_the_digest_to_leads_only(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN))->get(route('tasksheet.index', ['team' => $this->team->id]))
            ->assertOk()->assertSee('Standup digest');
        $this->actingAs($this->user(User::ROLE_DEVELOPER, $this->team))->get(route('tasksheet.index', ['team' => $this->team->id]))
            ->assertOk()->assertDontSee('Standup digest');
    }

    // -- #10 Performance evidence ----------------------------------------------

    public function test_evidence_summarizes_the_period_and_never_asks_for_a_score(): void
    {
        $member = $this->user(User::ROLE_DEVELOPER, $this->team);
        $this->entry($member);
        $this->claude->respond(['summary' => 'Worked on login.', 'strengths' => [['text' => 'Fixed login', 'evidence' => 'Result on Monday']],
            'concerns' => [], 'data_gaps' => []]);

        $this->actingAs($this->user(User::ROLE_CTO))->postJson(route('ai.performance.evidence'), [
            'team_id' => $this->team->id, 'user_id' => $member->id, 'cadence' => 'weekly', 'date' => today()->subWeekday()->toDateString(),
        ])->assertOk()->assertJsonPath('strengths.0.text', 'Fixed login')->assertJsonPath('facts.days_logged', 1);

        $this->assertStringContainsString('never give, suggest or imply a score', $this->claude->requests[0]['system']);
    }

    public function test_evidence_is_scoped_like_performance_itself(): void
    {
        $member = $this->user(User::ROLE_DEVELOPER, $this->team);
        $payload = ['team_id' => $this->team->id, 'user_id' => $member->id, 'cadence' => 'daily', 'date' => today()->toDateString()];

        $otherLead = $this->user(User::ROLE_TEAM_LEAD);
        $this->otherTeam->update(['team_lead_id' => $otherLead->id]);
        $this->actingAs($otherLead)->postJson(route('ai.performance.evidence'), $payload)->assertForbidden();
        $this->actingAs($member)->postJson(route('ai.performance.evidence'), $payload)->assertForbidden();
        $this->actingAs($this->user(User::ROLE_VIEWER))->postJson(route('ai.performance.evidence'), $payload)->assertForbidden();

        // A member of another team is not reachable through this team.
        $stranger = $this->user(User::ROLE_DEVELOPER, $this->otherTeam);
        $this->actingAs($this->user(User::ROLE_ADMIN))->postJson(route('ai.performance.evidence'), ['user_id' => $stranger->id] + $payload)->assertNotFound();
        $this->assertSame([], $this->claude->requests);
    }

    // -- #11 Feedback drafts ---------------------------------------------------

    public function test_leads_get_a_sanitized_feedback_draft_from_the_unsaved_row(): void
    {
        $member = $this->user(User::ROLE_DEVELOPER, $this->team);
        $this->claude->respond(['feedback' => "Good progress on login.\nRaise the API key blocker early. <b>x</b>"]);

        $html = $this->actingAs($this->user(User::ROLE_TEAM_LEAD))->postJson(route('ai.tasksheet.feedback'), [
            'team_id' => $this->team->id, 'user_id' => $member->id, 'date' => today()->toDateString(),
            'plan' => '<div>Fix login</div>', 'result' => '<div>Done, waiting on keys</div>',
        ])->assertOk()->json('html');

        $this->assertStringContainsString('waiting on keys', $this->claude->prompt());
        $this->assertStringContainsString('<br', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }

    public function test_feedback_drafts_are_lead_only_and_need_content(): void
    {
        $member = $this->user(User::ROLE_DEVELOPER, $this->team);
        $payload = ['team_id' => $this->team->id, 'user_id' => $member->id, 'date' => today()->toDateString(), 'plan' => '<div>x</div>'];

        $this->actingAs($member)->postJson(route('ai.tasksheet.feedback'), $payload)->assertForbidden();
        $this->actingAs($this->user(User::ROLE_ADMIN))->postJson(route('ai.tasksheet.feedback'), ['plan' => ''] + $payload)->assertStatus(409);
    }
}
