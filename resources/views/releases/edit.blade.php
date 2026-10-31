<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit release"
                       :description="$release->project->name.' · '.$release->team->name.' · '.$release->year.' '.$release->quarterLabel()"
                       :breadcrumbs="['Releases' => route('releases.index'), $release->name => route('releases.show', $release), 'Edit' => null]">
            <x-slot name="actions">
                <a href="{{ route('releases.show', $release) }}" class="btn-secondary btn-sm">View release</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <form method="POST" action="{{ route('releases.update', $release) }}" class="space-y-4">
                @csrf @method('PUT')
                @include('releases.form')
                <x-form-actions :cancel="route('releases.show', $release)" submit="Save changes">
                    <x-slot name="delete">
                        <button type="submit" form="delete-release" class="btn-danger px-3 sm:px-4">
                            <x-icon name="trash" variant="outline" class="h-4 w-4" /><span class="sr-only sm:not-sr-only">Delete release</span>
                        </button>
                    </x-slot>
                </x-form-actions>
            </form>

            {{-- Separate form — never nest inside the update form. --}}
            <form id="delete-release" method="POST" action="{{ route('releases.destroy', $release) }}" class="hidden"
                  data-confirm="Delete this release, its phases, documents and off-days? This can't be undone." data-confirm-verb="Delete">
                @csrf @method('DELETE')
            </form>
        </div>
    </div>
</x-app-layout>
