<?php

namespace App\Services\Ai\Assistant\Tools;

use App\Models\Event;
use App\Models\User;
use App\Services\Ai\Assistant\ReadTool;
use App\Services\Ai\Assistant\ToolOutput;
use App\Services\EventService;
use Illuminate\Support\Carbon;

class ListEvents extends ReadTool
{
    private const LIMIT = 40;

    public function __construct(private readonly EventService $events) {}

    public function name(): string
    {
        return 'list_events';
    }

    public function description(): string
    {
        return 'List calendar events this person may see in a date range. Defaults to the next 14 days. Use it to check for clashes before proposing a time.';
    }

    public function status(): string
    {
        return 'Checking the calendar';
    }

    public function properties(): array
    {
        return [
            'from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
            'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
            'show' => ['type' => 'string', 'enum' => array_keys(EventService::SHOW), 'description' => 'all, mine (created or attending) or shared. Defaults to all.'],
        ];
    }

    public function run(array $input, User $user): ToolOutput
    {
        try {
            $from = filled($input['from'] ?? null) ? Carbon::parse($input['from']) : today();
            $to = filled($input['to'] ?? null) ? Carbon::parse($input['to']) : $from->copy()->addDays(14);
        } catch (\Throwable) {
            $this->fail('Dates must look like YYYY-MM-DD.');
        }

        $lines = $this->events->inWindow($from, $to, ['show' => $input['show'] ?? null], $user)
            ->take(self::LIMIT)->map(fn (Event $e) => self::eventLine($e))->values()->all();

        return self::output("Events {$from->toDateString()} to {$to->toDateString()}:", $lines);
    }
}
