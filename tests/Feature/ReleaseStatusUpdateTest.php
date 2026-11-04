<?php

namespace Tests\Feature;

use App\Models\ReleaseStatusUpdate;
use App\Models\Task;
use App\Models\User;
use App\Notifications\ReleaseStatusChanged;
use App\Services\ReleaseStatusService;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Ai\AiTestCase;

/** Leads post how a release is going; Claude can draft it; nothing saves until a lead posts. */
class ReleaseStatusUpdateTest extends AiTestCase
{
    public function test_a_lead_posts_an_update_and_it_shows_on_the_page(): void
    {
        $lead = $this->user(User::ROLE_TECH_LEAD);
        $release = $this->release();

        $this->actingAs($lead)->post(route('releases.status-updates.store', $release), ['status' => 'at_risk', 'note' => 'QA is behind.'])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('release_status_updates', ['release_id' => $release->id, 'author_id' => $lead->id, 'status' => 'at_risk', 'note' => 'QA is behind.']);
        $this->get(route('releases.show', $release))->assertOk()->assertSee('At risk')->assertSee('QA is behind.');
    }

    public function test_it_needs_a_valid_status_and_a_note(): void
    {
        $release = $this->release();
        $this->actingAs($this->user(User::ROLE_ADMIN));

        $this->post(route('releases.status-updates.store', $release), ['status' => 'great', 'note' => ''])
            ->assertSessionHasErrors(['status', 'note']);
        $this->post(route('releases.status-updates.store', $release), ['status' => 'on_track', 'note' => str_repeat('x', 2001)])
            ->assertSessionHasErrors('note');

        $this->assertSame(0, ReleaseStatusUpdate::count());
    }

    public function test_only_leads_can_post_and_only_leads_see_the_form(): void
    {
        $release = $this->release();
        $release->team->members()->attach($dev = $this->user(User::ROLE_DEVELOPER));

        foreach ([$dev, $this->user(User::ROLE_QA, $this->team), $this->user(User::ROLE_VIEWER)] as $user) {
            $this->actingAs($user)->post(route('releases.status-updates.store', $release), ['status' => 'on_track', 'note' => 'Hi'])->assertForbidden();
            $this->get(route('releases.show', $release))->assertOk()->assertDontSee('Post an update');
        }

        $this->actingAs($this->user(User::ROLE_TEAM_LEAD))->get(route('releases.show', $release))->assertSee('Post an update');
    }

    public function test_everyone_who_can_see_the_release_can_read_the_history_newest_first(): void
    {
        $release = $this->release();
        $lead = $this->user(User::ROLE_ADMIN);
        $service = app(ReleaseStatusService::class);

        $this->travelTo(now()->subDay());
        $service->post($release, $lead, 'on_track', 'First note');
        $this->travelBack();
        $service->post($release, $lead, 'at_risk', 'Second note');

        $this->assertSame(['Second note', 'First note'], $service->history($release)->pluck('note')->all());

        $this->actingAs($this->user(User::ROLE_DEVELOPER, $this->team))->get(route('releases.show', $release))
            ->assertOk()->assertSee('Second note')->assertSee('Earlier updates (1)')->assertSee('First note');
    }

    public function test_marking_a_release_at_risk_tells_its_lead_and_org_leads_but_not_the_author(): void
    {
        Notification::fake();
        $teamLead = $this->user(User::ROLE_TEAM_LEAD);
        $this->team->update(['team_lead_id' => $teamLead->id]);
        $otherLead = $this->user(User::ROLE_TEAM_LEAD);
        $cto = $this->user(User::ROLE_CTO);
        $author = $this->user(User::ROLE_ADMIN);
        $dev = $this->user(User::ROLE_DEVELOPER, $this->team);
        $inactive = $this->user(User::ROLE_ADMIN);
        $inactive->update(['deactivated_at' => now()]);

        app(ReleaseStatusService::class)->post($this->release(), $author, 'off_track', 'It will slip.');

        Notification::assertSentTo([$teamLead, $cto], ReleaseStatusChanged::class);
        Notification::assertNotSentTo([$author, $otherLead, $dev, $inactive], ReleaseStatusChanged::class);
    }

    public function test_routine_updates_tell_nobody(): void
    {
        Notification::fake();
        $this->user(User::ROLE_CTO);
        $author = $this->user(User::ROLE_ADMIN);
        $release = $this->release();
        $service = app(ReleaseStatusService::class);

        $service->post($release, $author, 'on_track', 'Fine.');
        Notification::assertNothingSent();

        $service->post($release, $author, 'at_risk', 'Worried.');
        Notification::assertCount(1);

        $service->post($release, $author, 'at_risk', 'Still worried.');
        Notification::assertCount(1);

        $service->post($release, $author, 'off_track', 'It will slip.');
        Notification::assertCount(2);
    }

    public function test_opening_the_notification_goes_to_the_release(): void
    {
        $cto = $this->user(User::ROLE_CTO);
        $release = $this->release();

        app(ReleaseStatusService::class)->post($release, $this->user(User::ROLE_ADMIN), 'at_risk', 'Worried.');
        $notification = $cto->notifications()->sole();

        $this->assertSame('release_status', $notification->data['kind']);
        $this->assertStringContainsString('marked', $notification->data['message']);

        $this->actingAs($cto)->get(route('notifications.index'))->assertOk()->assertSee('at risk');
        $this->get(route('notifications.open', $notification->id))->assertRedirect(route('releases.show', $release));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_the_api_reads_and_posts_with_the_same_rules(): void
    {
        $release = $this->release();

        Sanctum::actingAs($lead = $this->user(User::ROLE_ADMIN));
        $this->postJson("/api/v1/releases/{$release->id}/status-updates", ['status' => 'at_risk', 'note' => 'Worried.'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'at_risk')
            ->assertJsonPath('data.status_label', 'At risk')
            ->assertJsonPath('data.author.id', $lead->id);
        $this->postJson("/api/v1/releases/{$release->id}/status-updates", ['status' => 'nope'])->assertUnprocessable();

        Sanctum::actingAs($this->user(User::ROLE_DEVELOPER, $this->team));
        $this->getJson("/api/v1/releases/{$release->id}/status-updates")->assertOk()->assertJsonPath('data.0.note', 'Worried.');
        $this->postJson("/api/v1/releases/{$release->id}/status-updates", ['status' => 'on_track', 'note' => 'x'])->assertForbidden();

        Sanctum::actingAs($this->user(User::ROLE_DEVELOPER, $this->otherTeam));
        $this->getJson("/api/v1/releases/{$release->id}/status-updates")->assertForbidden();
    }

    public function test_notifications_over_the_api_say_which_release(): void
    {
        $cto = $this->user(User::ROLE_CTO);
        $release = $this->release();
        app(ReleaseStatusService::class)->post($release, $this->user(User::ROLE_ADMIN), 'at_risk', 'Worried.');

        Sanctum::actingAs($cto);
        $this->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('data.0.kind', 'release_status')
            ->assertJsonPath('data.0.release_id', $release->id);
    }

    public function test_claude_drafts_from_the_facts_and_nothing_is_saved(): void
    {
        $release = $this->release();
        Task::create(['title' => 'Payment page', 'status' => 'todo', 'position' => 0, 'release_id' => $release->id, 'team_id' => $this->team->id, 'due_date' => today()->subDay()]);
        $this->actingAs($lead = $this->user(User::ROLE_TECH_LEAD));
        app(ReleaseStatusService::class)->post($release, $lead, 'on_track', 'Previous note');
        $this->claude->respond(['status' => 'at_risk', 'note' => 'One task left and time is short.']);

        $this->postJson(route('ai.release-status.draft', $release))->assertOk()
            ->assertJson(['status' => 'at_risk', 'note' => 'One task left and time is short.']);

        $sent = json_encode($this->claude->requests[0]);
        $this->assertStringContainsString('Payment page', $sent);
        $this->assertStringContainsString('Previous note', $sent);
        $this->assertStringContainsString('team_finishes_per_week', $sent);
        $this->assertSame(1, ReleaseStatusUpdate::count());
    }

    public function test_an_unexpected_status_from_claude_is_treated_as_at_risk(): void
    {
        $release = $this->release();
        $this->claude->respond(['status' => 'fantastic', 'note' => 'All good.']);

        $this->actingAs($this->user(User::ROLE_ADMIN))->postJson(route('ai.release-status.draft', $release))
            ->assertOk()->assertJsonPath('status', 'at_risk');
    }

    public function test_only_leads_can_ask_claude_for_a_draft(): void
    {
        $release = $this->release();

        foreach ([$this->user(User::ROLE_DEVELOPER, $this->team), $this->user(User::ROLE_VIEWER)] as $user) {
            $this->actingAs($user)->postJson(route('ai.release-status.draft', $release))->assertForbidden();
        }

        $this->assertSame([], $this->claude->requests);
    }

    public function test_the_draft_button_only_shows_when_claude_is_available(): void
    {
        $release = $this->release();
        $lead = $this->user(User::ROLE_ADMIN);

        $this->actingAs($lead)->get(route('releases.show', $release))->assertSee('Draft with AI');

        config(['services.anthropic.key' => null]);
        $this->get(route('releases.show', $release))->assertDontSee(route('ai.release-status.draft', $release));
    }

    public function test_notes_in_html_are_shown_as_text(): void
    {
        $release = $this->release();
        $lead = $this->user(User::ROLE_ADMIN);
        app(ReleaseStatusService::class)->post($release, $lead, 'on_track', '<script>alert(1)</script>');

        $this->actingAs($lead)->get(route('releases.show', $release))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }
}
