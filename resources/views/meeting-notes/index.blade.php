<x-app-layout>
    @php
        $hasFilters = $filter || $type || $author || $attendee || $search || $from || $to;
    @endphp

    <x-slot name="header">
        <x-page-header title="Meeting notes" description="Minutes, decisions and action items from every meeting.">
            <x-slot name="actions">
                <a href="{{ route('meeting-notes.create', $filter && $filter !== 'general' ? ['release' => $filter] : []) }}" class="btn-primary btn-sm">
                    <x-icon name="plus" variant="outline" class="h-4 w-4" />
                    New meeting note
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-4">
            {{-- One GET form so every filter composes; Clear drops all of them. --}}
            <x-filter-bar :action="route('meeting-notes.index')" :active="$hasFilters" :count="$notes->total()" noun="note">
                <x-slot name="search">
                    <label for="search" class="filter-label">Search</label>
                    <input id="search" name="search" type="search" value="{{ $search }}"
                           placeholder="Search title or discussion…" class="filter-search">
                </x-slot>

                <label for="type" class="filter-label">Type</label>
                <select id="type" name="type" class="filter-select">
                    <option value="">All types</option>
                    @foreach (\App\Models\MeetingNote::TYPES as $val => $label)
                        <option value="{{ $val }}" @selected($type === $val)>{{ $label }}</option>
                    @endforeach
                </select>

                <label for="release" class="filter-label">Release</label>
                <select id="release" name="release" class="filter-select max-w-[13rem]">
                    <option value="" @selected(! $filter)>All releases</option>
                    <option value="general" @selected($filter === 'general')>General (no release)</option>
                    @foreach ($releases as $r)
                        <option value="{{ $r->id }}" @selected((string) $filter === (string) $r->id)>{{ $r->name }} ({{ $r->year }})</option>
                    @endforeach
                </select>

                <label for="author" class="filter-label">Written by</label>
                <select id="author" name="author" class="filter-select max-w-[11rem]">
                    <option value="">Any author</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected((int) $author === $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>

                <label for="attendee" class="filter-label">Attended by</label>
                <select id="attendee" name="attendee" class="filter-select max-w-[11rem]">
                    <option value="">Any attendee</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected((int) $attendee === $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>

                <div class="flex items-center gap-1.5">
                    <label for="from" class="filter-label">Meeting date from</label>
                    <input id="from" type="date" name="from" value="{{ $from }}" class="filter-date" title="From">
                    <span class="text-xs text-slate-400" aria-hidden="true">to</span>
                    <label for="to" class="filter-label">Meeting date to</label>
                    <input id="to" type="date" name="to" value="{{ $to }}" class="filter-date" title="To">
                </div>
            </x-filter-bar>

            @if ($notes->isEmpty())
                <div class="card">
                    @if ($hasFilters)
                        <x-empty-state icon="funnel" title="No meeting notes match these filters."
                                       description="Try widening the date range or removing a filter.">
                            <a href="{{ route('meeting-notes.index') }}" class="btn-secondary btn-sm">Clear all filters</a>
                        </x-empty-state>
                    @else
                        <x-empty-state icon="chat-bubbles" title="No meeting notes yet."
                                       description="Capture what was discussed, decided and assigned — linked to a release or kept general.">
                            <a href="{{ route('meeting-notes.create') }}" class="btn-primary btn-sm">
                                <x-icon name="plus" variant="outline" class="h-4 w-4" />Write the first one
                            </a>
                        </x-empty-state>
                    @endif
                </div>
            @else
                <ul class="list-card">
                    @foreach ($notes as $note)
                        @php
                            $excerpt = \App\Support\HtmlSanitizer::isEmpty($note->body)
                                ? null
                                // Break block-level tags into spaces so "Monday.</div><ul><li>QA" reads "Monday. QA".
                                : Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('#</(div|p|li|h[1-6]|blockquote)>|<br\s*/?>#i', ' ', (string) $note->bodyPreviewHtml())))), 160);
                        @endphp
                        <li>
                            <a href="{{ route('meeting-notes.show', $note) }}"
                               class="group flex flex-col gap-3 px-4 py-4 transition-colors hover:bg-slate-50/80 sm:flex-row sm:items-center sm:gap-6 sm:px-5">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-meeting-note-type :note="$note" />
                                        <h3 class="truncate text-sm font-semibold text-slate-900 group-hover:text-brand-700">{{ $note->title }}</h3>
                                        @if ($note->isAttendeesOnly())
                                            <span class="badge badge-warning" title="Visible to attendees, the author, and team leads only">
                                                <x-icon name="lock-closed" variant="outline" class="h-3 w-3" />Attendees only
                                            </span>
                                        @endif
                                    </div>
                                    @if ($excerpt)
                                        <p class="mt-1 line-clamp-1 text-sm text-slate-500">{{ $excerpt }}</p>
                                    @endif
                                </div>

                                <div class="flex flex-none flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-500 sm:justify-end">
                                    @if ($note->release)
                                        <span class="badge badge-brand max-w-[12rem] truncate">{{ $note->release->name }}</span>
                                    @else
                                        <span class="badge badge-slate">General</span>
                                    @endif
                                    <span class="tabular w-24 whitespace-nowrap">{{ $note->meeting_date->format('M j, Y') }}</span>
                                    @if ($note->attendees_count)
                                        <span class="whitespace-nowrap">{{ $note->attendees_count }} {{ Str::plural('attendee', $note->attendees_count) }}</span>
                                    @endif
                                    <span class="flex items-center gap-1.5 whitespace-nowrap" title="Written by {{ $note->author->name ?? 'Unknown' }}">
                                        <x-avatar :user="$note->author" :name="$note->author->name ?? '?'" size="sm" />
                                        <span class="hidden max-w-[8rem] truncate text-slate-600 lg:inline">{{ $note->author->name ?? 'Unknown' }}</span>
                                    </span>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div>{{ $notes->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
