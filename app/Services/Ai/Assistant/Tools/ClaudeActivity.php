<?php

namespace App\Services\Ai\Assistant\Tools;

use App\Http\Controllers\ClaudeActivityController;
use App\Models\ClaudeActivityLog;
use App\Models\ClaudeDeviceOwner;
use App\Models\User;
use App\Services\Ai\Assistant\ReadTool;
use App\Services\Ai\Assistant\ToolOutput;
use App\Services\Ai\ClaudeHabitAnalyst;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Claude Code usage from the Claude activity monitor: which devices and people
 * used it, when, on which projects and models, and what they asked. Admins and
 * the CTO only.
 */
class ClaudeActivity extends ReadTool
{
    private const MAX_PROMPTS = 60;

    private const MAX_SESSIONS = 40;

    public function name(): string
    {
        return 'claude_activity';
    }

    public function description(): string
    {
        return 'Claude Code usage recorded by the Claude activity monitor on monitored devices. '
            .'`view` is "overview" (default: totals, each device with its linked person, models, projects, busiest days), '
            .'"prompts" (the prompts themselves; only their start is stored) or "sessions" (each session with its length, project and prompt count). '
            .'Narrow with `from`/`to` (dates, default the last 7 days), `person` (a user id or name; covers their linked devices), '
            .'`device` (a computer name or device id) and `search` (text in prompts, folders and pages). Claude\'s answers are never recorded.';
    }

    public function status(): string
    {
        return 'Checking Claude activity';
    }

    public function availableTo(User $user): bool
    {
        return self::allowed($user);
    }

    public static function allowed(User $user): bool
    {
        return $user->isAdmin() || $user->role === User::ROLE_CTO;
    }

    public function properties(): array
    {
        return [
            'view' => ['type' => 'string', 'description' => 'overview, prompts or sessions'],
            'from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
            'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
            'person' => ['type' => 'string', 'description' => 'user id or name'],
            'device' => ['type' => 'string', 'description' => 'computer name or device id'],
            'search' => ['type' => 'string'],
        ];
    }

    public function run(array $input, User $user): ToolOutput
    {
        if (! self::allowed($user)) {
            $this->fail('Only admins and the CTO can see Claude activity.');
        }

        try {
            $to = filled($input['to'] ?? null) ? Carbon::parse($input['to'])->endOfDay() : now()->endOfDay();
            $from = filled($input['from'] ?? null) ? Carbon::parse($input['from'])->startOfDay() : $to->copy()->subDays(6)->startOfDay();
        } catch (\Throwable) {
            $this->fail('Give dates as YYYY-MM-DD.');
        }
        if ($from->gt($to)) {
            $this->fail('`from` is after `to`.');
        }

        $owners = ClaudeDeviceOwner::with('user:id,name')->get()->keyBy('device_id');
        [$query, $scope] = $this->scoped($input, $owners, $from, $to);

        $heading = "Claude activity {$from->toDateString()} to {$to->toDateString()}{$scope}.";
        $view = strtolower(trim((string) ($input['view'] ?? 'overview')));

        return match ($view) {
            'prompts' => $this->prompts($query, $owners, $heading),
            'sessions' => $this->sessions($query, $owners, $heading),
            default => $this->overview($query, $owners, $heading),
        };
    }

    /** @return array{0: Builder, 1: string} the events in scope, and words describing the scope */
    private function scoped(array $input, Collection $owners, Carbon $from, Carbon $to): array
    {
        $query = ClaudeActivityLog::query()->whereBetween('created_at', [$from, $to]);
        $words = [];

        if (filled($person = trim((string) ($input['person'] ?? '')))) {
            $match = ctype_digit($person)
                ? User::whereKey((int) $person)->get(['id', 'name'])
                : User::whereRaw("name LIKE ? ESCAPE '!'", [self::like($person)])->get(['id', 'name']);
            if ($match->count() !== 1) {
                $this->fail($match->isEmpty() ? "No person matches \"{$person}\"." : "Several people match \"{$person}\": ".$match->map(fn ($u) => "{$u->name} (id {$u->id})")->implode(', ').'. Pass an id.');
            }
            $devices = $owners->where('user_id', $match->first()->id)->keys()->all();
            if ($devices === []) {
                $this->fail("{$match->first()->name} has no device linked on the Claude activity page, so their activity can't be told apart.");
            }
            $query->whereIn('device_id', $devices);
            $words[] = "for {$match->first()->name}";
        }

        if (filled($device = trim((string) ($input['device'] ?? '')))) {
            $query->where(fn ($q) => $q->where('device_id', $device)->orWhereRaw("hostname LIKE ? ESCAPE '!'", [self::like($device)]));
            $words[] = "on device \"{$device}\"";
        }

        if (filled($search = trim((string) ($input['search'] ?? '')))) {
            $like = self::like($search);
            $query->where(fn ($q) => $q->whereRaw("prompt_excerpt LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("cwd LIKE ? ESCAPE '!'", [$like])->orWhereRaw("url LIKE ? ESCAPE '!'", [$like]));
            $words[] = "matching \"{$search}\"";
        }

        return [$query, $words === [] ? '' : ' '.implode(' ', $words)];
    }

    /** A LIKE pattern matching $text literally, escaped with "!" (SQLite has no default escape). */
    private static function like(string $text): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text).'%';
    }

    private function overview(Builder $query, Collection $owners, string $heading): ToolOutput
    {
        $events = (clone $query)->get(['event', 'session_id', 'device_id', 'hostname', 'model', 'cwd', 'ip', 'created_at']);
        if ($events->isEmpty()) {
            return new ToolOutput("{$heading}\nNo activity recorded.");
        }

        $prompts = $events->where('event', 'UserPromptSubmit');
        $refs = [];
        $devices = $events->groupBy(fn ($e) => $e->device_id ?? ClaudeActivityController::NO_DEVICE)
            ->map(function (Collection $rows, string $id) use ($owners, &$refs) {
                $ref = 'CD'.substr($id, 0, 8);
                $name = $rows->last()->hostname ?? 'Unknown host';
                $refs[$ref] = ['ref' => $ref, 'kind' => 'Claude device', 'title' => $name, 'url' => ClaudeActivityController::deviceUrl($id === ClaudeActivityController::NO_DEVICE ? null : $id)];

                return sprintf('[%s] %s; person %s; %d prompts, %d sessions, %d events; models %s; last seen %s.',
                    $ref, $name, $owners->get($id)?->user?->name ?? 'not linked',
                    $rows->where('event', 'UserPromptSubmit')->count(), $rows->pluck('session_id')->filter()->unique()->count(), $rows->count(),
                    $rows->pluck('model')->filter()->unique()->implode(', ') ?: 'unknown',
                    $rows->max('created_at')->format('Y-m-d H:i'));
            })->values();

        $byPerson = $prompts->groupBy(fn ($e) => $owners->get($e->device_id)?->user?->name ?? 'Devices not linked to a person')
            ->map(fn (Collection $rows, string $name) => "{$name}: {$rows->count()} prompts")->values();
        $projects = $prompts->countBy(fn ($e) => ClaudeHabitAnalyst::project($e->cwd))->sortDesc()->take(10)
            ->map(fn ($n, $p) => "{$p} ({$n})")->implode(', ');
        $models = $events->pluck('model')->filter()->countBy()->sortDesc()->map(fn ($n, $m) => "{$m} ({$n} events)")->implode(', ');
        $days = $prompts->countBy(fn ($e) => $e->created_at->toDateString())->sortKeys()->map(fn ($n, $d) => "{$d}: {$n}")->implode(', ');

        return new ToolOutput(implode("\n", array_filter([
            $heading,
            sprintf('Totals: %d prompts, %d sessions, %d devices, %d events, %d browser actions.',
                $prompts->count(), $events->pluck('session_id')->filter()->unique()->count(), $devices->count(), $events->count(), $events->where('event', 'PreToolUse')->count()),
            'Prompts by person: '.$byPerson->implode('; ').'.',
            "Devices:\n".$devices->implode("\n"),
            $models ? "Models: {$models}." : null,
            $projects ? "Projects by prompts: {$projects}." : null,
            $days ? "Prompts per day: {$days}." : null,
        ])), $refs);
    }

    private function prompts(Builder $query, Collection $owners, string $heading): ToolOutput
    {
        $total = (clone $query)->where('event', 'UserPromptSubmit')->count();
        $rows = (clone $query)->where('event', 'UserPromptSubmit')->latest('id')->limit(self::MAX_PROMPTS)
            ->get(['device_id', 'hostname', 'session_id', 'cwd', 'model', 'prompt_excerpt', 'created_at']);

        $lines = $rows->map(fn ($e) => sprintf('- %s · %s (%s) · project %s · %s · session %s: %s',
            $e->created_at->format('Y-m-d H:i'), $owners->get($e->device_id)?->user?->name ?? 'not linked', $e->hostname ?? 'unknown host',
            ClaudeHabitAnalyst::project($e->cwd), $e->model ?? 'model unknown', substr((string) $e->session_id, 0, 8),
            preg_replace('/\s+/', ' ', trim((string) $e->prompt_excerpt))));

        $more = $total > $rows->count() ? " Showing the latest {$rows->count()} of {$total}; narrow the dates or search for the rest." : '';

        return new ToolOutput("{$heading} {$total} prompts (only the start of each is stored).{$more}\n".($lines->implode("\n") ?: 'None.'));
    }

    private function sessions(Builder $query, Collection $owners, string $heading): ToolOutput
    {
        $events = (clone $query)->whereNotNull('session_id')->orderBy('id')
            ->get(['event', 'session_id', 'device_id', 'hostname', 'cwd', 'model', 'created_at']);
        $sessions = $events->groupBy('session_id')->sortByDesc(fn (Collection $s) => $s->last()->created_at);

        $lines = $sessions->take(self::MAX_SESSIONS)->map(function (Collection $s, string $id) use ($owners) {
            $first = $s->first();

            return sprintf('- %s %s to %s (%d min) · %s (%s) · project %s · %s · %d prompts · session %s',
                $first->created_at->format('Y-m-d'), $first->created_at->format('H:i'), $s->last()->created_at->format('H:i'),
                $first->created_at->diffInMinutes($s->last()->created_at),
                $owners->get($first->device_id)?->user?->name ?? 'not linked', $first->hostname ?? 'unknown host',
                ClaudeHabitAnalyst::project($first->cwd), $s->pluck('model')->filter()->last() ?? 'model unknown',
                $s->where('event', 'UserPromptSubmit')->count(), substr($id, 0, 8));
        })->values();

        $more = $sessions->count() > self::MAX_SESSIONS ? ' Showing the latest '.self::MAX_SESSIONS.'.' : '';

        return new ToolOutput("{$heading} {$sessions->count()} sessions.{$more}\n".($lines->implode("\n") ?: 'None.'));
    }
}
