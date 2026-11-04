@php
    use App\Models\SavedView;

    // Requires $savedViewPage ('tasks' or 'board') and $savedViews.
    $current = collect(request()->query())->only(SavedView::PAGES[$savedViewPage])->filter(fn ($v) => $v !== null && $v !== '');
    $isCurrent = fn (SavedView $view) => $current->sortKeys()->map(fn ($v) => (string) $v)->all()
        === collect($view->filters)->sortKeys()->map(fn ($v) => (string) $v)->all();
@endphp
<div x-data="{ open: false, saving: false }" @click.outside="open = false; saving = false" @keydown.escape="open = false; saving = false" class="relative">
    {{-- Icon only on laptop widths, where the board's filters need the room. --}}
    <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-haspopup="true" aria-label="Views" title="Saved views"
            class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-white px-2.5 text-sm font-medium text-slate-700 ring-1 ring-inset ring-slate-300 transition-colors hover:bg-slate-50 2xl:px-3">
        <x-icon name="bookmark" variant="outline" class="h-4 w-4 text-slate-500" />
        <span class="hidden 2xl:inline">Views</span>
    </button>

    <div x-show="open" x-cloak class="menu-panel absolute right-0 z-30 mt-2 w-72">
        @forelse ($savedViews as $view)
            <div @class(['group flex items-center gap-1 rounded-md', 'bg-brand-50' => $isCurrent($view)])>
                <a href="{{ $view->url() }}" class="menu-item min-w-0 flex-1">
                    <span class="truncate">{{ $view->name }}</span>
                    @if ($view->is_shared)
                        <span class="badge badge-slate ml-auto flex-none">Shared</span>
                    @endif
                </a>
                @can('delete', $view)
                    <form method="POST" action="{{ route('saved-views.destroy', $view) }}" data-confirm="Remove the view “{{ $view->name }}”?{{ $view->is_shared ? ' Everyone loses it.' : '' }}" data-confirm-verb="Remove" class="flex-none">
                        @csrf @method('DELETE')
                        <button class="btn-ghost btn-sm btn-icon h-7 w-7 text-slate-400 hover:text-danger-600" aria-label="Remove view {{ $view->name }}">
                            <x-icon name="x-mark" variant="outline" class="h-4 w-4" />
                        </button>
                    </form>
                @endcan
            </div>
        @empty
            <p class="px-3 py-2 text-sm text-slate-500">No saved views yet.</p>
        @endforelse

        <div class="menu-divider"></div>

        <button type="button" x-show="! saving" @click="saving = true; $nextTick(() => $refs.viewName.focus())" class="menu-item w-full">
            <x-icon name="plus" variant="outline" class="h-4 w-4" />
            Save current filters
        </button>
        <form x-show="saving" x-cloak method="POST" action="{{ route('saved-views.store') }}" class="space-y-2 p-2">
            @csrf
            <input type="hidden" name="page" value="{{ $savedViewPage }}">
            @foreach ($current as $key => $value)
                <input type="hidden" name="filters[{{ $key }}]" value="{{ $value }}">
            @endforeach
            <label for="view-name-{{ $savedViewPage }}" class="sr-only">View name</label>
            <input id="view-name-{{ $savedViewPage }}" x-ref="viewName" name="name" required maxlength="60" placeholder="Name this view" class="field-control field-sm" autocomplete="off">
            @can('share', SavedView::class)
                <label class="flex cursor-pointer items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox" name="shared" value="1" class="checkbox-sm">
                    Share with everyone
                </label>
            @endcan
            <button class="btn-primary btn-sm">Save view</button>
        </form>
    </div>
</div>
