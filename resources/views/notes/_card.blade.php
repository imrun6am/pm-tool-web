@php use App\Models\Note; @endphp
{{-- One note in the day-grouped feed. Requires $note (author + recipients loaded) and $users. --}}
<article class="group/note px-4 py-4 sm:px-5" x-data="{ editing: false, vis: '{{ $note->visibility }}' }">
    <div class="flex gap-3">
        <x-avatar :user="$note->author" :name="$note->author->name ?? '?'" />

        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span class="text-sm font-semibold text-slate-900">{{ $note->author->name ?? 'Unknown' }}</span>@include('partials.user-tag', ['tagUser' => $note->author])
                <span class="text-xs text-slate-500" title="{{ $note->date->format('M j, Y') }}">{{ $note->created_at->format('g:i a') }}</span>

                @if ($note->isShared())
                    <span class="badge badge-info"><x-icon name="globe" variant="outline" class="h-3 w-3" />Shared</span>
                @elseif ($note->isSpecific())
                    <span class="badge badge-violet"><x-icon name="user-group" variant="outline" class="h-3 w-3" />Specific</span>
                @else
                    <span class="badge badge-slate"><x-icon name="lock-closed" variant="outline" class="h-3 w-3" />Private</span>
                @endif

                @can('update', $note)
                    {{-- Quiet actions: revealed on hover/focus, always shown on touch screens --}}
                    <div x-show="!editing"
                         class="ml-auto flex items-center gap-0.5 opacity-0 transition-opacity focus-within:opacity-100 group-hover/note:opacity-100 [@media(hover:none)]:opacity-100">
                        <button type="button" @click="editing = true" class="btn-ghost btn-sm btn-icon h-8 w-8 text-slate-500" title="Edit note" aria-label="Edit note">
                            <x-icon name="pencil-square" variant="outline" class="h-4 w-4" />
                        </button>
                        <form method="POST" action="{{ route('notes.destroy', $note) }}" data-confirm="Delete this note?" data-confirm-verb="Delete">
                            @csrf @method('DELETE')
                            <button class="btn-ghost btn-sm btn-icon h-8 w-8 text-slate-500 hover:bg-danger-50 hover:text-danger-600" title="Delete note" aria-label="Delete note">
                                <x-icon name="trash" variant="outline" class="h-4 w-4" />
                            </button>
                        </form>
                    </div>
                @endcan
            </div>

            <div x-show="!editing" class="prose-notes mt-1.5 max-w-4xl text-sm leading-relaxed text-slate-700 [&>*:first-child]:mt-0">{!! $note->bodyHtml() !!}</div>

            @if ($note->isSpecific() && $note->recipients->isNotEmpty())
                <div x-show="!editing" class="mt-2.5 flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
                    <span>Shared with</span>
                    @foreach ($note->recipients as $r)
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 py-0.5 pl-0.5 pr-2 text-slate-700">
                            <x-avatar :user="$r" size="xs" />{{ $r->name }}
                        </span>
                    @endforeach
                </div>
            @endif

            @can('update', $note)
                <form x-show="editing" x-cloak method="POST" action="{{ route('notes.update', $note) }}" class="mt-2">
                    @csrf @method('PUT')
                    <input type="hidden" name="date" value="{{ $note->date->toDateString() }}">
                    <input id="note-edit-{{ $note->id }}" type="hidden" name="body" value="{{ $note->body }}">
                    <trix-editor input="note-edit-{{ $note->id }}" class="prose-notes"></trix-editor>
                    <x-ai-enhance input="note-edit-{{ $note->id }}" kind="note" />

                    <div class="mt-3 grid gap-3 sm:grid-cols-[12rem_1fr]">
                        <div>
                            <label for="note-edit-vis-{{ $note->id }}" class="sr-only">Visibility</label>
                            <select id="note-edit-vis-{{ $note->id }}" name="visibility" x-model="vis" class="field-select field-sm !mt-0">
                                @foreach (Note::VISIBILITIES as $val => $label)
                                    <option value="{{ $val }}" @selected($note->visibility === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div x-show="vis === 'specific'" x-cloak>
                            <x-multi-select
                                name="recipients"
                                :options="$users->map(fn ($u) => ['value' => $u->id, 'label' => $u->name, 'hint' => $u->roleLabel()])"
                                :selected="$note->recipients->pluck('id')->all()"
                                placeholder="Choose people…" />
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end gap-2">
                        <button type="button" @click="editing = false" class="btn-ghost btn-sm">Cancel</button>
                        <button class="btn-primary btn-sm">Save</button>
                    </div>
                </form>
            @endcan
        </div>
    </div>
</article>
