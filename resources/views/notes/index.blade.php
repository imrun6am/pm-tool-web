@php use App\Models\Note; @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="page-title">Notes</h2>

            {{-- Filters: author, plus a single day or a from/to range --}}
            <form method="GET" action="{{ route('notes.index') }}" class="flex flex-wrap items-center gap-1">
                <label for="author" class="field-label !mt-0 !font-normal text-slate-500">By</label>
                <select id="author" name="author" onchange="this.form.submit()"
                        class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">Anyone</option>
                    @foreach ($authors as $a)
                        <option value="{{ $a->id }}" @selected($author === $a->id)>{{ $a->id === auth()->id() ? 'Me' : $a->name }}</option>
                    @endforeach
                </select>

                <label class="field-label !mt-0 ml-2 !font-normal text-slate-500">Day</label>
                <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()"
                       class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                <span class="mx-1 text-xs text-slate-300">or range</span>
                <input type="date" name="from" value="{{ $from }}" aria-label="From date"
                       class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                <span class="text-slate-400">–</span>
                <input type="date" name="to" value="{{ $to }}" aria-label="To date"
                       class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                <button class="btn-secondary btn-sm">Filter</button>
                @if ($date || $from || $to || $author)
                    <a href="{{ route('notes.index') }}" class="btn-ghost btn-sm">Clear</a>
                @endif
            </form>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-6">

            {{-- Add a note --}}
            <form method="POST" action="{{ route('notes.store') }}" class="card card-pad"
                  x-data="{ vis: '{{ old('visibility', 'private') }}' }">
                @csrf
                <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                    <label class="field-label !mt-0">Add a note</label>
                    <div class="flex items-center gap-1">
                        <label class="field-label !mt-0 !font-normal text-slate-500">Date</label>
                        <input type="date" name="date" value="{{ old('date', $today) }}"
                               class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>

                <input id="note-add-body" type="hidden" name="body" value="{{ old('body') }}">
                <trix-editor input="note-add-body" placeholder="Jot something down…" class="prose-notes mt-2"></trix-editor>
                @error('body') <p class="field-error">{{ $message }}</p> @enderror

                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="field-label !mt-0 !font-normal text-slate-500">Visibility</label>
                        <select name="visibility" x-model="vis" class="field-input !mt-1">
                            @foreach (Note::VISIBILITIES as $val => $label)
                                <option value="{{ $val }}" @selected(old('visibility', 'private') === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="vis === 'specific'" x-cloak>
                        <label class="field-label !mt-0 !font-normal text-slate-500">Share with</label>
                        <div class="mt-1">
                            <x-multi-select
                                name="recipients"
                                :options="$users->map(fn ($u) => ['value' => $u->id, 'label' => $u->name, 'hint' => $u->roleLabel()])"
                                :selected="[]"
                                placeholder="Choose people…" />
                        </div>
                    </div>
                </div>

                <div class="mt-3 flex justify-end">
                    <button class="btn-primary btn-sm">Add note</button>
                </div>
            </form>

            {{-- Notes (all visible, newest first, paginated) --}}
            @if ($notes->isEmpty())
                <div class="card p-12 text-center text-sm text-slate-500">
                    {{ ($date || $from || $to) ? 'No notes match this filter.' : 'No notes yet — add your first above.' }}
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($notes as $note)
                        @include('notes._card', ['note' => $note, 'users' => $users])
                    @endforeach
                </div>

                <div>{{ $notes->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
