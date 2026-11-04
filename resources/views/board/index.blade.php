@php
    use App\Services\TaskService;
    use Illuminate\Support\Str;

    $me = auth()->user();

    // Developers and QA land on their own tasks, which isn't a filter they can clear.
    $ownByDefault = $filters['assignee_id'] === $me->id && ! request()->filled('assignee_id');
    $anyone = $me->hasLimitedAccess() ? TaskService::ANYONE : '';
    $hasFilters = $filters['release_id'] || $filters['team_id'] || ($filters['assignee_id'] && ! $ownByDefault) || $filters['priority'] || $filters['label_id'] || $filters['blocked'];

    // Developers and QA are pinned to their team: no team choice unless they are on several.
    $teamChoice = ! $me->hasLimitedAccess() || $teams->count() > 1;

    // "My tasks" toggles the assignee filter and keeps the release and team.
    $mine = $filters['assignee_id'] === $me->id;
    $mineUrl = route('board.index', array_filter([
        'release_id' => $filters['release_id'],
        'team_id' => $filters['team_id'],
        'assignee_id' => $mine ? $anyone : $me->id,
        'priority' => $filters['priority'],
        'label_id' => $filters['label_id'],
        'blocked' => $filters['blocked'] ? 1 : null,
        'group' => $group,
    ]));

    // Literal classes per status so Tailwind keeps them.
    $palette = [
        'todo' => ['col' => 'border-t-slate-400 bg-slate-100/70', 'dot' => 'bg-slate-400', 'pill' => 'bg-white text-slate-600 ring-1 ring-inset ring-slate-200', 'hex' => '#94a3b8'],
        'in_progress' => ['col' => 'border-t-blue-500 bg-blue-50/70', 'dot' => 'bg-blue-500', 'pill' => 'bg-blue-100 text-blue-800', 'hex' => '#3b82f6'],
        'in_review' => ['col' => 'border-t-amber-500 bg-amber-50/70', 'dot' => 'bg-amber-500', 'pill' => 'bg-amber-100 text-amber-800', 'hex' => '#f59e0b'],
        'recheck' => ['col' => 'border-t-orange-500 bg-orange-50/70', 'dot' => 'bg-orange-500', 'pill' => 'bg-orange-100 text-orange-800', 'hex' => '#f97316'],
        'done' => ['col' => 'border-t-emerald-500 bg-emerald-50/70', 'dot' => 'bg-emerald-500', 'pill' => 'bg-emerald-100 text-emerald-800', 'hex' => '#10b981'],
        'archive' => ['col' => 'border-t-slate-300 bg-slate-100/60', 'dot' => 'bg-slate-400', 'pill' => 'bg-white text-slate-500 ring-1 ring-inset ring-slate-200', 'hex' => '#64748b'],
    ];
    $tone = fn (string $status) => $palette[$status] ?? $palette['todo'];

    $counts = collect($statuses)->map(fn ($label, $status) => $columns[$status]->count());
    $total = $counts->sum();
    $donePct = $total > 0 ? (int) round(($counts['done'] ?? 0) / $total * 100) : 0;

    $canDelete = $me->can('deleteFromBoard', \App\Models\Task::class);

    // Names the active filters beside "select all", so its reach is plain.
    $filterLabels = $canDelete ? array_values(array_filter([
        $filters['release_id'] === 'none' ? 'No release' : ($release ? 'Release: '.$release->name : null),
        $filters['team_id'] === 'none' ? 'No team' : (is_int($filters['team_id']) ? 'Team: '.($teams->firstWhere('id', $filters['team_id'])?->name ?? 'selected') : null),
        $filters['assignee_id'] === 'none' ? 'Unassigned' : ($filters['assignee_id'] ? 'Assignee: '.($users->firstWhere('id', $filters['assignee_id'])?->name ?? 'selected') : null),
        $filters['priority'] === 'none' ? 'No priority' : ($filters['priority'] ? 'Priority: '.\App\Models\Task::PRIORITIES[$filters['priority']] : null),
        $filters['label_id'] ? 'Label: '.($labels->firstWhere('id', $filters['label_id'])?->name ?? 'selected') : null,
        $filters['blocked'] ? 'Blocked' : null,
    ])) : [];
    $filterScope = implode(', ', $filterLabels);

    // A limit means the whole column for one team, so any other filter hides it.
    $showLimits = is_int($filters['team_id']) && ! $filters['release_id'] && ! $filters['assignee_id'] && ! $filters['priority'] && ! $filters['label_id'] && ! $filters['blocked'];
    $limitTeam = is_int($filters['team_id']) ? $teams->firstWhere('id', $filters['team_id']) : null;
    $canSetLimits = $showLimits && $limitTeam && $me->canAccessTeamPerformance($limitTeam);

    // On lg the columns share any spare width, but stay wide enough to read a
    // card. When they don't all fit they scroll sideways inside the board, with
    // the next column peeking in. Phones keep their own fixed width.
    $columnWidth = 'w-[17rem] lg:w-auto lg:min-w-[18rem] lg:flex-1 lg:basis-0';
@endphp
<x-app-layout full-height>
    {{-- The title and filters share one row, so short laptop screens keep their height for the cards. --}}
    <x-slot name="header">
        <x-page-header title="Board" class="!items-center"
                       :breadcrumbs="$release ? [$release->name => route('releases.show', $release), 'Board' => null] : []">
            <x-slot name="actions">
                @include('partials.saved-views', ['savedViewPage' => 'board'])
                <x-filter-bar :action="route('board.index')" :active="$hasFilters">
                    <label for="release_id" class="filter-label">Release</label>
                    <select id="release_id" name="release_id" class="filter-select max-w-[8.5rem] 2xl:max-w-[14rem]">
                        <option value="">All releases</option>
                        <option value="none" @selected($filters['release_id'] === 'none')>No release</option>
                        @foreach ($releases as $r)
                            <option value="{{ $r->id }}" @selected($filters['release_id'] === $r->id)>{{ $r->name }}</option>
                        @endforeach
                    </select>

                    @if ($teamChoice)
                        <label for="team_id" class="filter-label">Team</label>
                        <select id="team_id" name="team_id" class="filter-select max-w-[7.5rem] min-[1400px]:max-w-[10rem] 2xl:max-w-[12rem]">
                            <option value="">All teams</option>
                            <option value="none" @selected($filters['team_id'] === 'none')>No team</option>
                            @foreach ($teams as $tm)
                                <option value="{{ $tm->id }}" @selected($filters['team_id'] === $tm->id)>{{ $tm->name }}</option>
                            @endforeach
                        </select>
                    @endif

                    <label for="assignee_id" class="filter-label">Assignee</label>
                    <select id="assignee_id" name="assignee_id" class="filter-select max-w-[8rem] 2xl:max-w-[13rem]">
                        <option value="{{ $anyone }}">Anyone</option>
                        <option value="none" @selected($filters['assignee_id'] === 'none')>Unassigned</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected($filters['assignee_id'] === $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>

                    <label for="priority" class="filter-label">Priority</label>
                    <select id="priority" name="priority" class="filter-select max-w-[7rem] 2xl:max-w-[9rem]">
                        <option value="">Priority</option>
                        <option value="none" @selected($filters['priority'] === 'none')>No priority</option>
                        @foreach (\App\Models\Task::PRIORITIES as $value => $label)
                            <option value="{{ $value }}" @selected($filters['priority'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <label for="label_id" class="filter-label">Label</label>
                    <select id="label_id" name="label_id" class="filter-select max-w-[6.5rem] 2xl:max-w-[9rem]">
                        <option value="">Label</option>
                        @foreach ($labels as $l)
                            <option value="{{ $l->id }}" @selected($filters['label_id'] === $l->id)>{{ $l->name }}</option>
                        @endforeach
                    </select>

                    @include('partials.my-tasks-toggle', ['url' => $mineUrl, 'mine' => $mine, 'label' => 'My tasks'])
                </x-filter-bar>
            </x-slot>
        </x-page-header>
    </x-slot>

    {{-- full-height: on lg the board fills the viewport; columns scroll their own cards
         and the horizontal scrollbar stays in view. Phones keep normal page scroll. --}}
    <div class="py-3 lg:flex lg:min-h-0 lg:flex-1 lg:flex-col lg:pb-2">
        <div class="app-container-tight space-y-3 lg:flex lg:min-h-0 lg:flex-1 lg:flex-col">

            {{-- Summary and, for admins, bulk selection: one slim row --}}
            <div class="card flex flex-none flex-wrap items-center gap-x-4 gap-y-1.5 px-3.5 py-2">
                <p class="flex items-baseline gap-1">
                    <span class="board-total tabular text-base font-semibold tracking-tight text-slate-900">{{ $total }}</span>
                    <span class="board-total-label text-sm text-slate-500">{{ Str::plural('task', $total) }}</span>
                </p>
                <p class="flex items-baseline gap-1">
                    <span class="board-done-pct tabular text-base font-semibold tracking-tight text-emerald-600">{{ $donePct }}%</span>
                    <span class="text-sm text-slate-500">done</span>
                </p>
                <form method="GET" action="{{ route('board.index') }}" class="flex-none">
                    @foreach (request()->except('group') as $key => $value)
                        @if (is_scalar($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <label for="board-group" class="sr-only">Group cards</label>
                    <select id="board-group" name="group" data-native onchange="this.form.requestSubmit()"
                            class="h-7 rounded-md border-slate-200 bg-white py-0 pl-2 pr-7 text-xs font-medium text-slate-600">
                        <option value="">No grouping</option>
                        <option value="assignee" @selected($group === 'assignee')>By person</option>
                        <option value="priority" @selected($group === 'priority')>By priority</option>
                    </select>
                </form>
                <div class="flex min-w-[6rem] flex-1 items-center">
                    <div class="board-progress flex h-2 flex-1 gap-0.5 overflow-hidden rounded-full bg-slate-100" role="img"
                         aria-label="Tasks by status">
                        @foreach ($statuses as $status => $label)
                            <span class="board-seg h-full transition-[width] duration-300 first:rounded-l-full last:rounded-r-full" data-status="{{ $status }}"
                                  style="width: {{ $total > 0 ? $counts[$status] / $total * 100 : 0 }}%; background-color: {{ $tone($status)['hex'] }}"
                                  title="{{ $label }}"></span>
                        @endforeach
                    </div>
                </div>

                @if ($canDelete)
                    <div id="board-bulk" class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-sm" data-filter-scope="{{ $filterScope }}">
                        <label class="flex cursor-pointer items-center gap-2 font-medium text-slate-700">
                            <input type="checkbox" class="board-select board-select-all">
                            <span>Select all <span class="board-shown-count tabular">0</span> shown</span>
                        </label>
                        <span class="text-xs text-slate-500">{{ $filterScope !== '' ? 'Only '.$filterScope : 'Whole board, no filters' }}</span>
                        <span class="board-archive-note text-xs text-slate-500 [&[hidden]]:hidden" hidden></span>
                        <a href="{{ route('tasks.trash') }}" class="text-xs font-medium text-brand-700 hover:text-brand-800">Trash</a>

                        <div class="board-bulk-actions flex items-center gap-2 [&[hidden]]:hidden" hidden>
                            <span class="board-selected-count tabular text-xs font-semibold text-slate-700" aria-live="polite"></span>
                            <button type="button" class="board-clear btn-ghost btn-sm">Clear</button>
                            <form id="board-bulk-form" method="POST" action="{{ route('board.tasks.bulk-destroy') }}" data-confirm="">
                                @csrf
                                <button class="board-bulk-delete btn-sm btn bg-rose-600 text-white shadow-sm hover:bg-rose-700 disabled:opacity-50">Delete</button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Columns --}}
            <div class="board-scroller flex items-start gap-3 pb-2 lg:min-h-0 lg:flex-1 lg:items-stretch">
                @foreach ($statuses as $status => $label)
                    @php $cards = $columns[$status]; $t = $tone($status); $isArchive = $status === 'archive'; @endphp
                    <section @class(['board-column lg:min-h-0', $t['col'], $columnWidth => ! $isArchive])
                             data-status="{{ $status }}" aria-labelledby="col-{{ $status }}"
                             @if ($isArchive)
                                 x-data="{
                                     collapsed: (() => { try { return localStorage.getItem('rp.board.archive') !== 'open'; } catch (e) { return true; } })(),
                                     toggle() { this.collapsed = ! this.collapsed; try { localStorage.setItem('rp.board.archive', this.collapsed ? 'closed' : 'open'); } catch (e) {} this.$nextTick(() => this.$dispatch('board-archive-toggled')); },
                                 }"
                                 x-init="$nextTick(() => $dispatch('board-archive-toggled'))"
                                 data-collapsed="true" :data-collapsed="collapsed ? 'true' : 'false'"
                                 :class="collapsed ? 'w-12 !px-1.5' : '{{ $columnWidth }}'"
                             @endif>

                        @if ($isArchive)
                            {{-- Collapsed rail --}}
                            <button type="button" x-show="collapsed" @click="toggle()" aria-controls="col-list-{{ $status }}" :aria-expanded="(! collapsed).toString()"
                                    class="flex w-full flex-col items-center gap-3 rounded-lg py-2 text-slate-500 transition-colors hover:bg-white/70 hover:text-slate-700">
                                <span class="board-count tabular rounded-full px-1.5 py-0.5 text-xs font-semibold {{ $t['pill'] }}">{{ $cards->count() }}</span>
                                <span class="text-xs font-semibold [writing-mode:vertical-rl]">{{ $label }}</span>
                                <x-icon name="chevron-double-left" variant="outline" class="h-4 w-4 rotate-180" />
                            </button>
                        @endif

                        <div @if ($isArchive) x-show="! collapsed" x-cloak @endif class="flex min-h-0 flex-1 flex-col">
                            <header class="mb-3 flex flex-none items-center gap-2 px-1">
                                <span class="h-2 w-2 rounded-full {{ $t['dot'] }}"></span>
                                <h2 id="col-{{ $status }}" class="text-sm font-semibold text-slate-800">{{ $label }}</h2>
                                @php
                                    $limit = $showLimits ? ($wipLimits[$status] ?? null) : null;
                                    $limitable = $showLimits && ! in_array($status, \App\Models\Task::DONE_STATUSES, true);
                                @endphp
                                <span class="ml-auto flex items-center gap-1">
                                    <span @class(['board-count-wrap tabular rounded-full px-2 py-0.5 text-xs font-semibold', $t['pill'] => ! ($limit && $cards->count() > $limit), 'is-over bg-danger-100 text-danger-700' => $limit && $cards->count() > $limit])
                                          @if ($limit) data-limit="{{ $limit }}" title="{{ $cards->count() > $limit ? 'Over the limit of '.$limit : 'Limit '.$limit }}" @endif>
                                        <span class="board-count">{{ $cards->count() }}</span>@if ($limit)<span class="font-normal opacity-70"> / {{ $limit }}</span>@endif
                                    </span>
                                    @if ($canSetLimits && $limitable)
                                        <span x-data="{ open: false }" class="relative" @click.outside="open = false" @keydown.escape="open = false">
                                            <button type="button" @click="open = ! open" class="btn-ghost btn-sm btn-icon h-6 w-6 text-slate-400" aria-label="Set a limit for {{ $label }}" title="Set a limit">
                                                <x-icon name="scale" variant="outline" class="h-3.5 w-3.5" />
                                            </button>
                                            <form x-show="open" x-cloak method="POST" action="{{ route('board.wip-limits') }}"
                                                  class="menu-panel absolute right-0 z-30 mt-1 flex w-44 items-center gap-2 p-2">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="team_id" value="{{ $filters['team_id'] }}">
                                                <input type="hidden" name="status" value="{{ $status }}">
                                                <label for="limit-{{ $status }}" class="sr-only">Most tasks in {{ $label }}</label>
                                                <input id="limit-{{ $status }}" type="number" name="max_tasks" min="1" max="999" value="{{ $limit }}" placeholder="No limit" class="field-control field-sm min-w-0 flex-1">
                                                <button class="btn-primary btn-sm">Save</button>
                                            </form>
                                        </span>
                                    @endif
                                </span>
                                @if ($isArchive)
                                    <button type="button" @click="toggle()" class="btn-ghost btn-sm btn-icon -mr-1 h-7 w-7 text-slate-500" aria-label="Collapse {{ $label }}">
                                        <x-icon name="chevron-double-left" variant="outline" class="h-4 w-4" />
                                    </button>
                                @endif
                            </header>

                            <div id="col-list-{{ $status }}" class="board-list board-cards flex min-h-[72px] flex-1 flex-col gap-2.5">
                                @foreach ($cards as $task)
                                    @if ($group && ($loop->first || $cards[$loop->index - 1]->lane !== $task->lane))
                                        <p class="board-lane flex-none truncate px-1 pt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $task->lane }}</p>
                                    @endif
                                    @include('partials.board-card', ['task' => $task, 'statuses' => $statuses, 'canDelete' => $canDelete])
                                @endforeach
                                <p class="board-empty flex flex-none flex-col items-center gap-1 rounded-xl border-2 border-dashed border-slate-300/70 px-3 py-6 text-center text-xs font-medium text-slate-400"
                                   @if ($cards->count()) style="display:none" @endif>
                                    <x-icon name="squares" variant="outline" class="h-5 w-5" />
                                    Drop tasks here
                                </p>
                            </div>

                            {{-- Add a card --}}
                            @php $reopen = $errors->any() && old('status') === $status; @endphp
                            <div x-data="{ adding: @js($reopen) }" class="mt-2.5 flex-none">
                                <button type="button" x-show="!adding" @click="adding = true; $nextTick(() => $refs.title.focus())"
                                        class="flex w-full items-center gap-1.5 rounded-lg px-2 py-1.5 text-left text-xs font-medium text-slate-500 transition-colors hover:bg-white/80 hover:text-slate-800">
                                    <x-icon name="plus" variant="outline" class="h-4 w-4" />
                                    Add a card
                                </button>
                                <form x-show="adding" x-cloak method="POST" action="{{ route('board.tasks.store') }}"
                                      class="space-y-2 rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $status }}">
                                    <label for="new-card-{{ $status }}" class="sr-only">New card title</label>
                                    <textarea id="new-card-{{ $status }}" name="title" x-ref="title" rows="2" required maxlength="255" placeholder="What needs doing?"
                                              @keydown.escape="adding = false"
                                              class="field-control">{{ $reopen ? old('title') : '' }}</textarea>
                                    @if (is_int($filters['team_id']))
                                        <input type="hidden" name="team_id" value="{{ $filters['team_id'] }}">
                                    @elseif ($me->hasLimitedAccess() && $teamChoice)
                                        {{-- On several teams: pick which one the card belongs to --}}
                                        <label for="new-card-team-{{ $status }}" class="sr-only">Team</label>
                                        <select id="new-card-team-{{ $status }}" name="team_id" required class="field-control field-sm">
                                            @foreach ($teams as $tm)
                                                <option value="{{ $tm->id }}">{{ $tm->name }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    @if ($release)
                                        <input type="hidden" name="release_id" value="{{ $release->id }}">
                                    @else
                                        <label for="new-card-release-{{ $status }}" class="sr-only">Release (optional)</label>
                                        <select id="new-card-release-{{ $status }}" name="release_id" class="field-control field-sm">
                                            <option value="">No release</option>
                                            @foreach ($releases as $r)
                                                <option value="{{ $r->id }}">{{ $r->name }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    <label for="new-card-assignee-{{ $status }}" class="sr-only">Assignee (optional)</label>
                                    <select id="new-card-assignee-{{ $status }}" name="assignee_id" data-ss-kind="person" class="field-control field-sm">
                                        {{-- Developers and QA: their own cards are theirs unless they pick a teammate. --}}
                                        @unless ($me->hasLimitedAccess())
                                            <option value="">Unassigned</option>
                                        @endunless
                                        @foreach ($users->filter->isActive() as $u)
                                            <option value="{{ $u->id }}" @selected($reopen ? (int) old('assignee_id') === $u->id : $me->hasLimitedAccess() && $u->id === $me->id)>{{ $u->name }}</option>
                                        @endforeach
                                    </select>
                                    @if ($reopen)
                                        <p class="text-xs text-danger-600" role="alert">{{ $errors->first() }}</p>
                                    @endif
                                    <div class="flex items-center gap-2">
                                        <button class="btn-primary btn-sm">Add card</button>
                                        <button type="button" @click="adding = false" class="btn-ghost btn-sm">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </div>

    @if (app(\App\Services\Ai\AiFeatures::class)->available('assignee_suggest'))
        @include('ai.partials.assignee-dialog')
    @endif

    @include('tasks._panel-drawer')

    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const labels = @json($statuses);
            let dragged = null;
            let origin = null;

            const toast = (type, message, action = null) => window.dispatchEvent(new CustomEvent('toast', { detail: { type, message, action } }));
            const columnOf = (status) => document.querySelector(`.board-column[data-status="${status}"]`);
            const listOf = (status) => columnOf(status)?.querySelector('.board-list');
            const snapshot = (card) => ({ list: card.parentElement, next: card.nextElementSibling });
            const statusOf = (card) => card.closest('.board-column').dataset.status;

            document.querySelectorAll('.board-card').forEach(bindCard);

            function bindCard(card) {
                card.addEventListener('dragstart', function (e) {
                    dragged = card;
                    origin = snapshot(card);
                    requestAnimationFrame(() => card.classList.add('is-dragging'));
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', card.dataset.taskId);
                });
                card.addEventListener('dragend', function () {
                    card.classList.remove('is-dragging');
                    document.querySelectorAll('.board-column.is-drop-target').forEach((c) => c.classList.remove('is-drop-target'));
                    dragged = null;
                    refresh();
                });

                // Keyboard / touch path: the card's "Move to" menu.
                const select = card.querySelector('.board-move');
                select?.addEventListener('change', function () {
                    const snap = snapshot(card);
                    const list = listOf(select.value);
                    if (!list) return;
                    list.insertBefore(card, list.querySelector('.board-empty'));
                    refresh();
                    select.focus({ preventScroll: true });
                    persist(card, select.value, snap);
                });
            }

            function getAfterElement(list, y) {
                const cards = [...list.querySelectorAll('.board-card:not(.is-dragging)')];
                return cards.reduce((closest, child) => {
                    const box = child.getBoundingClientRect();
                    const offset = y - box.top - box.height / 2;
                    if (offset < 0 && offset > closest.offset) return { offset, element: child };
                    return closest;
                }, { offset: -Infinity }).element || null;
            }

            document.querySelectorAll('.board-column').forEach(function (col) {
                const list = col.querySelector('.board-list');
                const empty = col.querySelector('.board-empty');

                col.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    if (!dragged) return;
                    col.classList.add('is-drop-target');
                    if (empty) empty.style.display = 'none';
                    const after = getAfterElement(list, e.clientY);
                    list.insertBefore(dragged, after ?? empty);
                });
                col.addEventListener('dragleave', function (e) {
                    if (!col.contains(e.relatedTarget)) col.classList.remove('is-drop-target');
                });
                col.addEventListener('drop', function (e) {
                    e.preventDefault();
                    col.classList.remove('is-drop-target');
                    if (!dragged || !origin) return;
                    persist(dragged, col.dataset.status, origin);
                });
            });

            function persist(card, status, snap) {
                const list = listOf(status);
                const orderedIds = [...list.querySelectorAll('.board-card')].map((c) => c.dataset.taskId);
                const moved = snap.list !== list;

                fetch('/board/tasks/' + card.dataset.taskId, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ status: status, ordered_ids: orderedIds }),
                }).then(function (r) {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    syncSelect(card);
                    if (moved) {
                        const limit = Number(columnOf(status).querySelector('[data-limit]')?.dataset.limit ?? 0);
                        const count = list.querySelectorAll('.board-card').length;
                        toast(limit && count > limit ? 'warning' : 'success', limit && count > limit
                            ? 'Moved to ' + (labels[status] ?? status) + ', which is now over its limit of ' + limit + '.'
                            : 'Moved to ' + (labels[status] ?? status));
                    }
                    // Grouped cards need the server to put them back under the right heading.
                    if (@json((bool) $group)) window.Turbo?.visit(window.location.href, { action: 'replace' });
                }).catch(function () {
                    // Put the card back where it was instead of reloading the board.
                    snap.list.insertBefore(card, snap.next && snap.next.parentElement === snap.list ? snap.next : snap.list.querySelector('.board-empty'));
                    syncSelect(card);
                    refresh();
                    toast('error', 'Couldn’t move the card. Please try again.');
                });
            }

            function syncSelect(card) {
                const select = card.querySelector('.board-move');
                if (select) select.value = statusOf(card);
            }

            // Assigning: the card holds a button, and its picker is built only when
            // opened, because who can be picked depends on the card's release.
            const assigneeFilter = @json($filters['assignee_id']);

            document.querySelector('.board-scroller').addEventListener('click', function (e) {
                const button = e.target.closest('.board-assign');
                if (button) openPicker(button);
            });

            // An accepted Claude suggestion arrives here (see aiAssignee in resources/js/ai.js).
            document.querySelector('.board-scroller').addEventListener('ai-assigned', function (e) {
                const button = e.target.querySelector('.board-assign');
                if (button) applyAssignee(button, e.detail);
            });

            function openPicker(button) {
                if (button.getAttribute('aria-busy') === 'true') return;
                button.setAttribute('aria-busy', 'true');

                fetch(button.dataset.url, { headers: { 'Accept': 'application/json' } }).then(function (r) {
                    if (!r.ok) throw new Error(String(r.status));
                    return r.json();
                }).then(function (data) {
                    if (button.isConnected) showPicker(button, data);
                }).catch(function (error) {
                    toast('error', error.message === '403'
                        ? 'You can’t change this task.'
                        : 'Couldn’t load the people. Please try again.');
                }).finally(function () {
                    button.removeAttribute('aria-busy');
                });
            }

            function showPicker(button, data) {
                const select = document.createElement('select');
                select.className = 'board-assign-select';
                select.dataset.ssKind = 'person';
                select.setAttribute('aria-label', 'Assignee of “' + button.dataset.title + '”');
                select.add(new Option('Unassigned', ''));
                data.people.forEach((person) => select.add(new Option(person.name, person.id)));
                select.value = data.assignee_id ?? '';

                let saving = false;
                let refocus = false;
                const holdsFocus = () => select.parentElement?.contains(document.activeElement);
                const dismiss = function () {
                    refocus ||= holdsFocus();
                    select.remove();
                    button.hidden = false;
                    if (refocus) button.focus({ preventScroll: true });
                };

                // The dropdown closes before it reports the change, so wait a tick to see if a save began.
                select.addEventListener('ss:close', () => setTimeout(() => { if (!saving) dismiss(); }));
                select.addEventListener('change', function () {
                    saving = true;
                    const assigneeId = select.value === '' ? null : Number(select.value);
                    // Disabling it drops focus, so note now where focus should return.
                    refocus = holdsFocus();
                    select.disabled = true;
                    saveAssignee(button, assigneeId, dismiss);
                });

                button.hidden = true;
                button.after(select);
                // The searchable-select observer enhances it first; a native select just shows.
                setTimeout(() => select.dispatchEvent(new CustomEvent('ss:open')));
            }

            function saveAssignee(button, assigneeId, dismiss) {
                fetch(button.dataset.assignUrl, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ assignee_id: assigneeId }),
                }).then(function (r) {
                    return r.json().catch(() => ({})).then((data) => ({ status: r.status, data }));
                }).then(function ({ status, data }) {
                    if (status === 422) throw new Error(data.errors?.assignee_id?.[0] ?? 'That person can’t be assigned here.');
                    if (status === 403) throw new Error('You can’t change this task.');
                    if (status < 200 || status >= 300) throw new Error('Couldn’t save the assignee. Please try again.');

                    dismiss();
                    applyAssignee(button, data);
                }).catch(function (error) {
                    dismiss();
                    toast('error', error.message);
                });
            }

            function applyAssignee(button, data) {
                const said = data.assignee_name ? 'Assigned to ' + data.assignee_name : 'Unassigned';
                const matches = assigneeFilter === null
                    || (assigneeFilter === 'none' ? data.assignee_id === null : data.assignee_id === assigneeFilter);

                const card = button.closest('.board-card');

                if (data.visible && matches) {
                    const hadFocus = document.activeElement === button;
                    button.outerHTML = data.html;
                    if (hadFocus) card.querySelector('.board-assign').focus({ preventScroll: true });
                    toast('success', said + '.');
                    return;
                }

                card.remove();
                refresh();
                @if ($canDelete) syncSelection(); @endif
                toast('success', said + '. It no longer matches this view.');
            }

            function refresh() {
                let total = 0;
                const counts = {};
                document.querySelectorAll('.board-column').forEach(function (col) {
                    const n = col.querySelectorAll('.board-card').length;
                    counts[col.dataset.status] = n;
                    total += n;
                    col.querySelectorAll('.board-count').forEach((el) => { el.textContent = n; });
                    col.querySelectorAll('[data-limit]').forEach(function (el) {
                        const limit = Number(el.dataset.limit);
                        const over = n > limit;
                        el.classList.toggle('bg-danger-100', over);
                        el.classList.toggle('text-danger-700', over);
                        el.title = over ? 'Over the limit of ' + limit : 'Limit ' + limit;
                    });
                    const empty = col.querySelector('.board-empty');
                    if (empty) empty.style.display = n === 0 ? '' : 'none';
                });

                document.querySelector('.board-total').textContent = total;
                document.querySelector('.board-total-label').textContent = total === 1 ? 'task' : 'tasks';
                document.querySelector('.board-done-pct').textContent = (total ? Math.round((counts.done || 0) / total * 100) : 0) + '%';
                document.querySelectorAll('.board-seg').forEach(function (seg) {
                    seg.style.width = (total ? (counts[seg.dataset.status] || 0) / total * 100 : 0) + '%';
                });
            }

            @if ($canDelete)
                // Deleting: the checkboxes hold the selection, and everything else is
                // worked out from them, so a restored Turbo snapshot just works.
                const bulk = document.getElementById('board-bulk');
                const bulkForm = document.getElementById('board-bulk-form');
                const selectAll = bulk.querySelector('.board-select-all');
                const bulkDelete = bulk.querySelector('.board-bulk-delete');
                const scroller = document.querySelector('.board-scroller');
                const count = (n, word) => n + ' ' + word + (n === 1 ? '' : 's');

                const cardBoxes = () => [...scroller.querySelectorAll('.board-select')];
                // Cards in a collapsed Archive column aren't "shown". This reads the column's
                // state, not the layout: x-show only applies on the next frame.
                const isShown = (box) => !box.closest('[data-collapsed="true"]');

                function syncSelection() {
                    const boxes = cardBoxes();
                    // Collapsing Archive drops its cards from the selection: nothing hidden can be deleted.
                    boxes.forEach((box) => { if (box.checked && !isShown(box)) box.checked = false; });

                    const shown = boxes.filter(isShown);
                    const selected = shown.filter((box) => box.checked);
                    const left = boxes.length - shown.length;

                    boxes.forEach((box) => box.closest('.board-card').classList.toggle('is-selected', box.checked));
                    selectAll.disabled = shown.length === 0;
                    selectAll.checked = shown.length > 0 && selected.length === shown.length;
                    selectAll.indeterminate = selected.length > 0 && selected.length < shown.length;
                    bulk.querySelector('.board-shown-count').textContent = shown.length;

                    const note = bulk.querySelector('.board-archive-note');
                    note.hidden = left === 0;
                    note.textContent = left ? left + ' archived not included (column collapsed)' : '';

                    bulk.querySelector('.board-bulk-actions').hidden = selected.length === 0;
                    bulk.querySelector('.board-selected-count').textContent = selected.length + ' selected';
                    bulkDelete.textContent = 'Delete ' + selected.length;

                    // The confirm modal reads these attributes when the form submits.
                    const subtasks = selected.reduce((sum, box) => sum + Number(box.dataset.subtasks), 0);
                    const scope = bulk.dataset.filterScope;
                    bulkForm.dataset.confirmTitle = 'Delete ' + count(selected.length, 'task') + '?';
                    bulkForm.dataset.confirmVerb = 'Delete ' + count(selected.length, 'task');
                    bulkForm.dataset.confirm = (subtasks
                        ? 'This also deletes ' + count(subtasks, 'subtask') + ' and all comments. '
                        : 'Their comments go with them. ')
                        + (scope ? 'Selected from the board filtered to ' + scope + '. ' : '')
                        + 'An admin can restore them for ' + @json(\App\Models\Task::TRASH_DAYS) + ' days.';
                }

                scroller.addEventListener('change', function (e) {
                    if (e.target.classList.contains('board-select')) syncSelection();
                });
                scroller.addEventListener('board-archive-toggled', syncSelection);
                selectAll.addEventListener('change', function () {
                    cardBoxes().filter(isShown).forEach((box) => { box.checked = selectAll.checked; });
                    syncSelection();
                });
                bulk.querySelector('.board-clear').addEventListener('click', function () {
                    cardBoxes().forEach((box) => { box.checked = false; });
                    syncSelection();
                });

                // The confirm modal stops the first submit and re-submits once confirmed.
                // That second one reaches here unprevented; stopping it keeps Turbo out.
                bulkForm.addEventListener('submit', function (e) {
                    if (e.defaultPrevented) return;
                    e.preventDefault();
                    const ids = cardBoxes().filter((box) => box.checked).map((box) => Number(box.value));
                    sendDelete(bulkForm.action, 'POST', { ids }, bulkDelete);
                });
                scroller.querySelectorAll('.board-delete-form').forEach(function (form) {
                    form.addEventListener('submit', function (e) {
                        if (e.defaultPrevented) return;
                        e.preventDefault();
                        sendDelete(form.action, 'DELETE', null, form.querySelector('button'));
                    });
                });

                function undoDelete(ids) {
                    fetch(@json(route('tasks.restore')), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        body: JSON.stringify({ ids }),
                    }).then(function (r) {
                        if (!r.ok) throw new Error(String(r.status));
                        window.Turbo?.visit(window.location.href, { action: 'replace' });
                    }).catch(function () {
                        toast('error', 'Couldn’t restore. The tasks are still in the trash.');
                    });
                }

                function sendDelete(url, method, body, button) {
                    button.disabled = true;
                    fetch(url, {
                        method,
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        body: body ? JSON.stringify(body) : undefined,
                    }).then(function (r) {
                        if (!r.ok) throw new Error(String(r.status));
                        return r.json();
                    }).then(function (data) {
                        data.deleted_ids.forEach((id) => scroller.querySelector(`.board-card[data-task-id="${id}"]`)?.remove());
                        refresh();
                        syncSelection();
                        toast('success', count(data.deleted_ids.length, 'task') + ' moved to the trash'
                            + (data.subtasks_deleted ? ', with ' + count(data.subtasks_deleted, 'subtask') : '') + '.',
                            { label: 'Undo', run: () => undoDelete(data.deleted_ids) });
                    }).catch(function (error) {
                        // Nothing was deleted: the server takes all of the ids or none.
                        const stale = ['404', '422'].includes(error.message);
                        const denied = error.message === '403';
                        toast('error', stale ? 'Some of these tasks are no longer on the board. Reload it and try again.'
                            : denied ? 'You don’t have permission to delete these tasks.'
                            : 'Couldn’t delete. Please try again.');
                    }).finally(function () {
                        button.disabled = false;
                    });
                }

                syncSelection();
            @endif
        })();
    </script>
</x-app-layout>
