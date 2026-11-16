<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Claude activity"
                       description="Every device that has used Claude Code with the monitor installed. Open one to see its sessions and prompts. Entries can't be edited, so they stay even after a conversation is deleted.">
            <x-slot name="actions">
                <a href="{{ route('claude-activity.habits.index') }}" class="btn-secondary btn-sm">
                    <x-icon name="sparkles" variant="outline" class="h-4 w-4" />Habit reports
                </a>
            </x-slot>
        </x-page-header>
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
                                    <th scope="col">Models</th>
                                    <th scope="col" class="text-right">Sessions</th>
                                    <th scope="col" class="text-right">Events</th>
                                    <th scope="col">Last seen</th>
                                    <th scope="col"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($devices as $device)
                                    @php
                                        $ips = $ipsByDevice[$device->device_id] ?? collect();
                                        $models = $modelsByDevice[$device->device_id] ?? collect();
                                    @endphp
                                    <tr>
                                        <td>
                                            <a href="{{ \App\Http\Controllers\ClaudeActivityController::deviceUrl($device->device_id) }}" class="font-medium text-slate-900 hover:text-brand-700 hover:underline">{{ $device->hostname ?? 'Unknown host' }}</a>
                                            <p class="text-xs text-slate-500">
                                                {{ $device->os_user ?? '?' }} · {{ $device->platform ?? '?' }}
                                                @if ($device->mac_address)
                                                    · <span class="font-mono">{{ $device->mac_address }}</span>
                                                @endif
                                                @if ($device->host_names > 1)
                                                    · also seen under {{ $device->host_names - 1 }} other {{ \Illuminate\Support\Str::plural('name', $device->host_names - 1) }}
                                                @endif
                                                @unless ($device->device_id)
                                                    · <span class="text-danger-600">no device id</span>
                                                @endunless
                                            </p>
                                            @if ($device->device_id)
                                                {{-- Who uses it: habit reports cover a person's linked devices. --}}
                                                <form method="POST" action="{{ route('claude-activity.owner', $device->device_id) }}" class="mt-1.5">
                                                    @csrf
                                                    <label for="owner-{{ $loop->index }}" class="sr-only">Person using {{ $device->hostname ?? 'this device' }}</label>
                                                    <select id="owner-{{ $loop->index }}" name="user_id" class="filter-select field-sm max-w-[12rem] text-xs" data-native
                                                            x-data @change="$el.form.requestSubmit()">
                                                        <option value="">Not linked to a person</option>
                                                        @foreach ($people as $person)
                                                            <option value="{{ $person->id }}" @selected($owners->get($device->device_id)?->user_id === $person->id)>{{ $person->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <noscript><button class="btn-secondary btn-sm">Save</button></noscript>
                                                </form>
                                            @endif
                                        </td>
                                        <td class="text-sm">
                                            @foreach ($ips->take(3) as $row)
                                                <a href="{{ \App\Http\Controllers\ClaudeActivityController::deviceUrl($device->device_id, ['ip' => $row->ip]) }}" class="block whitespace-nowrap font-mono text-slate-700 hover:underline">{{ $row->ip }}</a>
                                            @endforeach
                                            @if ($ips->count() > 3)
                                                <span class="text-xs text-slate-500">+{{ $ips->count() - 3 }} more</span>
                                            @endif
                                        </td>
                                        <td class="text-sm">
                                            @forelse ($models->take(3) as $row)
                                                <a href="{{ \App\Http\Controllers\ClaudeActivityController::deviceUrl($device->device_id, ['model' => $row->model]) }}" class="block whitespace-nowrap font-mono text-slate-700 hover:underline">{{ $row->model }}</a>
                                            @empty
                                                <span class="text-slate-400">—</span>
                                            @endforelse
                                            @if ($models->count() > 3)
                                                <span class="text-xs text-slate-500">+{{ $models->count() - 3 }} more</span>
                                            @endif
                                        </td>
                                        <td class="text-right tabular-nums">{{ number_format($device->sessions) }}</td>
                                        <td class="text-right tabular-nums">{{ number_format($device->events) }}</td>
                                        <td class="whitespace-nowrap text-slate-600">
                                            <span title="{{ $device->last_seen }}">{{ \Illuminate\Support\Carbon::parse($device->last_seen)->diffForHumans() }}</span>
                                            <span class="block text-xs text-slate-500">since {{ \Illuminate\Support\Carbon::parse($device->first_seen)->format('M j, Y') }}</span>
                                        </td>
                                        <td class="whitespace-nowrap text-right">
                                            <div class="flex items-center justify-end gap-1">
                                                <a href="{{ \App\Http\Controllers\ClaudeActivityController::deviceUrl($device->device_id) }}" class="btn btn-secondary btn-sm">Show events</a>
                                                {{-- Every event this device sent; it drops off the list until it reports again. --}}
                                                @php
                                                    $deviceName = $device->hostname ?? 'this device';
                                                    $eventCount = number_format($device->events).' '.\Illuminate\Support\Str::plural('event', $device->events);
                                                @endphp
                                                <button type="button" class="btn-ghost btn-sm text-slate-400 hover:text-danger-600"
                                                        aria-label="Delete {{ $deviceName }} and its events" title="Delete this device and its events"
                                                        x-data @click="$dispatch('claude-delete-all', @js([
                                                            'title' => "Delete {$deviceName}?",
                                                            'message' => "All {$eventCount} from {$deviceName} will be deleted. If it uses Claude Code again, it shows up here again.",
                                                            'fields' => ['all' => 1, 'device' => $device->device_id ?? \App\Http\Controllers\ClaudeActivityController::NO_DEVICE],
                                                        ]))">
                                                    <x-icon name="trash" class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

        </div>
    </div>

    @include('claude-activity._delete-all-modal')
</x-app-layout>
