@php
    use App\Http\Controllers\ClaudeActivityController;
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    $name = $newest?->hostname ?? 'Unknown host';
    $noId = $device === ClaudeActivityController::NO_DEVICE;
    $deviceId = $noId ? null : $device;
    $firstSeen = Carbon::parse($summary->first_seen);
    $lastSeen = Carbon::parse($summary->last_seen);
    $eventCount = number_format($summary->events).' '.Str::plural('event', $summary->events);

    // Each list: newest first, linking to the events it covers.
    $rows = fn ($list, string $column, string $title) => $list->map(fn ($row) => [
        'value' => $row->{$column},
        'href' => ClaudeActivityController::deviceUrl($deviceId, [$column === 'mac_address' ? 'mac' : $column => $row->{$column}]),
        'title' => $title,
        'events' => (int) $row->events,
        'last_seen' => Carbon::parse($row->last_seen),
    ])->values()->all();
    $otherNames = $hostNames->reject(fn ($host) => $host === $newest?->hostname);
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$name"
                       :description="$noId ? 'Events that arrived without a device id.' : ($owner?->user ? 'Used by '.$owner->user->name : 'Not linked to a person yet').' · last seen '.$lastSeen->diffForHumans()"
                       :breadcrumbs="['Claude activity' => route('claude-activity.index'), $name => null]">
            <x-slot name="actions">
                <button type="button" class="btn-secondary btn-sm text-danger-700 hover:bg-danger-50"
                        x-data @click="$dispatch('claude-delete-all', @js([
                            'title' => "Delete {$name}?",
                            'message' => "All {$eventCount} from {$name} will be deleted. If it uses Claude Code again, it shows up again.",
                            'fields' => ['all' => 1, 'device' => $device],
                        ]))">
                    <x-icon name="trash" variant="outline" class="h-4 w-4" />Delete device
                </button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-6">
            <div class="stat-strip">
                <x-stat label="Prompts" :value="number_format($summary->prompts)" icon="chat-bubbles" tone="info"
                        :sub="$summary->sessions ? round($summary->prompts / max(1, $summary->sessions), 1).' per session' : null" />
                <x-stat label="Sessions" :value="number_format($summary->sessions)" icon="clock" tone="violet"
                        :sub="'since '.$firstSeen->format('M j, Y')" />
                <x-stat label="Active days" :value="number_format($summary->active_days)" icon="calendar" tone="success"
                        :sub="'last on '.$lastSeen->format('M j')" />
                <x-stat label="Events" :value="number_format($summary->events)" icon="command-line" tone="slate"
                        :sub="$summary->browser_actions ? number_format($summary->browser_actions).' in the browser' : 'all kinds'" />
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                {{-- Who and what it is --}}
                <section class="card" aria-labelledby="device-profile">
                    <div class="card-header">
                        <h2 id="device-profile" class="card-title flex items-center gap-2">
                            <x-icon name="command-line" variant="outline" class="h-5 w-5 text-slate-400" />Device
                        </h2>
                    </div>
                    <dl class="divide-y divide-slate-100 text-sm">
                        @unless ($noId)
                            <div class="px-4 py-3 sm:px-6">
                                <dt class="text-xs font-medium text-slate-500">Person</dt>
                                <dd class="mt-1">
                                    {{-- Habit reports cover a person's linked devices. --}}
                                    <form method="POST" action="{{ route('claude-activity.owner', $device) }}">
                                        @csrf
                                        <label for="device-owner" class="sr-only">Person using {{ $name }}</label>
                                        <select id="device-owner" name="user_id" class="field-select field-sm w-full" data-native
                                                x-data @change="$el.form.requestSubmit()">
                                            <option value="">Not linked to a person</option>
                                            @foreach ($people as $person)
                                                <option value="{{ $person->id }}" @selected($owner?->user_id === $person->id)>{{ $person->name }}</option>
                                            @endforeach
                                        </select>
                                        <noscript><button class="btn-secondary btn-sm mt-2">Save</button></noscript>
                                    </form>
                                </dd>
                            </div>
                        @endunless
                        <div class="px-4 py-3 sm:px-6">
                            <dt class="text-xs font-medium text-slate-500">Computer name</dt>
                            <dd class="mt-1 font-medium text-slate-900">{{ $name }}</dd>
                            @if ($otherNames->isNotEmpty())
                                <dd class="mt-0.5 text-xs text-slate-500">Also seen as {{ $otherNames->implode(', ') }}</dd>
                            @endif
                        </div>
                        <div class="grid grid-cols-2 gap-4 px-4 py-3 sm:px-6">
                            <div>
                                <dt class="text-xs font-medium text-slate-500">OS {{ Str::plural('user', $options['os_users']->count()) }}</dt>
                                <dd class="mt-1 text-slate-800">{{ $options['os_users']->implode(', ') ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-slate-500">Platform</dt>
                                <dd class="mt-1 text-slate-800">{{ $newest?->platform ?? '—' }}</dd>
                            </div>
                        </div>
                        <div class="px-4 py-3 sm:px-6">
                            <dt class="text-xs font-medium text-slate-500">Started from</dt>
                            <dd class="mt-1 flex flex-wrap gap-1.5">
                                @forelse ($options['entrypoints'] as $entrypoint)
                                    <a href="{{ ClaudeActivityController::deviceUrl($deviceId, ['entrypoint' => $entrypoint]) }}" class="badge badge-slate font-mono hover:bg-slate-200" title="Only events started from {{ $entrypoint }}">{{ $entrypoint }}</a>
                                @empty
                                    <span class="text-slate-500">—</span>
                                @endforelse
                            </dd>
                        </div>
                        <div class="grid grid-cols-2 gap-4 px-4 py-3 sm:px-6">
                            <div>
                                <dt class="text-xs font-medium text-slate-500">First seen</dt>
                                <dd class="mt-1 text-slate-800">{{ $firstSeen->format('M j, Y') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-slate-500">Last seen</dt>
                                <dd class="mt-1 text-slate-800" title="{{ $lastSeen->format('M j, Y H:i') }}">{{ $lastSeen->diffForHumans() }}</dd>
                            </div>
                        </div>
                        @unless ($noId)
                            <div class="px-4 py-3 sm:px-6" x-data="{ copied: false }">
                                <dt class="text-xs font-medium text-slate-500">Device id</dt>
                                <dd class="mt-1 flex items-center gap-2">
                                    <code class="min-w-0 truncate font-mono text-xs text-slate-700">{{ $device }}</code>
                                    <button type="button" class="flex-none text-slate-400 hover:text-slate-700" title="Copy" aria-label="Copy the device id"
                                            @click="navigator.clipboard?.writeText(@js($device)); copied = true; setTimeout(() => copied = false, 1500)">
                                        <x-icon name="clipboard-check" variant="outline" class="h-4 w-4" />
                                    </button>
                                    <span class="text-xs text-success-600" x-show="copied" x-cloak role="status">Copied</span>
                                </dd>
                            </div>
                        @endunless
                    </dl>
                </section>

                {{-- Where it reported from --}}
                <section class="card" aria-labelledby="device-network">
                    <div class="card-header">
                        <h2 id="device-network" class="card-title flex items-center gap-2">
                            <x-icon name="globe" variant="outline" class="h-5 w-5 text-slate-400" />Network
                        </h2>
                    </div>
                    <h3 class="flex items-baseline justify-between bg-slate-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">
                        IP addresses <span class="font-normal normal-case tracking-normal tabular-nums">{{ $ips->count() }}</span>
                    </h3>
                    @include('claude-activity._usage-list', [
                        'items' => $rows($ips, 'ip', 'Only events from this IP address'),
                        'noun' => 'IP address',
                        'empty' => 'None recorded.',
                    ])
                    <h3 class="flex items-baseline justify-between border-t border-slate-100 bg-slate-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">
                        MAC addresses <span class="font-normal normal-case tracking-normal tabular-nums">{{ $macs->count() }}</span>
                    </h3>
                    @include('claude-activity._usage-list', [
                        'items' => $rows($macs, 'mac_address', 'Only events from this MAC address'),
                        'noun' => 'MAC address',
                        'empty' => 'None recorded yet.',
                    ])
                </section>

                {{-- Models --}}
                <section class="card" aria-labelledby="device-models">
                    <div class="card-header">
                        <h2 id="device-models" class="card-title flex items-center gap-2">
                            <x-icon name="sparkles" variant="outline" class="h-5 w-5 text-slate-400" />Models
                        </h2>
                        <span class="text-xs tabular-nums text-slate-500">{{ $models->count() }}</span>
                    </div>
                    @include('claude-activity._usage-list', [
                        'items' => $rows($models, 'model', 'Only events with this model'),
                        'noun' => 'model',
                        'empty' => 'None recorded yet.',
                        'bars' => true,
                        'limit' => 5,
                    ])
                </section>
            </div>

            @include('claude-activity._events', [
                'action' => ClaudeActivityController::deviceUrl($deviceId),
                'device' => $device,
            ])
        </div>
    </div>

    @include('claude-activity._delete-all-modal')
</x-app-layout>
