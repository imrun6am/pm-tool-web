<?php

namespace Tests\Feature;

use App\Models\MeetingNote;
use App\Models\Note;
use App\Models\User;
use App\Services\Ai\TextEnhancer;
use App\Services\Kpi\MissingApiKeyException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiEnhanceTest extends TestCase
{
    use RefreshDatabase;

    /** What the fake enhancer last received (null = not called). */
    private ?array $sent = null;

    private function fakeClaude(string $html = '<div>Polished</div>', ?\Throwable $error = null): void
    {
        $this->app->instance(TextEnhancer::class, new class($html, $error, $this->sent) implements TextEnhancer
        {
            public function __construct(private string $html, private ?\Throwable $error, private ?array &$sent) {}

            public function enhance(string $kind, string $html, array $context = []): string
            {
                $this->sent = compact('kind', 'html', 'context');
                if ($this->error) {
                    throw $this->error;
                }

                return $this->html;
            }
        });
    }

    public function test_returns_enhanced_html_for_a_note(): void
    {
        $this->fakeClaude('<h1>Today</h1><ul><li>Shipped checkout fix</li></ul>');

        $this->actingAs(User::factory()->create())
            ->postJson(route('ai.enhance'), ['kind' => 'note', 'body' => '<div>shiped checkout fix<script>alert(1)</script></div>'])
            ->assertOk()
            ->assertExactJson(['html' => '<h1>Today</h1><ul><li>Shipped checkout fix</li></ul>']);

        // Claude gets the user's HTML, already sanitized.
        $this->assertSame('note', $this->sent['kind']);
        $this->assertStringNotContainsString('<script', $this->sent['html']);
        $this->assertStringContainsString('shiped checkout fix', $this->sent['html']);
    }

    public function test_meeting_requests_pass_title_and_type_as_context(): void
    {
        $this->fakeClaude();

        $this->actingAs(User::factory()->create())
            ->postJson(route('ai.enhance'), [
                'kind' => 'meeting', 'body' => '<div>sara sends api spec fri</div>',
                'title' => 'Sprint planning', 'type' => 'Sync Up',
            ])
            ->assertOk();

        $this->assertSame('meeting', $this->sent['kind']);
        $this->assertSame(['title' => 'Sprint planning', 'type' => 'Sync Up'], $this->sent['context']);
    }

    public function test_empty_or_invalid_input_is_rejected_without_calling_claude(): void
    {
        $this->fakeClaude();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('ai.enhance'), ['kind' => 'note', 'body' => '<div><br></div>'])
            ->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->actingAs($user)->postJson(route('ai.enhance'), ['kind' => 'essay', 'body' => '<div>hi</div>'])
            ->assertUnprocessable()->assertJsonValidationErrors('kind');
        $this->actingAs($user)->postJson(route('ai.enhance'), ['kind' => 'note', 'body' => str_repeat('a', 20001)])
            ->assertUnprocessable()->assertJsonValidationErrors('body');

        $this->assertNull($this->sent);
    }

    public function test_guests_cannot_use_it(): void
    {
        $this->fakeClaude();

        $this->postJson(route('ai.enhance'), ['kind' => 'note', 'body' => '<div>hi</div>'])->assertUnauthorized();
        $this->assertNull($this->sent);
    }

    public function test_claude_failures_return_a_readable_503(): void
    {
        $user = User::factory()->create();

        $this->fakeClaude(error: new MissingApiKeyException('ANTHROPIC_API_KEY is not set.'));
        $this->actingAs($user)->postJson(route('ai.enhance'), ['kind' => 'note', 'body' => '<div>hi</div>'])
            ->assertStatus(503)
            ->assertJson(['message' => 'Claude isn’t configured (ANTHROPIC_API_KEY). Your text is unchanged.']);

        $this->fakeClaude(error: new \RuntimeException('Claude returned an unreadable response.'));
        $this->actingAs($user)->postJson(route('ai.enhance'), ['kind' => 'note', 'body' => '<div>hi</div>'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Claude couldn’t enhance this right now. Your text is unchanged — please try again.');
    }

    public function test_requests_are_rate_limited_per_user(): void
    {
        $this->fakeClaude();
        $user = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->postJson(route('ai.enhance'), ['kind' => 'note', 'body' => '<div>hi</div>'])->assertOk();
        }
        $this->actingAs($user)->postJson(route('ai.enhance'), ['kind' => 'note', 'body' => '<div>hi</div>'])->assertStatus(429);

        // Another user has their own allowance.
        $this->actingAs(User::factory()->create())->postJson(route('ai.enhance'), ['kind' => 'note', 'body' => '<div>hi</div>'])->assertOk();
    }

    public function test_enhance_button_appears_in_note_and_meeting_editors(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $note = Note::create(['user_id' => $user->id, 'date' => today(), 'body' => '<div>Mine</div>', 'visibility' => 'private']);
        $meeting = MeetingNote::create([
            'created_by' => $user->id, 'title' => 'Sync', 'meeting_date' => today()->toDateString(), 'body' => '<div>Minutes</div>',
        ]);

        // New-note composer and the existing note's inline editor.
        $this->actingAs($user)->get(route('notes.index'))->assertOk()
            ->assertSee('input: \'note-add-body\'', false)
            ->assertSee('input: \'note-edit-'.$note->id.'\'', false)
            ->assertSee('Enhance with Claude');

        // Meeting note create and edit, with title/type context.
        foreach ([route('meeting-notes.create'), route('meeting-notes.edit', $meeting)] as $url) {
            $this->actingAs($user)->get($url)->assertOk()
                ->assertSee('input: \'meeting-note-body\', kind: \'meeting\'', false)
                ->assertSee('titleField: \'title\', typeField: \'type\'', false);
        }
    }
}
