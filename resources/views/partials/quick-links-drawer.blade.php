@php
    $viewer = auth()->user();
    $limited = $viewer->hasLimitedAccess();
@endphp
{{-- Quick-links slide-over, opened by a floating right-edge handle. --}}
<div x-data="{ open: {{ session('quick-links-open') ? 'true' : 'false' }}, adding: {{ $errors->hasAny(['label', 'url', 'visibility', 'release_id']) ? 'true' : 'false' }} }"
     @toggle-quick-links.window="open = !open"
     @keydown.escape.window="open = false"
     class="relative z-50">

    {{-- Floating handle on the right screen edge --}}
    <button type="button" @click="open = true" x-show="!open"
            class="fixed right-0 top-1/3 z-40 rounded-l-xl bg-brand-600 p-2.5 text-white shadow-lg transition hover:bg-brand-700"
            title="Quick links" aria-label="Quick links">
        <x-icon name="link" class="h-5 w-5" />
    </button>

    {{-- Backdrop --}}
    <div x-show="open" x-transition.opacity style="display: none;"
         class="fixed inset-0 bg-slate-900/40 backdrop-blur-[2px]" @click="open = false" aria-hidden="true"></div>

    {{-- Panel --}}
    <aside x-show="open" style="display: none;"
           x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
           x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
           class="fixed inset-y-0 right-0 flex w-full max-w-sm flex-col border-l border-slate-200 bg-white shadow-2xl"
           role="dialog" aria-label="Quick links">

        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                <x-icon name="link" class="h-4 w-4 text-brand-600" />
                Quick links
            </h2>
            <button type="button" @click="open = false"
                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                    aria-label="Close quick links">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            </button>
        </div>

        <div class="flex-1 space-y-6 overflow-y-auto px-5 py-5">
            {{-- Add button on top; the form expands beneath it --}}
            <button type="button" x-show="!adding" @click="adding = true"
                    class="btn-primary btn-sm w-full justify-center">
                <x-icon name="plus" class="h-4 w-4" />
                Add link
            </button>

            <form x-show="adding" style="display: none;" method="POST" action="{{ route('quick-links.store') }}"
                  class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                @csrf
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Add a link</p>

                <div>
                    <label for="ql-add-label" class="sr-only">Label</label>
                    <input id="ql-add-label" name="label" type="text" required maxlength="100"
                           placeholder="Label — e.g. Staging server" value="{{ old('label') }}"
                           class="field-input !mt-0 bg-white">
                    @error('label') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="ql-add-url" class="sr-only">URL</label>
                    <input id="ql-add-url" name="url" type="url" required placeholder="https://…"
                           value="{{ old('url') }}" class="field-input !mt-0 bg-white">
                    @error('url') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-3 {{ $limited ? '' : 'grid-cols-2' }}">
                    @unless ($limited)
                        <div>
                            <label for="ql-add-visibility" class="mb-1 block text-[11px] font-medium text-slate-500">Visibility</label>
                            <select id="ql-add-visibility" name="visibility" class="field-input !mt-0 bg-white">
                                <option value="private" @selected(old('visibility') !== 'shared')>Private</option>
                                <option value="shared" @selected(old('visibility') === 'shared')>Shared</option>
                            </select>
                        </div>
                    @endunless
                    <div>
                        <label for="ql-add-release" class="mb-1 block text-[11px] font-medium text-slate-500">Release <span class="font-normal text-slate-400">(optional)</span></label>
                        <select id="ql-add-release" name="release_id" class="field-input !mt-0 bg-white">
                            <option value="">None</option>
                            @foreach ($drawerReleases as $r)
                                <option value="{{ $r->id }}" @selected((int) old('release_id') === $r->id)>{{ $r->name }} ({{ $r->year }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @error('visibility') <p class="field-error">{{ $message }}</p> @enderror

                <div class="flex gap-2">
                    <button type="button" @click="adding = false" class="btn-ghost btn-sm flex-none">Cancel</button>
                    <button class="btn-primary btn-sm flex-1 justify-center">Add link</button>
                </div>
            </form>

            {{-- My links --}}
            <section>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">My links</h3>
                @if ($myQuickLinks->isEmpty())
                    <p class="mt-3 rounded-lg border border-dashed border-slate-200 p-4 text-center text-sm text-slate-400">
                        No links yet — add your first above.
                    </p>
                @else
                    <ul class="mt-3 space-y-2">
                        @foreach ($myQuickLinks as $link)
                            @php $pinned = $link->isPinnedBy($viewer); @endphp
                            <li x-data="{ editing: false }"
                                @class([
                                    'group rounded-xl border p-3 transition hover:border-brand-200 hover:shadow-sm',
                                    'border-amber-200 bg-amber-50/40' => $pinned,
                                    'border-slate-200' => ! $pinned,
                                ])>
                                <div x-show="!editing" class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <a href="{{ $link->url }}" target="_blank" rel="noopener"
                                           class="flex items-center gap-1.5 text-sm font-medium text-slate-800 hover:text-brand-700">
                                            <span class="truncate">{{ $link->label }}</span>
                                            <svg class="h-3 w-3 flex-none text-slate-300 group-hover:text-brand-400" viewBox="0 0 20 20" fill="currentColor"><path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/><path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/></svg>
                                        </a>
                                        <p class="mt-0.5 truncate text-xs text-slate-400">{{ parse_url($link->url, PHP_URL_HOST) ?? $link->url }}</p>
                                        <p class="mt-1.5 flex flex-wrap items-center gap-1">
                                            @unless ($limited)
                                                <span @class([
                                                    'rounded-full px-2 py-0.5 text-[11px] font-medium',
                                                    'bg-emerald-50 text-emerald-700' => $link->isShared(),
                                                    'bg-slate-100 text-slate-500' => ! $link->isShared(),
                                                ])>{{ $link->isShared() ? 'Shared' : 'Private' }}</span>
                                            @endunless
                                            @if ($link->release)
                                                <span class="rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-medium text-brand-700">{{ $link->release->name }}</span>
                                            @endif
                                        </p>
                                    </div>
                                    <div class="flex flex-none items-center gap-0.5">
                                        {{-- Always visible when pinned, so the ordering reads as deliberate --}}
                                        <form method="POST" action="{{ route('quick-links.pin', $link) }}"
                                              class="{{ $pinned ? '' : 'opacity-0 transition group-hover:opacity-100' }}">
                                            @csrf
                                            <button @class([
                                                        'rounded-lg p-1.5',
                                                        'text-amber-500 hover:bg-amber-100' => $pinned,
                                                        'text-slate-400 hover:bg-slate-100 hover:text-slate-600' => ! $pinned,
                                                    ])
                                                    title="{{ $pinned ? 'Unpin' : 'Pin to top' }}"
                                                    aria-label="{{ $pinned ? 'Unpin' : 'Pin' }} {{ $link->label }}">
                                                <x-icon name="pin" class="h-3.5 w-3.5" />
                                            </button>
                                        </form>
                                        <div class="flex items-center gap-0.5 opacity-0 transition group-hover:opacity-100">
                                        <button type="button" @click="editing = true"
                                                class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                                                aria-label="Edit {{ $link->label }}">
                                            <x-icon name="pencil" class="h-3.5 w-3.5" />
                                        </button>
                                        <form method="POST" action="{{ route('quick-links.destroy', $link) }}" data-confirm="Delete this link?" data-confirm-verb="Delete">
                                            @csrf @method('DELETE')
                                            <button class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600" aria-label="Delete {{ $link->label }}">
                                                <x-icon name="trash" class="h-3.5 w-3.5" />
                                            </button>
                                        </form>
                                        </div>
                                    </div>
                                </div>

                                {{-- Inline edit --}}
                                <form x-show="editing" style="display: none;" method="POST" action="{{ route('quick-links.update', $link) }}" class="space-y-3">
                                    @csrf @method('PUT')
                                    <input name="label" type="text" required maxlength="100" value="{{ $link->label }}" class="field-input !mt-0" aria-label="Label">
                                    <input name="url" type="url" required value="{{ $link->url }}" class="field-input !mt-0" aria-label="URL">
                                    <div class="grid gap-3 {{ $limited ? '' : 'grid-cols-2' }}">
                                        @unless ($limited)
                                            <div>
                                                <label class="mb-1 block text-[11px] font-medium text-slate-500">Visibility</label>
                                                <select name="visibility" class="field-input !mt-0">
                                                    <option value="private" @selected(! $link->isShared())>Private</option>
                                                    <option value="shared" @selected($link->isShared())>Shared</option>
                                                </select>
                                            </div>
                                        @endunless
                                        <div>
                                            <label class="mb-1 block text-[11px] font-medium text-slate-500">Release</label>
                                            <select name="release_id" class="field-input !mt-0">
                                                <option value="">None</option>
                                                @foreach ($drawerReleases as $r)
                                                    <option value="{{ $r->id }}" @selected($link->release_id === $r->id)>{{ $r->name }} ({{ $r->year }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <button type="button" @click="editing = false" class="btn-ghost btn-sm">Cancel</button>
                                        <button class="btn-primary btn-sm">Save</button>
                                    </div>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Shared by others (never rendered for limited roles) --}}
            @unless ($limited)
                <section>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Shared by others</h3>
                    @if ($sharedQuickLinks->isEmpty())
                        <p class="mt-3 rounded-lg border border-dashed border-slate-200 p-4 text-center text-sm text-slate-400">
                            Nothing shared yet.
                        </p>
                    @else
                        <ul class="mt-3 space-y-2">
                            @foreach ($sharedQuickLinks as $link)
                                @php $pinned = $link->isPinnedBy($viewer); @endphp
                                <li @class([
                                        'group rounded-xl border p-3 transition hover:border-brand-200 hover:shadow-sm',
                                        'border-amber-200 bg-amber-50/40' => $pinned,
                                        'border-slate-200' => ! $pinned,
                                    ])>
                                    <div class="flex items-start justify-between gap-2">
                                        <a href="{{ $link->url }}" target="_blank" rel="noopener"
                                           class="flex min-w-0 items-center gap-1.5 text-sm font-medium text-slate-800 hover:text-brand-700">
                                            <span class="truncate">{{ $link->label }}</span>
                                            <svg class="h-3 w-3 flex-none text-slate-300 group-hover:text-brand-400" viewBox="0 0 20 20" fill="currentColor"><path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/><path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/></svg>
                                        </a>
                                        {{-- Pinning a teammate's link is per-viewer: they see no change --}}
                                        <form method="POST" action="{{ route('quick-links.pin', $link) }}"
                                              class="flex-none {{ $pinned ? '' : 'opacity-0 transition group-hover:opacity-100' }}">
                                            @csrf
                                            <button @class([
                                                        'rounded-lg p-1.5',
                                                        'text-amber-500 hover:bg-amber-100' => $pinned,
                                                        'text-slate-400 hover:bg-slate-100 hover:text-slate-600' => ! $pinned,
                                                    ])
                                                    title="{{ $pinned ? 'Unpin' : 'Pin to top' }}"
                                                    aria-label="{{ $pinned ? 'Unpin' : 'Pin' }} {{ $link->label }}">
                                                <x-icon name="pin" class="h-3.5 w-3.5" />
                                            </button>
                                        </form>
                                    </div>
                                    <p class="mt-0.5 truncate text-xs text-slate-400">{{ parse_url($link->url, PHP_URL_HOST) ?? $link->url }}</p>
                                    <p class="mt-1.5 flex flex-wrap items-center gap-1 text-[11px] text-slate-400">
                                        <span class="flex h-4 w-4 items-center justify-center rounded-full bg-slate-100 text-[9px] font-semibold text-slate-500">{{ strtoupper(mb_substr($link->author->name ?? '?', 0, 1)) }}</span>
                                        <span>{{ $link->author->name ?? 'Unknown' }}</span>
                                        @if ($link->release)
                                            <span class="rounded-full bg-brand-50 px-2 py-0.5 font-medium text-brand-700">{{ $link->release->name }}</span>
                                        @endif
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endunless
        </div>
    </aside>
</div>
