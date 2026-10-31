<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\QuickLink;
use App\Models\Release;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickLinksTest extends TestCase
{
    use RefreshDatabase;

    private function release(): Release
    {
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);

        return Release::create([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => 'R',
            'year' => 2026, 'quarter' => 3, 'start_date' => '2026-07-10', 'end_date' => '2026-07-30',
        ]);
    }

    private function link(User $user, array $attrs = []): QuickLink
    {
        return QuickLink::create(array_merge([
            'user_id' => $user->id, 'label' => 'A link', 'url' => 'https://example.com', 'visibility' => 'private',
        ], $attrs));
    }

    public function test_user_can_add_private_and_shared_links_and_private_is_the_default(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_VIEWER]);

        $this->actingAs($user)->post(route('quick-links.store'), [
            'label' => 'Docs', 'url' => 'https://docs.example.com',
        ])->assertRedirect()->assertSessionHas('quick-links-open');

        $this->actingAs($user)->post(route('quick-links.store'), [
            'label' => 'CI', 'url' => 'https://ci.example.com', 'visibility' => 'shared',
        ])->assertRedirect();

        $this->assertSame(2, QuickLink::count());
        $this->assertSame('private', QuickLink::where('label', 'Docs')->first()->visibility);
        $this->assertSame('shared', QuickLink::where('label', 'CI')->first()->visibility);
        $this->assertSame($user->id, QuickLink::first()->user_id);
    }

    public function test_invalid_urls_and_missing_label_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('quick-links.store'), ['label' => '', 'url' => 'https://ok.example.com'])
            ->assertSessionHasErrors('label');
        $this->actingAs($user)->post(route('quick-links.store'), ['label' => 'Bad', 'url' => 'not-a-url'])
            ->assertSessionHasErrors('url');
        $this->actingAs($user)->post(route('quick-links.store'), ['label' => 'Evil', 'url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('url');

        $this->assertSame(0, QuickLink::count());
    }

    public function test_dev_and_qa_can_only_create_private_links(): void
    {
        foreach ([User::ROLE_DEVELOPER, User::ROLE_QA] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->post(route('quick-links.store'), [
                'label' => 'Mine', 'url' => 'https://example.com', 'visibility' => 'shared',
            ])->assertSessionHasErrors('visibility');

            $this->actingAs($user)->post(route('quick-links.store'), [
                'label' => 'Mine', 'url' => 'https://example.com',
            ])->assertRedirect();
        }

        $this->assertSame(2, QuickLink::count());
        $this->assertSame(0, QuickLink::where('visibility', 'shared')->count());
    }

    public function test_drawer_shows_own_and_shared_links_by_role(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_VIEWER]);
        $this->link($author, ['label' => 'Author private secret']);
        $this->link($author, ['label' => 'Author shared tool', 'visibility' => 'shared']);

        // Full-access viewer sees the shared link but not the private one.
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get(route('notes.index'))
            ->assertOk()
            ->assertSee('Author shared tool')
            ->assertDontSee('Author private secret');

        // Dev sees neither — only their own links exist for them.
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->link($dev, ['label' => 'Dev private bookmark']);
        $this->actingAs($dev)->get(route('notes.index'))
            ->assertOk()
            ->assertSee('Dev private bookmark')
            ->assertDontSee('Author shared tool')
            ->assertDontSee('Author private secret');
    }

    public function test_only_author_can_edit_or_delete(): void
    {
        $author = User::factory()->create();
        $link = $this->link($author);
        $other = User::factory()->create(['role' => User::ROLE_ADMIN]); // not even admins

        $this->actingAs($other)->put(route('quick-links.update', $link), [
            'label' => 'hijack', 'url' => 'https://example.com',
        ])->assertForbidden();
        $this->actingAs($other)->delete(route('quick-links.destroy', $link))->assertForbidden();

        $this->actingAs($author)->put(route('quick-links.update', $link), [
            'label' => 'Renamed', 'url' => 'https://example.com', 'visibility' => 'shared',
        ])->assertRedirect();
        $this->assertSame('Renamed', $link->refresh()->label);
        $this->assertTrue($link->isShared());

        $this->actingAs($author)->delete(route('quick-links.destroy', $link))->assertRedirect();
        $this->assertSame(0, QuickLink::count());
    }

    public function test_drawer_toggle_is_present_on_any_page_for_all_roles(): void
    {
        foreach ([User::ROLE_DEVELOPER, User::ROLE_VIEWER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('notes.index'))
                ->assertOk()
                ->assertSee('Quick links');
        }
    }

    public function test_release_links_section_shows_visible_links_and_add_attaches_release(): void
    {
        $release = $this->release();
        $someone = User::factory()->create(['role' => User::ROLE_VIEWER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->link($someone, ['label' => 'Their private staging', 'release_id' => $release->id]);
        $this->link($someone, ['label' => 'Shared board', 'visibility' => 'shared', 'release_id' => $release->id]);

        $this->actingAs($admin)->get(route('releases.show', $release))
            ->assertOk()
            ->assertSee('Shared board')
            ->assertDontSee('Their private staging');

        // Adding from the release page attaches the release.
        $this->actingAs($admin)->post(route('quick-links.store'), [
            'label' => 'Spec doc', 'url' => 'https://spec.example.com', 'visibility' => 'shared', 'release_id' => $release->id,
        ])->assertRedirect();

        $this->assertSame($release->id, QuickLink::where('label', 'Spec doc')->first()->release_id);
        $this->actingAs($admin)->get(route('releases.show', $release))->assertSee('Spec doc');
    }

    public function test_release_deletion_keeps_links_as_general(): void
    {
        $release = $this->release();
        $user = User::factory()->create();
        $link = $this->link($user, ['release_id' => $release->id]);

        $release->delete();

        $this->assertNull($link->refresh()->release_id);
        $this->assertSame(1, QuickLink::count());
    }
}
