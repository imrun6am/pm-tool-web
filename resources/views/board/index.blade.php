@php
    use Illuminate\Support\Str;

    $hasFilters = $filters['release_id'] || $filters['assignee_id'];
    $me = auth()->user();

    // "My tasks" toggles the assignee filter and keeps the release.
    $mine = $filters['assignee_id'] === $me->id;
    $mineUrl = route('board.index', array_filter([
        'release_id' => $filters['release_id'],
        'assignee_id' => $mine ? null : $me->id,
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
@endphp
<x-app-layout full-height>
    <x-slot name="header">
        <x-page-header title="Board" description="Drag cards between columns, or use a card's Move menu, to change status."
                       :breadcrumbs="$release ? [$release->name => route('releases.show', $release), 'Board' => null] : []" />
    </x-slot>

    {{-- full-height: on lg the board fills the viewport; columns scroll their own cards
         and the horizontal scrollbar stays in view. Phones keep normal page scroll. --}}
    <div class="py-6 sm:py-8 lg:flex lg:min-h-0 lg:flex-1 lg:flex-col lg:pb-4">
        <div class="app-container space-y-4 lg:flex lg:min-h-0 lg:flex-1 lg:flex-col">

            {{-- Filters --}}
            <x-filter-bar :action="route('board.index')" :active="$hasFilters" class="lg:flex-none">
                <label for="release_id" class="filter-label">Release</label>
                <select id="release_id" name="release_id" class="filter-select max-w-[14rem]">
                    <option value="">All releases</option>
                    <option value="none" @selected($filters['release_id'] === 'none')>No release</option>
                    @foreach ($releases as $r)
                        <option value="{{ $r->id }}" @selected($filters['release_id'] === $r->id)>{{ $r->name }}</option>
                    @endforeach
                </select>

                <label for="assignee_id" class="filter-label">Assignee</label>
                <select id="assignee_id" name="assignee_id" class="filter-select max-w-[13rem]">
                    <option value="">Anyone</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected($filters['assignee_id'] === $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>

                <a href="{{ $mineUrl }}" role="button" aria-pressed="{{ $mine ? 'true' : 'false' }}" @keydown.space.prevent="$el.click()"
                   @class([
                       'inline-flex h-9 items-center gap-1.5 rounded-lg px-3 text-sm font-medium ring-1 ring-inset transition-colors',
                       'bg-brand-600 text-white ring-brand-600 hover:bg-brand-700' => $mine,
                       'bg-white text-slate-700 ring-slate-300 hover:bg-slate-50' => ! $mine,
                   ])>
                    <x-avatar :user="$me" size="xs" @class(['ring-1 ring-white/60' => $mine]) />
                    My tasks
                </a>
            </x-filter-bar>

            {{-- Summary --}}
            <div class="card flex flex-none flex-wrap items-center gap-x-6 gap-y-3 px-5 py-3.5">
                <p class="flex items-baseline gap-1.5">
                    <span class="board-total tabular text-xl font-semibold tracking-tight text-slate-900">{{ $total }}</span>
                    <span class="board-total-label text-sm text-slate-500">{{ Str::plural('task', $total) }}</span>
                </p>
                <p class="flex items-baseline gap-1.5">
                    <span class="board-done-pct tabular text-xl font-semibold tracking-tight text-emerald-600">{{ $donePct }}%</span>
                    <span class="text-sm text-slate-500">done</span>
                </p>
                <div class="flex min-w-[12rem] flex-1 items-center gap-3">
                    <div class="board-progress flex h-2 flex-1 gap-0.5 overflow-hidden rounded-full bg-slate-100" role="img"
                         aria-label="Tasks by status">
                        @foreach ($statuses as $status => $label)
                            <span class="board-seg h-full transition-[width] duration-300 first:rounded-l-full last:rounded-r-full" data-status="{{ $status }}"
                                  style="width: {{ $total > 0 ? $counts[$status] / $total * 100 : 0 }}%; background-color: {{ $tone($status)['hex'] }}"
                                  title="{{ $label }}"></span>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Columns --}}
            <div class="board-scroller flex items-start gap-4 pb-3 lg:min-h-0 lg:flex-1 lg:items-stretch">
                @foreach ($statuses as $status => $label)
                    @php $cards = $columns[$status]; $t = $tone($status); $isArchive = $status === 'archive'; @endphp
                    <section @class(['board-column lg:min-h-0', $t['col'], 'w-[19rem]' => ! $isArchive])
                             data-status="{{ $status }}" aria-labelledby="col-{{ $status }}"
                             @if ($isArchive)
                                 x-data="{
                                     collapsed: (() => { try { return localStorage.getItem('rp.board.archive') !== 'open'; } catch (e) { return true; } })(),
                                     toggle() { this.collapsed = ! this.collapsed; try { localStorage.setItem('rp.board.archive', this.collapsed ? 'closed' : 'open'); } catch (e) {} },
                                 }"
                                 :class="collapsed ? 'w-12 !px-1.5' : 'w-[19rem]'"
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
                                <span class="board-count tabular ml-auto rounded-full px-2 py-0.5 text-xs font-semibold {{ $t['pill'] }}">{{ $cards->count() }}</span>
                                @if ($isArchive)
                                    <button type="button" @click="toggle()" class="btn-ghost btn-sm btn-icon -mr-1 h-7 w-7 text-slate-500" aria-label="Collapse {{ $label }}">
                                        <x-icon name="chevron-double-left" variant="outline" class="h-4 w-4" />
                                    </button>
                                @endif
                            </header>

                            <div id="col-list-{{ $status }}" class="board-list board-cards flex min-h-[72px] flex-1 flex-col gap-2.5">
                                @foreach ($cards as $task)
                                    @include('partials.board-card', ['task' => $task, 'statuses' => $statuses])
                                @endforeach
                                <p class="board-empty flex flex-none flex-col items-center gap-1 rounded-xl border-2 border-dashed border-slate-300/70 px-3 py-6 text-center text-xs font-medium text-slate-400"
                                   @if ($cards->count()) style="display:none" @endif>
                                    <x-icon name="squares" variant="outline" class="h-5 w-5" />
                                    Drop tasks here
                                </p>
                            </div>

                            {{-- Add a card --}}
                            <div x-data="{ adding: false }" class="mt-2.5 flex-none">
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
                                              class="field-control"></textarea>
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

    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const labels = @json($statuses);
            let dragged = null;
            let origin = null;

            const toast = (type, message) => window.dispatchEvent(new CustomEvent('toast', { detail: { type, message } }));
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
                    if (moved) toast('success', 'Moved to ' + (labels[status] ?? status));
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

            function refresh() {
                let total = 0;
                const counts = {};
                document.querySelectorAll('.board-column').forEach(function (col) {
                    const n = col.querySelectorAll('.board-card').length;
                    counts[col.dataset.status] = n;
                    total += n;
                    col.querySelectorAll('.board-count').forEach((el) => { el.textContent = n; });
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
        })();
    </script>
</x-app-layout>
