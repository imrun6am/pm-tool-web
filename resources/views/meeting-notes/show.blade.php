<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <h2 class="page-title">{{ $meetingNote->title }}</h2>
                <x-meeting-note-type :note="$meetingNote" />
                @if ($meetingNote->release)
                    <span class="rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700">{{ $meetingNote->release->name }}</span>
                @else
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-500">General</span>
                @endif
                @if ($meetingNote->isAttendeesOnly())
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700" title="Visible to attendees, the author, and team leads only">
                        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                        Attendees only
                    </span>
                @endif
            </div>
            <div class="flex items-center gap-3">
                @can('update', $meetingNote)
                    <a href="{{ route('meeting-notes.edit', $meetingNote) }}" class="btn-secondary btn-sm">Edit</a>
                @endcan
                @can('delete', $meetingNote)
                    <form method="POST" action="{{ route('meeting-notes.destroy', $meetingNote) }}" data-confirm="Delete this meeting note?" data-confirm-verb="Delete">
                        @csrf @method('DELETE')
                        <button class="rounded-md border border-rose-200 bg-white px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50">Delete</button>
                    </form>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="app-container space-y-6">
            <div class="card card-pad">
                <dl class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Meeting date</dt>
                        <dd class="mt-1 text-sm text-slate-800">{{ $meetingNote->meeting_date->format('D, M j, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Written by</dt>
                        <dd class="mt-1 text-sm text-slate-800">{{ $meetingNote->author->name ?? 'Unknown' }}@include('partials.user-tag', ['tagUser' => $meetingNote->author])</dd>
                    </div>
                    @if ($meetingNote->release)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Release</dt>
                            <dd class="mt-1 text-sm">
                                <a href="{{ route('releases.show', $meetingNote->release) }}" class="text-brand-600 hover:underline">{{ $meetingNote->release->name }}</a>
                            </dd>
                        </div>
                    @endif
                    @if ($meetingNote->event)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Event</dt>
                            <dd class="mt-1 text-sm">
                                <a href="{{ route('events.show', $meetingNote->event) }}" class="text-brand-600 hover:underline">{{ $meetingNote->event->title }}</a>
                            </dd>
                        </div>
                    @endif
                </dl>

                <div class="mt-6 border-t border-slate-100 pt-4">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Attendees ({{ $meetingNote->attendees->count() }})</dt>
                    @if ($meetingNote->attendees->isEmpty())
                        <p class="mt-1 text-sm text-slate-400">No attendees recorded.</p>
                    @else
                        <ul class="mt-2 flex flex-wrap gap-2">
                            @foreach ($meetingNote->attendees as $attendee)
                                <li class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-brand-100 text-[10px] font-semibold text-brand-700">{{ strtoupper(mb_substr($attendee->name, 0, 1)) }}</span>
                                    {{ $attendee->name }}@include('partials.user-tag', ['tagUser' => $attendee])
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="prose-notes mt-6 border-t border-slate-100 pt-6 text-sm text-slate-700">
                    {!! $meetingNote->bodyHtml() !!}
                </div>
            </div>

            <div>
                <a href="{{ route('meeting-notes.index') }}" class="text-sm text-slate-500 hover:text-brand-600">← Back to meeting notes</a>
            </div>
        </div>
    </div>
</x-app-layout>
