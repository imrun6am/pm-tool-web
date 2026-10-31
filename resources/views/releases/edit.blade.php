<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="eyebrow">Editing release</p>
                <h2 class="page-title mt-1">{{ $release->name }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="inline-block h-2.5 w-2.5 rounded-full align-middle" style="background-color: {{ $release->project->color }}"></span>{{ $release->project->name }}
                    </span>
                    <span class="mx-1 text-slate-300">·</span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="inline-block h-2.5 w-2.5 rounded-full align-middle" style="background-color: {{ $release->team->color }}"></span>{{ $release->team->name }}
                    </span>
                    <span class="mx-1 text-slate-300">·</span>
                    {{ $release->year }} {{ $release->quarterLabel() }}
                </p>
            </div>
            <a href="{{ route('releases.show', $release) }}" class="btn-secondary btn-sm">View release</a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <div class="space-y-4">
                <form method="POST" action="{{ route('releases.update', $release) }}">
                    @csrf @method('PUT')
                    @include('releases.form')

                    <div class="mt-6 flex items-center justify-end gap-3 border-t border-slate-200 pt-6">
                        <a href="{{ route('releases.show', $release) }}" class="btn-ghost btn-sm">Cancel</a>
                        <button class="btn-primary">Save changes</button>
                    </div>
                </form>

                {{-- Danger zone (separate form — never nest inside the update form) --}}
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-rose-100 bg-rose-50/40 px-5 py-4">
                    <div>
                        <p class="text-sm font-medium text-slate-700">Delete this release</p>
                        <p class="text-xs text-slate-500">Removes its phases, documents and off-days. This can't be undone.</p>
                    </div>
                    <form method="POST" action="{{ route('releases.destroy', $release) }}"
                          data-confirm="Delete this release, its phases and documents?" data-confirm-verb="Delete">
                        @csrf @method('DELETE')
                        <button class="btn-danger btn-sm">Delete release</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
