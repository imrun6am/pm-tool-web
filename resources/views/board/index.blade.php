<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="page-title">Board</h2>
                @if ($release)
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $release->name }} ·
                        <a href="{{ route('releases.show', $release) }}" class="text-indigo-600 hover:underline">back to release</a>
                    </p>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="app-container space-y-6">

            {{-- Filters --}}
            <form method="GET" action="{{ route('board.index') }}" class="card p-4">
                <div class="grid gap-4 sm:grid-cols-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Release</label>
                        <select name="release_id" class="field-input">
                            <option value="">All releases</option>
                            @foreach ($releases as $r)
                                <option value="{{ $r->id }}" @selected($filters['release_id'] === $r->id)>{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Assignee</label>
                        <select name="assignee_id" class="field-input">
                            <option value="">Anyone</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected($filters['assignee_id'] === $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-2 sm:col-span-2">
                        <button class="btn-primary btn-sm">Apply</button>
                        <a href="{{ route('board.index') }}" class="btn-secondary btn-sm">Reset</a>
                        <span class="ml-auto self-center text-xs text-slate-400">Drag cards between columns to change status.</span>
                    </div>
                </div>
            </form>

            {{-- Columns --}}
            <div class="flex gap-4 overflow-x-auto pb-4">
                @foreach ($statuses as $status => $label)
                    @php
                        $cards = $columns[$status];
                        $dot = match ($status) {
                            'todo' => 'bg-slate-400',
                            'in_progress' => 'bg-blue-500',
                            'in_review' => 'bg-amber-500',
                            'recheck' => 'bg-orange-500',
                            'done' => 'bg-emerald-500',
                            'archive' => 'bg-slate-500',
                            default => 'bg-slate-400',
                        };
                    @endphp
                    <div class="board-column flex w-72 flex-none flex-col rounded-xl bg-slate-100/70 p-3" data-status="{{ $status }}">
                        <div class="mb-3 flex items-center gap-2 px-1">
                            <span class="h-2.5 w-2.5 rounded-full {{ $dot }}"></span>
                            <h3 class="text-sm font-semibold text-slate-700">{{ $label }}</h3>
                            <span class="board-count ml-auto rounded-full bg-white px-2 py-0.5 text-xs font-medium text-slate-500">{{ $cards->count() }}</span>
                        </div>
                        <div class="board-list flex min-h-[60px] flex-1 flex-col gap-2">
                            @foreach ($cards as $task)
                                @include('partials.board-card', ['task' => $task])
                            @endforeach
                            <p class="board-empty px-1 py-6 text-center text-xs text-slate-400" @if($cards->count()) style="display:none" @endif>Drop tasks here</p>
                        </div>

                        {{-- Add a card --}}
                        <div x-data="{ adding: false }" class="mt-2">
                            <button type="button" x-show="!adding" @click="adding = true; $nextTick(() => $refs.title.focus())"
                                    class="flex w-full items-center gap-1.5 rounded-lg px-2 py-1.5 text-left text-xs font-medium text-slate-500 transition hover:bg-slate-200/70">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"/></svg>
                                Add a card
                            </button>
                            <form x-show="adding" x-cloak method="POST" action="{{ route('board.tasks.store') }}" class="space-y-2 rounded-lg border border-slate-200 bg-white p-2 shadow-sm">
                                @csrf
                                <input type="hidden" name="status" value="{{ $status }}">
                                <textarea name="title" x-ref="title" rows="2" required maxlength="255" placeholder="What needs doing?"
                                          @keydown.escape="adding = false"
                                          class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                                @if ($release)
                                    <input type="hidden" name="release_id" value="{{ $release->id }}">
                                @else
                                    <select name="release_id" required class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                        <option value="">Select release…</option>
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
                @endforeach
            </div>
        </div>
    </div>

    <script>
        (function () {
            const meta = document.querySelector('meta[name="csrf-token"]');
            const csrf = meta ? meta.content : '';
            let dragged = null;

            document.querySelectorAll('.board-card').forEach(bindCard);

            function bindCard(card) {
                card.addEventListener('dragstart', function (e) {
                    dragged = card;
                    setTimeout(() => card.classList.add('opacity-40'), 0);
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', card.dataset.taskId);
                });
                card.addEventListener('dragend', function () {
                    if (dragged) dragged.classList.remove('opacity-40');
                    dragged = null;
                    updateCounts();
                });
            }

            function getAfterElement(list, y) {
                const cards = [...list.querySelectorAll('.board-card:not(.opacity-40)')];
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
                    if (empty) empty.style.display = 'none';
                    const after = getAfterElement(list, e.clientY);
                    if (after == null) list.insertBefore(dragged, empty);
                    else list.insertBefore(dragged, after);
                });
                col.addEventListener('drop', function (e) {
                    e.preventDefault();
                    if (!dragged) return;
                    const status = col.dataset.status;
                    const taskId = dragged.dataset.taskId;
                    const orderedIds = [...list.querySelectorAll('.board-card')].map(c => c.dataset.taskId);
                    persist(taskId, status, orderedIds);
                });
            });

            function persist(taskId, status, orderedIds) {
                fetch('/board/tasks/' + taskId, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ status: status, ordered_ids: orderedIds }),
                }).then(function (r) {
                    if (!r.ok) location.reload();
                }).catch(function () { location.reload(); });
            }

            function updateCounts() {
                document.querySelectorAll('.board-column').forEach(function (col) {
                    const n = col.querySelectorAll('.board-card').length;
                    const count = col.querySelector('.board-count');
                    const empty = col.querySelector('.board-empty');
                    if (count) count.textContent = n;
                    if (empty) empty.style.display = n === 0 ? '' : 'none';
                });
            }
        })();
    </script>
</x-app-layout>
