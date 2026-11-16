<?php

namespace App\Http\Controllers;

use App\Models\ClaudeActivityLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Claude Code activity monitor: devices post hook events to store(); the
 * account owner reads them on index() and device(), and may delete them.
 * Nothing edits a row.
 */
class ClaudeActivityController extends Controller
{
    /** The device page address for events that came without a device id. */
    public const NO_DEVICE = 'none';

    /** The page sizes the event lists offer. */
    public const PAGE_SIZES = [25, 50, 100];

    /** POST /api/claude-activity — called by the hook on each monitored device. */
    public function store(Request $request): JsonResponse
    {
        $token = (string) config('claude_monitor.token');
        abort_if($token === '', 503, 'Claude activity monitor is not configured.');
        abort_unless(hash_equals($token, (string) $request->bearerToken()), 401);

        // The body is the hook's own JSON, forwarded untouched.
        $payload = json_decode($request->getContent(), true);
        abort_unless(is_array($payload) && is_string($payload['hook_event_name'] ?? null), 422, 'Not a Claude Code hook payload.');

        $str = fn ($value, int $max) => is_scalar($value) && (string) $value !== ''
            ? Str::limit(trim((string) $value), $max, '')
            : null;
        $header = fn (string $name, int $max) => $str($request->header($name), $max);

        $event = $payload['hook_event_name'];

        ClaudeActivityLog::create([
            'event' => $str($event, 32),
            'session_id' => $str($payload['session_id'] ?? null, 64),
            'device_id' => $header('X-Claude-Device-Id', 64),
            'hostname' => $header('X-Claude-Host', 255),
            'os_user' => $header('X-Claude-User', 128),
            'platform' => $header('X-Claude-Platform', 32),
            'entrypoint' => $header('X-Claude-Entrypoint', 64),
            'ip' => $request->ip(),
            'cwd' => $str($payload['cwd'] ?? null, 1024),
            'detail' => $str($payload['source'] ?? $payload['reason'] ?? null, 64),
            'prompt_excerpt' => $event === 'UserPromptSubmit'
                ? $str($payload['prompt'] ?? null, min(ClaudeActivityLog::PROMPT_EXCERPT_MAX, max(0, (int) config('claude_monitor.prompt_excerpt_length'))))
                : null,
            'transcript_path' => $str($payload['transcript_path'] ?? null, 1024),
        ]);

        return response()->json(['ok' => true], 201);
    }

    /** GET /claude-activity — every device, and the events of all of them. */
    public function index(Request $request): View
    {
        abort_unless(self::canView($request->user()), 403);

        $filters = $this->filters($request, withDevice: true);

        // One row per device id. The computer name isn't part of the grouping:
        // a Mac's name can change with the network it's on, and one device
        // must not split into several rows. The newest event names it.
        $devices = $this->nameDevices(ClaudeActivityLog::query()
            ->select('device_id')
            ->selectRaw('MIN(created_at) as first_seen, MAX(created_at) as last_seen, COUNT(*) as events')
            ->selectRaw('COUNT(DISTINCT session_id) as sessions, COUNT(DISTINCT hostname) as host_names')
            ->selectRaw('MAX(id) as latest_id')
            ->groupBy('device_id')
            ->orderByDesc('last_seen')
            ->get());

        // Every IP each device has posted from, newest first.
        $ipsByDevice = ClaudeActivityLog::query()
            ->select('device_id', 'ip', DB::raw('MAX(created_at) as last_seen'))
            ->whereNotNull('ip')
            ->groupBy('device_id', 'ip')
            ->orderByDesc('last_seen')
            ->get()
            ->groupBy('device_id');

        return view('claude-activity.index', [
            'logs' => $this->events(ClaudeActivityLog::query(), $filters),
            'devices' => $devices,
            'ipsByDevice' => $ipsByDevice,
            'filters' => $filters,
            'options' => $this->options(ClaudeActivityLog::query()),
            'configured' => (string) config('claude_monitor.token') !== '',
        ]);
    }

    /** GET /claude-activity/devices/{device} — one device's events. */
    public function device(Request $request, string $device): View
    {
        abort_unless(self::canView($request->user()), 403);

        $scope = fn () => self::forDevice(ClaudeActivityLog::query(), $device);
        abort_unless($scope()->exists(), 404);

        $filters = $this->filters($request, withDevice: false);

        $summary = $scope()
            ->selectRaw('MIN(created_at) as first_seen, MAX(created_at) as last_seen, COUNT(*) as events')
            ->selectRaw('COUNT(DISTINCT session_id) as sessions, COUNT(DISTINCT ip) as ips')
            ->selectRaw("SUM(CASE WHEN event = 'UserPromptSubmit' THEN 1 ELSE 0 END) as prompts")
            ->first();

        return view('claude-activity.device', [
            'device' => $device,
            'newest' => $scope()->latest('id')->first(),
            'hostNames' => $scope()->whereNotNull('hostname')->distinct()->orderBy('hostname')->pluck('hostname'),
            'summary' => $summary,
            'ips' => $scope()->select('ip', DB::raw('MAX(created_at) as last_seen'), DB::raw('COUNT(*) as events'))
                ->whereNotNull('ip')->groupBy('ip')->orderByDesc('last_seen')->get(),
            'logs' => $this->events($scope(), $filters),
            'filters' => $filters,
            'options' => $this->options($scope()),
        ]);
    }

    /** DELETE /claude-activity/{log} — one event. */
    public function destroy(Request $request, ClaudeActivityLog $log): RedirectResponse
    {
        abort_unless(self::canView($request->user()), 403);

        $log->delete();
        $this->noteDeletion($request, 1);

        return back()->with('success', 'Event deleted.');
    }

    /**
     * POST /claude-activity/delete — the ticked events (`ids`), or with `all`
     * every event matching the filters sent along (and the device, if any).
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        abort_unless(self::canView($request->user()), 403);

        $data = $request->validate([
            'all' => ['nullable', 'boolean'],
            'ids' => ['required_without:all', 'array', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        if ($request->boolean('all')) {
            $filters = $this->filters($request, withDevice: true);
            $deleted = $this->filtered(ClaudeActivityLog::query(), $filters)->delete();
        } else {
            $deleted = ClaudeActivityLog::whereIn('id', $data['ids'])->delete();
        }

        $this->noteDeletion($request, $deleted);

        return back()->with('success', $deleted === 1 ? '1 event deleted.' : number_format($deleted).' events deleted.');
    }

    /** The URL of a device's page; devices that sent no id share "none". */
    public static function deviceUrl(?string $deviceId, array $query = []): string
    {
        return route('claude-activity.device', ['device' => $deviceId ?? self::NO_DEVICE, ...$query]);
    }

    /**
     * The filters both pages share, validated. `device` only where the page
     * lists several devices.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request, bool $withDevice): array
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'device' => ['nullable', 'string', 'max:64'],
            'event' => ['nullable', 'string', 'max:32'],
            'ip' => ['nullable', 'string', 'max:45'],
            'os_user' => ['nullable', 'string', 'max:128'],
            'entrypoint' => ['nullable', 'string', 'max:64'],
            'session' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'sort' => ['nullable', 'in:newest,oldest'],
            'per_page' => ['nullable', 'integer', 'in:'.implode(',', self::PAGE_SIZES)],
        ]);

        if (! $withDevice) {
            unset($filters['device']);
        }

        return $filters;
    }

    /** The events matching $filters, paged. */
    private function events(Builder $query, array $filters): LengthAwarePaginator
    {
        return $this->filtered($query, $filters)
            ->orderBy('id', ($filters['sort'] ?? null) === 'oldest' ? 'asc' : 'desc')
            ->paginate((int) ($filters['per_page'] ?? 50))
            ->withQueryString();
    }

    private function filtered(Builder $query, array $filters): Builder
    {
        // The search text is literal: % and _ are escaped, with an escape
        // character named in the query (SQLite has no default one).
        $pattern = fn (string $text) => '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text).'%';
        $like = fn ($w, string $column, string $text, string $boolean = 'and') => $w->whereRaw("{$column} LIKE ? ESCAPE '!'", [$pattern($text)], $boolean);

        return $query
            ->when($filters['device'] ?? null, fn ($q, $device) => self::forDevice($q, $device))
            ->when($filters['event'] ?? null, fn ($q, $event) => $q->where('event', $event))
            ->when($filters['ip'] ?? null, fn ($q, $ip) => $q->where('ip', $ip))
            ->when($filters['os_user'] ?? null, fn ($q, $user) => $q->where('os_user', $user))
            ->when($filters['entrypoint'] ?? null, fn ($q, $entry) => $q->where('entrypoint', $entry))
            ->when($filters['session'] ?? null, fn ($q, $session) => $q->where('session_id', $session))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<', Carbon::parse($to)->addDay()->startOfDay()))
            ->when($filters['q'] ?? null, fn ($q, $text) => $q->where(function ($w) use ($like, $text) {
                $like($w, 'prompt_excerpt', $text);
                $like($w, 'cwd', $text, 'or');
                $like($w, 'hostname', $text, 'or');
            }));
    }

    private static function forDevice(Builder $query, string $device): Builder
    {
        return $device === self::NO_DEVICE ? $query->whereNull('device_id') : $query->where('device_id', $device);
    }

    /**
     * The values the select filters offer, from the events in $query.
     *
     * @return array{os_users: Collection<int, string>, entrypoints: Collection<int, string>}
     */
    private function options(Builder $query): array
    {
        $values = fn (string $column) => (clone $query)->whereNotNull($column)->distinct()->orderBy($column)->pluck($column);

        return ['os_users' => $values('os_user'), 'entrypoints' => $values('entrypoint')];
    }

    /** Fill each device row's name, user and OS from its newest event. */
    private function nameDevices(Collection $devices): Collection
    {
        $latest = ClaudeActivityLog::whereIn('id', $devices->pluck('latest_id'))->get()->keyBy('id');

        return $devices->each(function (ClaudeActivityLog $device) use ($latest) {
            $newest = $latest->get($device->latest_id);
            $device->hostname = $newest?->hostname;
            $device->os_user = $newest?->os_user;
            $device->platform = $newest?->platform;
        });
    }

    /** Deleting is allowed, so leave a trace in the app log of who did it. */
    private function noteDeletion(Request $request, int $count): void
    {
        Log::notice('Claude activity events deleted', [
            'count' => $count,
            'user_id' => $request->user()->id,
            'ip' => $request->ip(),
        ]);
    }

    /**
     * The user ids listed in config, or admins when the list is empty. Ids
     * because a user can't change their own, unlike their email.
     */
    public static function canView(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $viewers = config('claude_monitor.viewers', []);

        return $viewers === []
            ? $user->isAdmin()
            : in_array((string) $user->id, $viewers, true);
    }
}
