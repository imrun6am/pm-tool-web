@php use App\Models\Note; @endphp
{{-- A single note card. Requires $note (author + recipients loaded) and $users. --}}
<div class="card card-pad flex flex-col" x-data="{ editing: false, vis: '{{ $note->visibility }}' }">
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-2">
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">
                {{ strtoupper(substr($note->author->name ?? '?', 0, 1)) }}
            </span>
            <div>
                <p class="text-sm font-medium text-slate-800">{{ $note->author->name ?? 'Unknown' }}@include('partials.user-tag', ['tagUser' => $note->author])</p>
                <p class="text-xs text-slate-400">{{ $note->date->format('M j, Y') }} · {{ $note->created_at->format('g:i a') }}</p>
            </div>
        </div>
        @if ($note->isShared())
            <span class="badge bg-cyan-50 text-cyan-700">
                <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path d="M13 4.5a2.5 2.5 0 11.7 1.74l-4.9 2.45a2.5 2.5 0 010 2.62l4.9 2.45a2.5 2.5 0 11-.67 1.34l-4.9-2.45a2.5 2.5 0 110-5.3l4.9-2.45A2.5 2.5 0 0113 4.5z"/></svg>
                Shared
            </span>
        @elseif ($note->isSpecific())
            <span class="badge bg-violet-50 text-violet-700">
                <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>
                Specific
            </span>
        @else
            <span class="badge bg-slate-100 text-slate-600">
                <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4 4 0 00-4 4v2H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2V9a2 2 0 00-2-2h-1V5a4 4 0 00-4-4zm2 6V5a2 2 0 10-4 0v2h4z" clip-rule="evenodd"/></svg>
                Private
            </span>
        @endif
    </div>

    <div x-show="!editing" class="prose-notes mt-3 flex-1 text-sm text-slate-700">{!! $note->bodyHtml() !!}</div>

    @if ($note->isSpecific() && $note->recipients->isNotEmpty())
        <div x-show="!editing" class="mt-3 flex flex-wrap items-center gap-1 border-t border-slate-100 pt-3 text-xs text-slate-400">
            <span class="font-medium">Shared with:</span>
            @foreach ($note->recipients as $r)
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-slate-600">{{ $r->name }}</span>
            @endforeach
        </div>
    @endif

    @can('update', $note)
        <form x-show="editing" x-cloak method="POST" action="{{ route('notes.update', $note) }}" class="mt-3">
            @csrf @method('PUT')
            <input type="hidden" name="date" value="{{ $note->date->toDateString() }}">
            <input id="note-edit-{{ $note->id }}" type="hidden" name="body" value="{{ $note->body }}">
            <trix-editor input="note-edit-{{ $note->id }}" class="prose-notes"></trix-editor>

            <div class="mt-2 space-y-2">
                <select name="visibility" x-model="vis" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach (Note::VISIBILITIES as $val => $label)
                        <option value="{{ $val }}" @selected($note->visibility === $val)>{{ $label }}</option>
                    @endforeach
                </select>
                <div x-show="vis === 'specific'" x-cloak>
                    <x-multi-select
                        name="recipients"
                        :options="$users->map(fn ($u) => ['value' => $u->id, 'label' => $u->name, 'hint' => $u->roleLabel()])"
                        :selected="$note->recipients->pluck('id')->all()"
                        placeholder="Choose people…" />
                </div>
                <div class="flex justify-end gap-2">
                    <button class="btn-primary btn-sm">Save</button>
                    <button type="button" @click="editing = false" class="btn-ghost btn-sm">Cancel</button>
                </div>
            </div>
        </form>
        <div x-show="!editing" class="mt-3 flex gap-3 border-t border-slate-100 pt-3">
            <button type="button" @click="editing = true" class="text-xs text-slate-400 transition hover:text-brand-600">Edit</button>
            <form method="POST" action="{{ route('notes.destroy', $note) }}" data-confirm="Delete this note?" data-confirm-verb="Delete">
                @csrf @method('DELETE')
                <button class="text-xs text-slate-400 transition hover:text-rose-600">Delete</button>
            </form>
        </div>
    @endcan
</div>
