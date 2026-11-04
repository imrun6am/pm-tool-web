<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Labels" description="Shared tags for tasks. Anyone can apply them; leads manage this list." :breadcrumbs="['Board' => route('board.index'), 'Labels' => null]" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container max-w-3xl">
            <x-live-region id="labels" class="space-y-6">
                <form method="POST" action="{{ route('labels.store') }}" class="card flex flex-wrap items-end gap-3 p-4">
                    @csrf
                    <div class="min-w-[12rem] flex-1">
                        <label for="label-name" class="field-label">New label</label>
                        <input id="label-name" name="name" required maxlength="40" value="{{ old('name') }}" class="field-input" autocomplete="off">
                    </div>
                    <div>
                        <label for="label-color" class="field-label">Color</label>
                        <input id="label-color" type="color" name="color" value="{{ old('color', '#0b63f6') }}" class="mt-1.5 block h-10 w-14 cursor-pointer rounded-lg border border-slate-300 bg-white p-1">
                    </div>
                    <button class="btn-primary">Add label</button>
                    @foreach (['name', 'color'] as $field)
                        @error($field)<p class="field-error w-full" role="alert">{{ $message }}</p>@enderror
                    @endforeach
                </form>

                <div class="list-card">
                    @forelse ($labels as $label)
                        <div x-data="{ editing: false }" class="px-4 py-3">
                            <div x-show="! editing" class="flex items-center gap-3">
                                <span class="h-3 w-3 flex-none rounded-full" style="background-color: {{ $label->color }}" aria-hidden="true"></span>
                                <span class="min-w-0 flex-1 truncate text-sm font-medium text-slate-800">{{ $label->name }}</span>
                                <span class="tabular flex-none text-xs text-slate-500">{{ $label->tasks_count }} {{ Str::plural('task', $label->tasks_count) }}</span>
                                <button type="button" @click="editing = true" class="btn-ghost btn-sm btn-icon h-7 w-7 text-slate-400 hover:text-brand-700" aria-label="Edit {{ $label->name }}">
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </button>
                                <form method="POST" action="{{ route('labels.destroy', $label) }}" data-confirm="Delete “{{ $label->name }}”? It comes off {{ $label->tasks_count }} {{ Str::plural('task', $label->tasks_count) }}; the tasks stay." data-confirm-verb="Delete">
                                    @csrf @method('DELETE')
                                    <button class="btn-ghost btn-sm btn-icon h-7 w-7 text-slate-400 hover:text-danger-600" aria-label="Delete {{ $label->name }}">
                                        <x-icon name="x-mark" variant="outline" class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                            <form x-show="editing" x-cloak method="POST" action="{{ route('labels.update', $label) }}" class="flex flex-wrap items-center gap-2">
                                @csrf @method('PUT')
                                <label for="label-color-{{ $label->id }}" class="sr-only">Color</label>
                                <input id="label-color-{{ $label->id }}" type="color" name="color" value="{{ $label->color }}" class="h-9 w-12 flex-none cursor-pointer rounded-lg border border-slate-300 bg-white p-1">
                                <label for="label-name-{{ $label->id }}" class="sr-only">Name</label>
                                <input id="label-name-{{ $label->id }}" name="name" required maxlength="40" value="{{ $label->name }}" class="field-control field-sm min-w-[10rem] flex-1" autocomplete="off">
                                <button class="btn-primary btn-sm">Save</button>
                                <button type="button" @click="editing = false" class="btn-ghost btn-sm">Cancel</button>
                            </form>
                        </div>
                    @empty
                        <p class="px-4 py-6 text-center text-sm text-slate-500">No labels yet.</p>
                    @endforelse
                </div>
            </x-live-region>
        </div>
    </div>
</x-app-layout>
