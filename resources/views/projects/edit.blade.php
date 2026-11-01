<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit project" :description="$project->isArchived() ? 'This project is archived. Restore it to plan new releases against it.' : null"
                       :breadcrumbs="['Projects' => route('projects.index'), $project->name => route('projects.show', $project), 'Edit' => null]" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <form method="POST" action="{{ route('projects.update', $project) }}" class="space-y-4">
                @csrf @method('PUT')
                @include('projects.form')
                <x-form-actions :cancel="route('projects.show', $project)" submit="Save changes">
                    <x-slot name="delete">
                        @if ($deletable)
                            <button type="submit" form="delete-project" class="btn-ghost text-danger-700 hover:bg-danger-50">
                                <x-icon name="trash" variant="outline" class="h-4 w-4" />Delete
                            </button>
                        @elseif ($project->isArchived())
                            <button type="submit" form="restore-project" class="btn-ghost">
                                <x-icon name="restore" class="h-4 w-4" />Restore
                            </button>
                        @else
                            <button type="submit" form="archive-project" class="btn-ghost" title="A project with releases is archived, not deleted">
                                <x-icon name="archive" class="h-4 w-4" />Archive
                            </button>
                        @endif
                    </x-slot>
                </x-form-actions>
            </form>

            {{-- Standalone forms for the action bar's leading button (forms cannot nest). --}}
            @if ($deletable)
                <form id="delete-project" method="POST" action="{{ route('projects.destroy', $project) }}"
                      data-confirm="Delete this project permanently?" data-confirm-verb="Delete" class="hidden">
                    @csrf @method('DELETE')
                </form>
            @elseif ($project->isArchived())
                <form id="restore-project" method="POST" action="{{ route('projects.restore', $project) }}" class="hidden">@csrf</form>
            @else
                <form id="archive-project" method="POST" action="{{ route('projects.archive', $project) }}" class="hidden">@csrf</form>
            @endif
        </div>
    </div>
</x-app-layout>
