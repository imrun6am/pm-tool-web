<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$meetingNote->title"
                       :breadcrumbs="['Meeting notes' => route('meeting-notes.index'), $meetingNote->title => null]">
            <x-slot name="actions">
                @can('update', $meetingNote)
                    <a href="{{ route('meeting-notes.edit', $meetingNote) }}" class="btn-secondary btn-sm">
                        <x-icon name="pencil-square" variant="outline" class="h-4 w-4" />Edit
                    </a>
                @endcan
                @can('delete', $meetingNote)
                    <form method="POST" action="{{ route('meeting-notes.destroy', $meetingNote) }}" data-confirm="Delete this meeting note?" data-confirm-verb="Delete">
                        @csrf @method('DELETE')
                        <button class="btn-danger btn-sm">
                            <x-icon name="trash" variant="outline" class="h-4 w-4" />Delete
                        </button>
                    </form>
                @endcan
            </x-slot>
        </x-page-header>
        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-slate-500">
            <x-meeting-note-type :note="$meetingNote" />
            <span>{{ $meetingNote->meeting_date->format('l, F j, Y') }}</span>
            <span aria-hidden="true" class="text-slate-300">·</span>
            <span class="flex items-center gap-1.5">
                <x-avatar :user="$meetingNote->author" :name="$meetingNote->author->name ?? '?'" size="sm" />
                {{ $meetingNote->author->name ?? 'Unknown' }}@include('partials.user-tag', ['tagUser' => $meetingNote->author])
            </span>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <div class="mx-auto grid max-w-6xl items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">

                {{-- Document --}}
                <article class="card card-pad sm:p-8">
                    @if (\App\Support\HtmlSanitizer::isEmpty($meetingNote->body))
                        <p class="text-sm text-slate-500">No notes were written for this meeting.</p>
                    @else
                        <div class="prose-notes max-w-prose text-[15px] leading-7 text-slate-700 [&>*:first-child]:mt-0">
                            {!! $meetingNote->bodyHtml() !!}
                        </div>
                    @endif
                </article>

                {{-- Details --}}
                <aside class="space-y-4">
                    <section class="card">
                        <h2 class="card-header card-title">Details</h2>
                        <dl class="divide-y divide-slate-100 text-sm">
                            <div class="flex items-center justify-between gap-3 px-5 py-3">
                                <dt class="text-slate-500">Release</dt>
                                <dd class="min-w-0 truncate text-right">
                                    @if ($meetingNote->release)
                                        <a href="{{ route('releases.show', $meetingNote->release) }}" class="font-medium text-brand-700 hover:underline">{{ $meetingNote->release->name }}</a>
                                    @else
                                        <span class="badge badge-slate">General</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 px-5 py-3">
                                <dt class="text-slate-500">Visibility</dt>
                                <dd>
                                    @if ($meetingNote->isAttendeesOnly())
                                        <span class="badge badge-warning" title="Visible to attendees, the author, and team leads only">
                                            <x-icon name="lock-closed" variant="outline" class="h-3 w-3" />Attendees only
                                        </span>
                                    @else
                                        <span class="badge badge-info"><x-icon name="globe" variant="outline" class="h-3 w-3" />{{ $meetingNote->visibilityLabel() }}</span>
                                    @endif
                                </dd>
                            </div>
                            @if ($meetingNote->event)
                                <div class="flex items-center justify-between gap-3 px-5 py-3">
                                    <dt class="text-slate-500">Event</dt>
                                    <dd class="min-w-0 truncate text-right">
                                        <a href="{{ route('events.show', $meetingNote->event) }}" class="font-medium text-brand-700 hover:underline">{{ $meetingNote->event->title }}</a>
                                    </dd>
                                </div>
                            @endif
                        </dl>
                    </section>

                    <section class="card">
                        <div class="card-header">
                            <h2 class="card-title">Attendees</h2>
                            <span class="badge badge-slate">{{ $meetingNote->attendees->count() }}</span>
                        </div>
                        @if ($meetingNote->attendees->isEmpty())
                            <p class="px-5 py-4 text-sm text-slate-500">No attendees recorded.</p>
                        @else
                            <ul class="space-y-1 p-2">
                                @foreach ($meetingNote->attendees as $attendee)
                                    <li class="flex items-center gap-2.5 rounded-lg px-3 py-1.5 text-sm text-slate-700">
                                        <x-avatar :user="$attendee" size="sm" />
                                        <span class="truncate">{{ $attendee->name }}</span>@include('partials.user-tag', ['tagUser' => $attendee])
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
