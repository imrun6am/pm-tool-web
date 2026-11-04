<?php

namespace App\Services\Ai\Assistant\Tools;

use App\Http\Requests\NoteRequest;
use App\Models\Note;
use App\Models\User;
use App\Services\Ai\Assistant\ActionDraft;
use App\Services\Ai\Assistant\ActionResult;
use App\Services\Ai\Assistant\ActionTool;
use App\Services\Ai\Assistant\FormRules;
use App\Services\NoteService;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Carbon;

class AddNote extends ActionTool
{
    public function __construct(private readonly NoteService $notes) {}

    public function name(): string
    {
        return 'add_note';
    }

    public function description(): string
    {
        return 'Draft a daily note for the person to confirm. Private unless they say to share it.';
    }

    public function properties(): array
    {
        return [
            'text' => ['type' => 'string'],
            'visibility' => ['type' => 'string', 'enum' => array_keys(Note::VISIBILITIES), 'description' => 'Defaults to private. specific needs recipients.'],
            'recipients' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Ids of people to share with.'],
            'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD. Defaults to today.'],
        ];
    }

    public function required(): array
    {
        return ['text'];
    }

    public function draft(array $input, User $user): ActionDraft
    {
        $text = trim((string) ($input['text'] ?? ''));
        $paragraphs = collect(preg_split('/\n+/', $text))->filter()
            ->map(fn (string $line) => '<div>'.e($line).'</div>')->implode('');

        $checked = FormRules::check(NoteRequest::class, [
            'body' => $paragraphs,
            'visibility' => $input['visibility'] ?? Note::VISIBILITY_PRIVATE,
            'date' => self::day($input['date'] ?? null),
            'recipients' => $input['recipients'] ?? [],
        ], $user)->validated();

        $people = User::whereIn('id', $checked['recipients'] ?? [])->orderBy('name')->pluck('name')->all();
        $day = Carbon::parse($checked['date']);

        return new ActionDraft(['text' => $text, 'visibility' => $checked['visibility'], 'date' => $day->toDateString(), 'recipients' => $checked['recipients'] ?? []], [
            'kind' => 'change',
            'action' => 'Add note',
            'title' => 'Daily note',
            'eyebrow' => $day->isToday() ? 'Today' : self::dayLabel($day),
            'rows' => array_values(array_filter([
                ['label' => 'Who can see it', 'from' => null, 'to' => Note::VISIBILITIES[$checked['visibility']]],
                $people ? ['label' => 'Shared with', 'from' => null, 'to' => implode(', ', $people)] : null,
            ])),
            'body' => $text,
        ]);
    }

    public function run(array $input, User $user): ActionResult
    {
        $body = collect(preg_split('/\n+/', $input['text']))->filter()
            ->map(fn (string $line) => '<div>'.e($line).'</div>')->implode('');

        $note = $this->notes->create(
            ['body' => HtmlSanitizer::clean($body), 'visibility' => $input['visibility'], 'date' => $input['date']],
            $input['recipients'] ?? [],
            $user,
        );

        return new ActionResult('Note added', route('notes.index'), "Added note [N{$note->id}].", [
            'ref' => "N{$note->id}", 'kind' => 'Daily note', 'title' => 'Your note', 'url' => route('notes.index'),
        ]);
    }
}
