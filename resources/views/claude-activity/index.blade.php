<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Claude activity"
                       description="Every Claude Code session and prompt on your monitored devices, recorded when it happens. Rows can't be edited or deleted, so they stay even after a conversation is deleted." />
    </x-slot>

    @php
        $filtered = collect($filters)->filter()->isNotEmpty();
        $eventTone = ['SessionStart' => 'badge-success', 'UserPromptSubmit' => 'badge-info', 'SessionEnd' => 'badge-slate', 'MonitorInstalled' => 'badge-violet'];
    @endphp

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-6">
            @unless ($configured)
                <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="status">
                    <x-icon name="exclamation-circle" variant="outline" class="mt-0.5 h-5 w-5 flex-none" />
                    <p>Add <code class="font-mono">CLAUDE_MONITOR_TOKEN</code> to <code class="font-mono">.env</code>. Until then devices can't report activity.</p>
                </div>
            @endunless

            {{-- Devices --}}
            <section class="card" aria-labelledby="claude-devices">
                <div class="card-header">
                    <h2 id="claude-devices" class="card-title">Devices</h2>
                </div>
                @if ($devices->isEmpty())
                    <x-empty-state icon="command-line" title="No devices yet"
                                   description="Install the hook from tools/claude-monitor on each device. Its first Claude Code session shows up here." class="p-6" />
                @else
                    <div class="table-wrap">
                        <table class="table-base">
                            <thead>
                                <tr>
                                    <th scope="col">Device</th>
                                    <th scope="col">IP addresses</th>
                                    <th scope="col" class="text-right">Sessions</th>
                                    <th scope="col" class="text-right">Events</th>
                                    <th scope="col">First seen</th>
                                    <th scope="col">Last seen</th>
                                    <th scope="col"><span class="sr-only">Filter</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($devices as $device)
                                    @php $ips = $ipsByDevice[$device->device_id] ?? collect(); @endphp
                                    <tr>
                                        <td>
                                            <p class="font-medium text-slate-900">{{ $device->hostname ?? 'Unknown host' }}</p>
                                            <p class="text-xs text-slate-500">
                                                {{ $device->os_user ?? '?' }} · {{ $device->platform ?? '?' }}
                                                @unless ($device->device_id)
                                                    · <span class="text-danger-600">no device id</span>
                                                @endunless
                                            </p>
                                        </td>
                                        <td class="text-sm">
                                            @foreach ($ips->take(3) as $row)
                                                <a href="{{ route('claude-activity.index', ['ip' => $row->ip]) }}" class="block font-mono text-slate-700 hover:underline">{{ $row->ip }}</a>
                                            @endforeach
                                            @if ($ips->count() > 3)
                                                <span class="text-xs text-slate-500">+{{ $ips->count() - 3 }} more</span>
                                            @endif
                                        </td>
                                        <td class="text-right tabular-nums">{{ number_format($device->sessions) }}</td>
                                        <td class="text-right tabular-nums">{{ number_format($device->events) }}</td>
                                        <td class="whitespace-nowrap text-slate-600">{{ \Illuminate\Support\Carbon::parse($device->first_seen)->format('M j, Y H:i') }}</td>
                                        <td class="whitespace-nowrap text-slate-600" title="{{ $device->last_seen }}">{{ \Illuminate\Support\Carbon::parse($device->last_seen)->diffForHumans() }}</td>
                                        <td class="text-right">
                                            @if ($device->device_id)
                                                <a href="{{ route('claude-activity.index', ['device' => $device->device_id]) }}" class="btn btn-secondary btn-sm">Show events</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Events --}}
            <section class="card" aria-labelledby="claude-events">
                <div class="card-header flex-wrap gap-3">
                    <h2 id="claude-events" class="card-title">Events</h2>
                    <form method="GET" action="{{ route('claude-activity.index') }}" class="flex flex-wrap items-center gap-2">
                        @if ($filters['device'] ?? null)
                            <input type="hidden" name="device" value="{{ $filters['device'] }}">
                        @endif
                        @if ($filters['ip'] ?? null)
                            <input type="hidden" name="ip" value="{{ $filters['ip'] }}">
                        @endif
                        <label for="event" class="sr-only">Event</label>
                        <select id="event" name="event" class="field-select field-sm !mt-0 w-auto">
                            <option value="">All events</option>
                            @foreach (\App\Models\ClaudeActivityLog::EVENTS as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['event'] ?? null) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <label for="from" class="sr-only">From</label>
                        <input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}" class="field-input field-sm !mt-0 w-auto">
                        <label for="to" class="sr-only">To</label>
                        <input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}" class="field-input field-sm !mt-0 w-auto">
                        <button type="submit" class="btn btn-secondary btn-sm">Apply</button>
                        @if ($filtered)
                            <a href="{{ route('claude-activity.index') }}" class="btn btn-ghost btn-sm">Clear</a>
                        @endif
                    </form>
                </div>
                @if ($logs->isEmpty())
                    <x-empty-state icon="command-line" title="No events" :description="$filtered ? 'Nothing matches these filters.' : 'Events show here as monitored devices use Claude Code.'" class="p-6" />
                @else
                    <div class="table-wrap">
                        <table class="table-base">
                            <thead>
                                <tr>
                                    <th scope="col">When</th>
                                    <th scope="col">Event</th>
                                    <th scope="col">Device</th>
                                    <th scope="col">IP</th>
                                    <th scope="col">Folder / prompt</th>
                                    <th scope="col">Session</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($logs as $log)
                                    <tr>
                                        <td class="whitespace-nowrap text-slate-600" title="{{ $log->created_at->diffForHumans() }}">{{ $log->created_at->format('M j, H:i:s') }}</td>
                                        <td class="whitespace-nowrap">
                                            <span class="badge {{ $eventTone[$log->event] ?? 'badge-slate' }}">{{ $log->eventLabel() }}</span>
                                            @if ($log->detail)
                                                <span class="text-xs text-slate-500">{{ $log->detail }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <p class="text-slate-900">{{ $log->hostname ?? 'Unknown host' }}</p>
                                            <p class="text-xs text-slate-500">{{ $log->os_user }}@if ($log->entrypoint) · {{ $log->entrypoint }}@endif</p>
                                        </td>
                                        <td class="whitespace-nowrap font-mono text-sm text-slate-700">{{ $log->ip }}</td>
                                        <td class="max-w-md">
                                            @if ($log->prompt_excerpt)
                                                <p class="line-clamp-2 text-slate-800" title="{{ $log->prompt_excerpt }}">{{ $log->prompt_excerpt }}</p>
                                            @endif
                                            @if ($log->cwd)
                                                <p class="truncate font-mono text-xs text-slate-500" title="{{ $log->cwd }}">{{ $log->cwd }}</p>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap font-mono text-xs text-slate-500" title="{{ $log->session_id }}">{{ \Illuminate\Support\Str::limit((string) $log->session_id, 8, '…') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-slate-100 px-4 py-3 sm:px-6">{{ $logs->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
