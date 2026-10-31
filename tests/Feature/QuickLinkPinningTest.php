<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\QuickLink;
use App\Models\Release;
use App\Models\Team;
use App\Models\User;
use App\Services\QuickLinkService;
use App\Services\ReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickLinkPinningTest extends TestCase
{
    use RefreshDatabase;

    private function release(string $name = 'R'): Release
    {
        $project = Project::create(['name' => 'P '.$name, 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T '.$name, 'color' => '#0891b2']);

        return Release::create([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => $name,
            'year' => 2026, 'quarter' => 3, 'start_date' => '2026-07-10', 'end_date' => '2026-07-30',
        ]);
    }

    private function link(User $user, array $attrs = []): QuickLink
    {
        return QuickLink::create(array_merge([
            'user_id' => $user->id, 'label' => 'A link', 'url' => 'https://example.com', 'visibility' => 'private',
        ], $attrs));
    }

    /** Labels in the order the drawer would render them, for the given viewer. */
    private function drawerOrder(User $viewer, string $bucket = 'mine'): array
    {
        return app(QuickLinkService::class)
            ->partitionedFor($viewer)[$bucket]
            ->pluck('label')->all();
    }

    // ------------------------------------------------------------- pinning

    public function test_user_can_pin_and_unpin_their_own_link(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_VIEWER]);
        $link = $this->link($user);

        $this->actingAs($user)->post(route('quick-links.pin', $link))->assertRedirect();
        $this->assertTrue($link->fresh()->isPinnedBy($user));

        $this->actingAs($user)->post(route('quick-links.pin', $link))->assertRedirect();
        $this->assertFalse($link->fresh()->isPinnedBy($user));
    }

    public function test_pinning_a_shared_link_is_private_to_the_pinner(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $pinner = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $link = $this->link($author, ['label' => 'Team board', 'visibility' => 'shared']);

        $this->actingAs($pinner)->post(route('quick-links.pin', $link))->assertRedirect();

        $this->assertTrue($link->fresh()->isPinnedBy($pinner));
        // The author sees no change from someone else's pin.
        $this->assertFalse($link->fresh()->isPinnedBy($author));
    }

    public function test_pinning_confers_no_edit_or_delete_rights(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $pinner = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $link = $this->link($author, ['visibility' => 'shared']);

        $this->actingAs($pinner)->post(route('quick-links.pin', $link))->assertRedirect();

        $this->actingAs($pinner)
            ->put(route('quick-links.update', $link), ['label' => 'Hijacked', 'url' => 'https://evil.example.com'])
            ->assertForbidden();
        $this->actingAs($pinner)->delete(route('quick-links.destroy', $link))->assertForbidden();

        $this->assertDatabaseHas('quick_links', ['id' => $link->id, 'label' => 'A link']);
    }

    public function test_cannot_pin_a_link_the_user_cannot_see(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $stranger = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $private = $this->link($author, ['visibility' => 'private']);

        $this->actingAs($stranger)->post(route('quick-links.pin', $private))->assertForbidden();

        // Limited roles never see others' shared links, so they cannot pin one.
        $shared = $this->link($author, ['visibility' => 'shared']);
        $developer = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->actingAs($developer)->post(route('quick-links.pin', $shared))->assertForbidden();

        $this->assertSame(0, \DB::table('quick_link_user')->count());
    }

    // ------------------------------------------------------------ ordering

    public function test_drawer_orders_pinned_then_private_then_shared_then_newest(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->link($user, ['label' => 'Private old']);
        $this->link($user, ['label' => 'Private new']);
        $this->link($user, ['label' => 'Shared old', 'visibility' => 'shared']);
        $toPin = $this->link($user, ['label' => 'Shared new', 'visibility' => 'shared']);

        // Unpinned: private band first, newest within each band.
        $this->assertSame(
            ['Private new', 'Private old', 'Shared new', 'Shared old'],
            $this->drawerOrder($user)
        );

        // A pinned *shared* link outranks unpinned private ones.
        $toPin->pinnedBy()->attach($user->id);

        $this->assertSame(
            ['Shared new', 'Private new', 'Private old', 'Shared old'],
            $this->drawerOrder($user)
        );
    }

    public function test_pins_do_not_reorder_the_drawer_for_other_users(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $other = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->link($author, ['label' => 'First shared', 'visibility' => 'shared']);
        $second = $this->link($author, ['label' => 'Second shared', 'visibility' => 'shared']);

        $second->pinnedBy()->attach($other->id);

        // Ordering for the author is untouched by the other user's pin.
        $this->assertSame(['Second shared', 'First shared'], $this->drawerOrder($author));
        $this->assertSame(['Second shared', 'First shared'], $this->drawerOrder($other, 'shared'));
    }

    // ------------------------------------------------- completed releases

    public function test_completing_a_release_removes_its_links_from_the_drawer(): void
    {
        $release = $this->release('Checkout');
        $other = $this->release('Search');
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->link($user, ['label' => 'Release link', 'release_id' => $release->id]);
        $this->link($user, ['label' => 'Other release link', 'release_id' => $other->id]);
        $this->link($user, ['label' => 'General link']);

        $this->assertSame(
            ['General link', 'Other release link', 'Release link'],
            $this->drawerOrder($user)
        );

        $release->update(['completed_at' => now()]);

        // Only the completed release's link goes; the others are untouched.
        $this->assertSame(['General link', 'Other release link'], $this->drawerOrder($user));
    }

    public function test_reopening_a_release_restores_its_links_with_the_pin_intact(): void
    {
        $release = $this->release();
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $link = $this->link($user, ['label' => 'Release link', 'release_id' => $release->id]);
        $link->pinnedBy()->attach($user->id);

        $release->update(['completed_at' => now()]);
        $this->assertSame([], $this->drawerOrder($user));

        app(ReleaseService::class)->reopen($release);

        $this->assertSame(['Release link'], $this->drawerOrder($user));
        $this->assertTrue($link->fresh()->isPinnedBy($user));
    }

    public function test_the_link_survives_completion_and_stays_on_the_release_page(): void
    {
        $release = $this->release();
        $author = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $link = $this->link($author, ['label' => 'Runbook', 'visibility' => 'shared', 'release_id' => $release->id]);

        $release->update(['completed_at' => now()]);

        // Not deleted…
        $this->assertDatabaseHas('quick_links', ['id' => $link->id]);

        // …and the release's own page still lists it, which is how the author
        // can still reach it while it is out of the drawer (design decision 3).
        $this->actingAs($author)->get(route('releases.show', $release))
            ->assertOk()->assertSee('Runbook');
    }

    // ------------------------------------------------------------- the API

    public function test_api_pin_toggles_and_reports_the_resulting_state(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $pinner = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $link = $this->link($author, ['visibility' => 'shared']);

        $this->actingAs($pinner, 'sanctum')
            ->postJson("/api/v1/quick-links/{$link->id}/pin")
            ->assertOk()->assertJsonPath('data.is_pinned', true);

        $this->actingAs($pinner, 'sanctum')
            ->postJson("/api/v1/quick-links/{$link->id}/pin")
            ->assertOk()->assertJsonPath('data.is_pinned', false);
    }

    public function test_api_index_matches_the_drawer_order_and_scoping(): void
    {
        $release = $this->release();
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->link($user, ['label' => 'Private one']);
        $pinned = $this->link($user, ['label' => 'Shared pinned', 'visibility' => 'shared']);
        $this->link($user, ['label' => 'On a release', 'release_id' => $release->id]);
        $pinned->pinnedBy()->attach($user->id);

        $release->update(['completed_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/quick-links')->assertOk();

        // Same order the drawer renders, and the completed release's link is gone.
        $this->assertSame(['Shared pinned', 'Private one'], array_column($response->json('data.mine'), 'label'));
        $this->assertTrue($response->json('data.mine.0.is_pinned'));
        $this->assertFalse($response->json('data.mine.1.is_pinned'));
    }

    public function test_is_pinned_is_the_requesting_users_own(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $pinner = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $link = $this->link($author, ['label' => 'Board', 'visibility' => 'shared']);
        $link->pinnedBy()->attach($pinner->id);

        // The author did not pin it, so their payload says so.
        $this->actingAs($author, 'sanctum')->getJson('/api/v1/quick-links')
            ->assertOk()->assertJsonPath('data.mine.0.is_pinned', false);

        $this->actingAs($pinner, 'sanctum')->getJson('/api/v1/quick-links')
            ->assertOk()->assertJsonPath('data.shared.0.is_pinned', true);
    }
}
