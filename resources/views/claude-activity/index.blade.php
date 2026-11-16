<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Claude activity"
                       description="Every Claude Code session and prompt on your monitored devices, recorded when it happens. Entries can't be edited, so they stay even after a conversation is deleted." />
    </x-slot>

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
                                            <a href="{{ \App\Http\Controllers\ClaudeActivityController::deviceUrl($device->device_id) }}" class="font-medium text-slate-900 hover:text-brand-700 hover:underline">{{ $device->hostname ?? 'Unknown host' }}</a>
                                            <p class="text-xs text-slate-500">
                                                {{ $device->os_user ?? '?' }} · {{ $device->platform ?? '?' }}
                                                @if ($device->host_names > 1)
                                                    · also seen under {{ $device->host_names - 1 }} other {{ \Illuminate\Support\Str::plural('name', $device->host_names - 1) }}
                                                @endif
                                                @unless ($device->device_id)
                                                    · <span class="text-danger-600">no device id</span>
                                                @endunless
                                            </p>
                                        </td>
                                        <td class="text-sm">
                                            @foreach ($ips->take(3) as $row)
                                                <a href="{{ \App\Http\Controllers\ClaudeActivityController::deviceUrl($device->device_id, ['ip' => $row->ip]) }}" class="block font-mono text-slate-700 hover:underline">{{ $row->ip }}</a>
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
                                            <a href="{{ \App\Http\Controllers\ClaudeActivityController::deviceUrl($device->device_id) }}" class="btn btn-secondary btn-sm">Show events</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            @include('claude-activity._events', ['action' => route('claude-activity.index'), 'devices' => $devices])
        </div>
    </div>
</x-app-layout>
