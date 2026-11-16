<?php

namespace App\Http\Controllers;

use App\Models\ClaudeActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Claude Code activity monitor: devices post hook events to store(); the
 * account owner reads them on index(). Nothing here edits or deletes a row.
 */
class ClaudeActivityController extends Controller
{
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

    /** GET /claude-activity — the log, with a per-device summary. */
    public function index(Request $request): View
    {
        abort_unless(self::canView($request->user()), 403);

        $filters = $request->validate([
            'device' => ['nullable', 'string', 'max:64'],
            'event' => ['nullable', 'string', 'max:32'],
            'ip' => ['nullable', 'string', 'max:45'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $logs = ClaudeActivityLog::query()
            ->when($filters['device'] ?? null, fn ($q, $device) => $q->where('device_id', $device))
            ->when($filters['event'] ?? null, fn ($q, $event) => $q->where('event', $event))
            ->when($filters['ip'] ?? null, fn ($q, $ip) => $q->where('ip', $ip))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<', now()->parse($to)->addDay()->startOfDay()))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        // One row per device id. The computer name isn't part of the grouping:
        // a Mac's name can change with the network it's on, and one device
        // must not split into several rows. The newest event names it.
        $devices = ClaudeActivityLog::query()
            ->select('device_id')
            ->selectRaw('MIN(created_at) as first_seen, MAX(created_at) as last_seen, COUNT(*) as events')
            ->selectRaw('COUNT(DISTINCT session_id) as sessions, COUNT(DISTINCT hostname) as host_names')
            ->selectRaw('MAX(id) as latest_id')
            ->groupBy('device_id')
            ->orderByDesc('last_seen')
            ->get();

        $latest = ClaudeActivityLog::whereIn('id', $devices->pluck('latest_id'))->get()->keyBy('id');
        $devices->each(function (ClaudeActivityLog $device) use ($latest) {
            $newest = $latest->get($device->latest_id);
            $device->hostname = $newest?->hostname;
            $device->os_user = $newest?->os_user;
            $device->platform = $newest?->platform;
        });

        // Every IP each device has posted from, newest first.
        $ipsByDevice = ClaudeActivityLog::query()
            ->select('device_id', 'ip', DB::raw('MAX(created_at) as last_seen'))
            ->whereNotNull('ip')
            ->groupBy('device_id', 'ip')
            ->orderByDesc('last_seen')
            ->get()
            ->groupBy('device_id');

        return view('claude-activity.index', [
            'logs' => $logs,
            'devices' => $devices,
            'ipsByDevice' => $ipsByDevice,
            'filters' => $filters,
            'configured' => (string) config('claude_monitor.token') !== '',
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
