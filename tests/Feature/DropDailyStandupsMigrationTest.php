<?php

namespace Tests\Feature;

use App\Models\MeetingNote;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Daily standup generation was removed; the notes it made are kept as ordinary meeting notes. */
class DropDailyStandupsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_11_14_100001_drop_daily_standups_table.php');
    }

    /** A generated standup as the removed feature stored it: no author, empty body, listing in daily_standups. */
    private function generatedNote(Team $team, array $content, string $body = ''): MeetingNote
    {
        $note = MeetingNote::create([
            'created_by' => null, 'title' => 'Platform standup · Thu, Nov 12', 'type' => 'standup',
            'meeting_date' => '2026-11-12', 'body' => $body, 'visibility' => 'everyone',
        ]);

        DB::table('daily_standups')->insert([
            'team_id' => $team->id, 'date' => '2026-11-12', 'meeting_note_id' => $note->id,
            'content' => json_encode($content), 'fingerprint' => str_repeat('a', 32), 'search_text' => '',
            'generated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $note;
    }

    public function test_generated_notes_become_plain_attendee_only_notes_and_the_table_goes(): void
    {
        $migration = $this->migration();
        $migration->down();

        $team = Team::create(['name' => 'Platform', 'color' => '#0891b2']);
        $ana = User::factory()->create(['name' => 'Ana']);
        $note = $this->generatedNote($team, [
            'date' => '2026-11-12',
            'previous_date' => '2026-11-11',
            'counts' => ['people' => 2, 'filled' => 1, 'on_leave' => 1, 'not_filled' => 0],
            'people' => [[
                'id' => $ana->id, 'name' => 'Ana <script>',
                'previous' => ['result' => '<div>Shipped the login fix</div>'],
                'today' => ['plan' => '<ul><li>Review the export</li></ul>'],
            ]],
            'on_leave' => [['id' => 99, 'name' => 'Ben', 'leave' => 'Sick leave', 'full_day' => true]],
            'not_filled' => [],
            'ai' => ['covers' => '2026-11-11', 'summary' => 'Login is fixed.', 'blockers' => [['member' => 'Ana', 'text' => 'Waiting on API keys']]],
        ], '<div>Agreed to demo on Friday</div>');
        $note->attendees()->sync([$ana->id]);
        $untouched = MeetingNote::create(['created_by' => $ana->id, 'title' => 'Retro', 'meeting_date' => '2026-11-12', 'body' => '<div>Keep</div>']);

        $migration->up();

        $this->assertFalse(Schema::hasTable('daily_standups'));

        $note->refresh();
        $this->assertSame(MeetingNote::TYPE_DEFAULT, $note->type);
        $this->assertSame(MeetingNote::VISIBILITY_ATTENDEES, $note->visibility);
        $this->assertNull($note->created_by);
        $this->assertSame([$ana->id], $note->attendees()->pluck('users.id')->all());
        foreach (['1 of 2 filled in', 'Ben (Sick leave)', 'Login is fixed.', 'Ana: Waiting on API keys',
            'Shipped the login fix', 'Review the export', 'Did (Wed, Nov 11)', 'Today (Thu, Nov 12)', 'Agreed to demo on Friday'] as $text) {
            $this->assertStringContainsString($text, $note->body);
        }
        $this->assertStringNotContainsString('<script>', $note->body);

        $this->assertSame('<div>Keep</div>', $untouched->fresh()->body);
    }

    public function test_a_converted_note_still_opens_and_is_read_only(): void
    {
        $migration = $this->migration();
        $migration->down();

        $team = Team::create(['name' => 'Platform', 'color' => '#0891b2']);
        $note = $this->generatedNote($team, ['people' => [], 'on_leave' => [], 'not_filled' => [], 'counts' => []]);
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);

        $migration->up();

        $this->actingAs($lead)->get(route('meeting-notes.show', $note))->assertOk()->assertSee('From the tasksheet');
        $this->actingAs($lead)->get(route('meeting-notes.index'))->assertOk()->assertSee('Platform standup');
        Sanctum::actingAs($lead);
        $this->getJson("/api/v1/meeting-notes/{$note->id}")->assertOk()
            ->assertJsonPath('data.author', null)
            ->assertJsonPath('data.can_edit', false)
            ->assertJsonMissingPath('data.standup');
        $this->actingAs($lead)->get(route('meeting-notes.edit', $note))->assertForbidden();
    }

    public function test_nothing_generates_standups_any_more(): void
    {
        $this->assertArrayNotHasKey('standup', MeetingNote::TYPES);
        $this->assertArrayNotHasKey('standup:generate', $this->app->make(Kernel::class)->all());
        $this->assertFalse(app('router')->has('standups.store'));
        $this->assertFalse(app('router')->has('api-v1.standups.store'));
    }
}
