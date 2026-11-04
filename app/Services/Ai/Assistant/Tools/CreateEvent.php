<?php

namespace App\Services\Ai\Assistant\Tools;

use App\Http\Requests\EventRequest;
use App\Models\Event;
use App\Models\Release;
use App\Models\User;
use App\Services\Ai\Assistant\ActionDraft;
use App\Services\Ai\Assistant\ActionResult;
use App\Services\Ai\Assistant\ActionTool;
use App\Services\Ai\Assistant\FormRules;
use App\Services\EventService;
use Illuminate\Support\Carbon;

class CreateEvent extends ActionTool
{
    public function __construct(private readonly EventService $events) {}

    public function name(): string
    {
        return 'create_event';
    }

    public function description(): string
    {
        return 'Draft a calendar event for the person to confirm. Times are wall-clock, as they said them. Use find_people for attendee ids.';
    }

    public function properties(): array
    {
        return [
            'title' => ['type' => 'string'],
            'starts_at' => ['type' => 'string', 'description' => 'YYYY-MM-DD HH:MM'],
            'ends_at' => ['type' => 'string', 'description' => 'YYYY-MM-DD HH:MM. Assume an hour after the start unless told otherwise.'],
            'all_day' => ['type' => 'boolean'],
            'type' => ['type' => 'string', 'enum' => array_keys(Event::TYPES)],
            'visibility' => ['type' => 'string', 'enum' => array_keys(Event::VISIBILITIES), 'description' => 'shared: the team sees it (default). private: only the person and attendees.'],
            'location' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'attendees' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Ids of people to invite.'],
            'release_id' => ['type' => 'integer'],
        ];
    }

    public function required(): array
    {
        return ['title', 'starts_at'];
    }

    public function draft(array $input, User $user): ActionDraft
    {
        $payload = ['type' => 'meeting', 'visibility' => Event::VISIBILITY_SHARED] + $input;
        foreach (['starts_at', 'ends_at'] as $field) {
            if ($date = self::readable($payload[$field] ?? null)) {
                $payload[$field] = $date->format('Y-m-d H:i');
            }
        }

        $checked = FormRules::check(EventRequest::class, $payload, $user)->validated();

        $start = Carbon::parse($checked['starts_at']);
        $end = filled($checked['ends_at'] ?? null) ? Carbon::parse($checked['ends_at']) : null;
        $allDay = (bool) ($checked['all_day'] ?? false);
        $people = User::whereIn('id', $checked['attendees'] ?? [])->orderBy('name')->pluck('name')->all();

        return new ActionDraft($checked, [
            'kind' => 'event',
            'action' => 'Create event',
            'title' => $checked['title'],
            'tile' => ['month' => $start->format('M'), 'day' => $start->format('j'), 'weekday' => $start->format('D')],
            'when' => $allDay ? 'All day' : $start->format('H:i').($end ? ' – '.($end->isSameDay($start) ? $end->format('H:i') : $end->format('M j, H:i')) : ''),
            'fields' => array_values(array_filter([
                ['label' => 'Type', 'value' => Event::TYPES[$checked['type']]],
                ['label' => 'Who can see it', 'value' => Event::VISIBILITIES[$checked['visibility']]],
                filled($checked['location'] ?? null) ? ['label' => 'Where', 'value' => $checked['location']] : null,
                $people ? ['label' => 'Invites', 'value' => implode(', ', $people)] : null,
                filled($checked['release_id'] ?? null) ? ['label' => 'Release', 'value' => Release::find($checked['release_id'])?->name] : null,
            ])),
            'body' => $checked['description'] ?? null,
            'warning' => $start->isBefore(today()) ? 'This date has already passed.' : null,
        ]);
    }

    public function run(array $input, User $user): ActionResult
    {
        $event = $this->events->create($input, $input['attendees'] ?? [], $user);

        return new ActionResult('Event created', route('events.show', $event), "Created event [E{$event->id}].", [
            'ref' => "E{$event->id}", 'kind' => 'Event', 'title' => $event->title, 'url' => route('events.show', $event),
        ]);
    }
}
