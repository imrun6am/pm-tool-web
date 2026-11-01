@php
    use App\Models\Note;
    use Illuminate\Support\Carbon;

    $hasFilters = $date || $from || $to || $author;
    // Start the composer open when a previous submit failed or left input behind.
    $composerOpen = $errors->hasAny(['body', 'date', 'visibility', 'recipients']) || old('body') !== null;
    $viewer = auth()->user();
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Notes" description="Daily notes: keep them private, share with everyone, or with specific people." />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            {{-- Adding, editing and deleting notes update this region in place. --}}
            <x-live-region id="notes" class="space-y-6">

                {{-- Filters: author + a date range (a legacy single `date` stays supported) --}}
                <x-filter-bar :action="route('notes.index')" :active="$hasFilters" :count="$notes->total()" noun="note">
                    <label for="author" class="filter-label">Author</label>
                    <select id="author" name="author" class="filter-select">
                        <option value="">Anyone</option>
                        @foreach ($authors as $a)
                            <option value="{{ $a->id }}" @selected($author === $a->id)>{{ $a->id === $viewer->id ? 'Me' : $a->name }}</option>
                        @endforeach
                    </select>

                    <div class="flex items-center gap-1.5">
                        <label for="from" class="filter-label">From date</label>
                        <input id="from" type="date" name="from" value="{{ $from }}" class="filter-date" title="From">
                        <span class="text-xs text-slate-400" aria-hidden="true">to</span>
                        <label for="to" class="filter-label">To date</label>
                        <input id="to" type="date" name="to" value="{{ $to }}" class="filter-date" title="To">
                    </div>

                    @if ($date)
                        <input type="hidden" name="date" value="{{ $date }}">
                        <span class="badge badge-brand h-7 px-2.5">
                            {{ Carbon::parse($date)->format('D, M j') }}
                        </span>
                    @endif
                </x-filter-bar>

                {{-- Composer: a one-line prompt that expands into the editor --}}
                <div class="card" x-data="{ open: @js($composerOpen) }">
                    <button type="button" x-show="!open" @click="open = true; $nextTick(() => $root.querySelector('trix-editor')?.focus())"
                            class="flex w-full items-center gap-3 rounded-xl px-4 py-3.5 text-left text-sm text-slate-500 transition-colors hover:bg-slate-50 sm:px-5">
                        <x-avatar :user="$viewer" />
                        <span class="flex-1">Write a note…</span>
                        <x-icon name="pencil-square" variant="outline" class="h-5 w-5 text-slate-400" />
                    </button>

                    <form x-show="open" x-cloak method="POST" action="{{ route('notes.store') }}" data-turbo-action="advance" class="p-4 sm:p-5"
                          x-data="{ vis: '{{ old('visibility', 'private') }}' }">
                        @csrf
                        <div class="mb-3 flex items-center gap-3">
                            <x-avatar :user="$viewer" />
                            <p class="text-sm font-semibold text-slate-900">New note</p>
                        </div>

                        <input id="note-add-body" type="hidden" name="body" value="{{ old('body') }}">
                        <trix-editor input="note-add-body" placeholder="Jot something down…" class="prose-notes"
                                     @invalid('body')></trix-editor>
                        @error('body')
                            <p id="body-error" class="field-error"><x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $message }}</p>
                        @enderror
                        <x-ai-enhance input="note-add-body" kind="note" />

                        <div class="mt-4 grid gap-4 sm:grid-cols-[10rem_12rem_1fr]">
                            <x-field label="Date" for="note-date" name="date">
                                <input id="note-date" type="date" name="date" value="{{ old('date', $today) }}" class="field-input field-sm"
                                       @invalid('date')>
                            </x-field>
                            <x-field label="Visibility" for="note-visibility" name="visibility">
                                <select id="note-visibility" name="visibility" x-model="vis" class="field-select field-sm">
                                    @foreach (Note::VISIBILITIES as $val => $label)
                                        <option value="{{ $val }}" @selected(old('visibility', 'private') === $val)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </x-field>
                            <div x-show="vis === 'specific'" x-cloak>
                                <x-field label="Share with" name="recipients">
                                    <div class="mt-1.5">
                                        <x-multi-select
                                            name="recipients"
                                            :options="$users->map(fn ($u) => ['value' => $u->id, 'label' => $u->name, 'hint' => $u->roleLabel()])"
                                            :selected="[]"
                                            placeholder="Choose people…" />
                                    </div>
                                </x-field>
                            </div>
                        </div>

                        <div class="mt-5 flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                            <button type="button" @click="open = false" class="btn-ghost btn-sm">Cancel</button>
                            <button class="btn-primary btn-sm">Add note</button>
                        </div>
                    </form>
                </div>

                {{-- Feed: newest first, grouped by day --}}
                @if ($notes->isEmpty())
                    <div class="card">
                        @if ($date || $from || $to || $author)
                            <x-empty-state icon="funnel" title="No notes match this filter."
                                           description="Try a different author or date range.">
                                <a href="{{ route('notes.index') }}" class="btn-secondary btn-sm">Clear filters</a>
                            </x-empty-state>
                        @else
                            <x-empty-state icon="pencil-square" title="No notes yet — add your first above."
                                           description="Notes are a quick way to log what happened today and share it with your team." />
                        @endif
                    </div>
                @else
                    @foreach ($notes->getCollection()->groupBy(fn ($n) => $n->date->toDateString()) as $day => $dayNotes)
                        @php
                            $d = Carbon::parse($day);
                            $heading = match (true) {
                                $d->isToday() => 'Today',
                                $d->isYesterday() => 'Yesterday',
                                $d->year === now()->year => $d->format('D, M j'),
                                default => $d->format('D, M j, Y'),
                            };
                        @endphp
                        <section aria-labelledby="day-{{ $day }}">
                            <h2 id="day-{{ $day }}" class="mb-2.5 flex items-center gap-3 px-1">
                                <span class="text-sm font-semibold text-slate-900">{{ $heading }}</span>
                                <span class="h-px flex-1 bg-slate-200" aria-hidden="true"></span>
                                <span class="text-xs text-slate-500">{{ $dayNotes->count() }} {{ Str::plural('note', $dayNotes->count()) }}</span>
                            </h2>
                            <div class="list-card">
                                @foreach ($dayNotes as $note)
                                    @include('notes._card', ['note' => $note, 'users' => $users])
                                @endforeach
                            </div>
                        </section>
                    @endforeach

                    <div>{{ $notes->links() }}</div>
                @endif
            </x-live-region>
        </div>
    </div>
</x-app-layout>
