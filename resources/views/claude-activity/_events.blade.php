{{--
    A device's event list on its Claude activity page: filters, the events,
    and deleting them (one, the ticked ones, or every event the filters match).
      $logs     paginator of ClaudeActivityLog
      $filters  the validated filters
      $options  ['os_users' => …, 'entrypoints' => …] for the selects
      $action   the page's URL, which the filters submit to
      $device   the device id the page shows ("none" for events without one)
--}}
@php
    use App\Http\Controllers\ClaudeActivityController;
    use App\Models\ClaudeActivityLog;
    use Illuminate\Support\Str;

    $filtered = collect($filters)->except(['sort', 'per_page'])->filter()->isNotEmpty();
    $eventTone = ['SessionStart' => 'badge-success', 'UserPromptSubmit' => 'badge-info', 'SessionEnd' => 'badge-slate', 'PostModelSwitch' => 'badge-warning', 'PreToolUse' => 'badge-brand', 'MonitorInstalled' => 'badge-violet'];
    $total = $logs->total();
    // What "delete all matching" sends: the filters, and the device it's scoped to.
    $matching = collect($filters)->except(['sort', 'per_page'])->filter()
        ->put('device', $device);
@endphp

<section class="card" aria-labelledby="claude-events">
    <div class="card-header flex-col items-stretch gap-3">
        <h2 id="claude-events" class="card-title">Events</h2>

        <x-filter-bar :action="$action" :active="$filtered" :count="$total" noun="event">
            <x-slot name="search">
                <label for="events-q" class="sr-only">Search prompts, folders and computer names</label>
                <input id="events-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search prompts, folders…" class="filter-search">
            </x-slot>


            <label for="events-event" class="filter-label">Event</label>
            <select id="events-event" name="event" class="filter-select">
                <option value="">All events</option>
                @foreach (ClaudeActivityLog::EVENTS as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['event'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>

            @if ($options['os_users']->count() > 1 || ($filters['os_user'] ?? null))
                <label for="events-user" class="filter-label">OS user</label>
                <select id="events-user" name="os_user" class="filter-select max-w-[10rem]">
                    <option value="">Any OS user</option>
                    @foreach ($options['os_users'] as $value)
                        <option value="{{ $value }}" @selected(($filters['os_user'] ?? null) === $value)>{{ $value }}</option>
                    @endforeach
                </select>
            @endif

            @if ($options['entrypoints']->count() > 1 || ($filters['entrypoint'] ?? null))
                <label for="events-entry" class="filter-label">Entry point</label>
                <select id="events-entry" name="entrypoint" class="filter-select max-w-[10rem]">
                    <option value="">Any entry point</option>
                    @foreach ($options['entrypoints'] as $value)
                        <option value="{{ $value }}" @selected(($filters['entrypoint'] ?? null) === $value)>{{ $value }}</option>
                    @endforeach
                </select>
            @endif

            @if ($options['models']->isNotEmpty() || ($filters['model'] ?? null))
                <label for="events-model" class="filter-label">Model</label>
                <select id="events-model" name="model" class="filter-select max-w-[12rem]">
                    <option value="">Any model</option>
                    @foreach ($options['models'] as $value)
                        <option value="{{ $value }}" @selected(($filters['model'] ?? null) === $value)>{{ $value }}</option>
                    @endforeach
                </select>
            @endif

            <label for="events-ip" class="filter-label">IP address</label>
            <input id="events-ip" type="text" name="ip" value="{{ $filters['ip'] ?? '' }}" placeholder="IP address" class="filter-date w-36 font-mono" inputmode="decimal" autocomplete="off">

            <label for="events-mac" class="filter-label">MAC address</label>
            <input id="events-mac" type="text" name="mac" value="{{ $filters['mac'] ?? '' }}" placeholder="MAC address" class="filter-date w-40 font-mono" autocomplete="off">

            <label for="events-session" class="filter-label">Session id</label>
            <input id="events-session" type="text" name="session" value="{{ $filters['session'] ?? '' }}" placeholder="Session id" class="filter-date w-36 font-mono" autocomplete="off">

            <label for="events-from" class="filter-label">From</label>
            <input id="events-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="filter-date" title="From">
            <label for="events-to" class="filter-label">To</label>
            <input id="events-to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="filter-date" title="To">

            <label for="events-sort" class="filter-label">Order</label>
            <select id="events-sort" name="sort" class="filter-select">
                <option value="newest">Newest first</option>
                <option value="oldest" @selected(($filters['sort'] ?? null) === 'oldest')>Oldest first</option>
            </select>

            <label for="events-per-page" class="filter-label">Per page</label>
            <select id="events-per-page" name="per_page" class="filter-select">
                @foreach (ClaudeActivityController::PAGE_SIZES as $size)
                    <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 50) === $size)>{{ $size }} per page</option>
                @endforeach
            </select>
        </x-filter-bar>
    </div>

    @if ($logs->isEmpty())
        <x-empty-state icon="command-line" title="No events" :description="$filtered ? 'Nothing matches these filters.' : 'Events show here as monitored devices use Claude Code.'" class="p-6" />
    @else
        <div x-data="{
                count: 0,
                allOnPage: false,
                boxes() { return [...$root.querySelectorAll('input[name=\'ids[]\']')]; },
                sync() { const b = this.boxes(); this.count = b.filter((x) => x.checked).length; this.allOnPage = b.length > 0 && this.count === b.length; },
                toggleAll(on) { this.boxes().forEach((x) => { x.checked = on; }); this.sync(); },
             }">
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-4 py-2.5 sm:px-6">
                {{-- Delete the ticked events --}}
                <form id="claude-bulk-delete" method="POST" action="{{ route('claude-activity.bulk-destroy') }}" x-show="count > 0" x-cloak
                      class="flex items-center gap-2"
                      data-confirm-title="Delete events?" data-confirm-verb="Delete"
                      :data-confirm="'Delete ' + count + (count === 1 ? ' event' : ' events') + '? This can\'t be undone.'">
                    @csrf
                    <span class="tabular text-sm font-semibold text-slate-800" aria-live="polite" x-text="count + ' selected'"></span>
                    <button class="btn btn-sm bg-rose-600 text-white shadow-sm hover:bg-rose-700" x-text="'Delete ' + count"></button>
                    <button type="button" class="btn-ghost btn-sm" @click="toggleAll(false)">Clear</button>
                </form>

                {{-- Delete every event these filters match, on every page --}}
                <form method="POST" action="{{ route('claude-activity.bulk-destroy') }}" class="ml-auto"
                      data-confirm-title="Delete {{ number_format($total) }} {{ Str::plural('event', $total) }}?" data-confirm-verb="Delete {{ number_format($total) }}"
                      data-confirm="Every event {{ $filtered ? 'that matches these filters' : 'from this device' }}, on every page, will be deleted. This can't be undone.">
                    @csrf
                    <input type="hidden" name="all" value="1">
                    @foreach ($matching as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                    <button class="btn-ghost btn-sm text-danger-600">
                        <x-icon name="trash" class="h-4 w-4" />
                        Delete all {{ number_format($total) }} {{ $filtered ? 'matching' : '' }}
                    </button>
                </form>
            </div>

            @if ($errors->hasAny(['ids', 'ids.*']))
                <p class="field-error px-4 pt-3 sm:px-6" role="alert">{{ $errors->first('ids') ?: $errors->first('ids.*') }}</p>
            @endif

            <div class="table-wrap rounded-none ring-0">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th scope="col" class="w-8">
                                <input type="checkbox" class="checkbox-sm" aria-label="Select all {{ $logs->count() }} events on this page"
                                       :checked="allOnPage" :indeterminate="count > 0 && ! allOnPage" @change="toggleAll($event.target.checked)">
                            </th>
                            <th scope="col">When</th>
                            <th scope="col">Event</th>
                            <th scope="col">IP / MAC</th>
                            <th scope="col">Page / prompt / folder</th>
                            <th scope="col">Session</th>
                            <th scope="col"><span class="sr-only">Delete</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td>
                                    <input type="checkbox" name="ids[]" value="{{ $log->id }}" form="claude-bulk-delete" class="checkbox-sm"
                                           aria-label="Select the {{ $log->eventLabel() }} event at {{ $log->created_at->format('M j, H:i:s') }}" @change="sync()">
                                </td>
                                <td class="whitespace-nowrap text-slate-600" title="{{ $log->created_at->diffForHumans() }}">{{ $log->created_at->format('M j, H:i:s') }}</td>
                                <td class="whitespace-nowrap">
                                    <span class="badge {{ $eventTone[$log->event] ?? 'badge-slate' }}">{{ $log->eventLabel() }}</span>
                                    @if ($log->detail)
                                        <span class="text-xs text-slate-500">{{ $log->event === 'PostModelSwitch' ? 'from '.$log->detail : $log->detail }}</span>
                                    @endif
                                    @if ($log->model)
                                        <a href="{{ request()->fullUrlWithQuery(['model' => $log->model, 'page' => null]) }}"
                                           class="mt-1 block font-mono text-xs text-slate-500 hover:underline" title="Only events with this model">{{ $log->model }}</a>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap font-mono text-sm text-slate-700">
                                    @if ($log->ip)
                                        <a href="{{ request()->fullUrlWithQuery(['ip' => $log->ip, 'page' => null]) }}" class="hover:underline" title="Only events from this IP">{{ $log->ip }}</a>
                                    @endif
                                    @if ($log->mac_address)
                                        <a href="{{ request()->fullUrlWithQuery(['mac' => $log->mac_address, 'page' => null]) }}" class="block text-xs text-slate-500 hover:underline" title="Only events from this MAC address">{{ $log->mac_address }}</a>
                                    @endif
                                </td>
                                <td class="max-w-md">
                                    @if ($log->url)
                                        <p class="truncate font-mono text-xs text-slate-800" title="{{ $log->url }}">{{ $log->url }}</p>
                                    @endif
                                    @if ($log->prompt_excerpt)
                                        <p class="line-clamp-2 text-slate-800" title="{{ $log->prompt_excerpt }}">{{ $log->prompt_excerpt }}</p>
                                    @endif
                                    @if ($log->cwd)
                                        <p class="truncate font-mono text-xs text-slate-500" title="{{ $log->cwd }}">{{ $log->cwd }}</p>
                                    @endif
                                    @if ($log->entrypoint || $log->os_user)
                                        <p class="text-xs text-slate-400">{{ $log->os_user }}@if ($log->entrypoint) · {{ $log->entrypoint }}@endif</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap font-mono text-xs text-slate-500">
                                    @if ($log->session_id)
                                        <a href="{{ request()->fullUrlWithQuery(['session' => $log->session_id, 'page' => null]) }}" class="hover:underline" title="Only this session ({{ $log->session_id }})">{{ Str::limit($log->session_id, 8, '…') }}</a>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('claude-activity.destroy', $log) }}"
                                          data-confirm-title="Delete this event?" data-confirm-verb="Delete"
                                          data-confirm="The {{ Str::lower($log->eventLabel()) }} event from {{ $log->hostname ?? 'an unknown host' }} at {{ $log->created_at->format('M j, H:i:s') }} will be deleted. This can't be undone.">
                                        @csrf @method('DELETE')
                                        <button class="btn-ghost btn-sm text-slate-400 hover:text-danger-600" aria-label="Delete this event">
                                            <x-icon name="trash" class="h-4 w-4" />
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-4 py-3 sm:px-6">{{ $logs->links() }}</div>
        </div>
    @endif
</section>
