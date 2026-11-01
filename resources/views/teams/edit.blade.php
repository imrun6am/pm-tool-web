<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit team" :description="$team->name"
                       :breadcrumbs="['Teams' => route('teams.index'), $team->name => route('teams.show', $team), 'Edit' => null]" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            @if ($team->isArchived())
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800">
                    <span>This team is archived — it is hidden from the directory and pickers.</span>
                    <button form="restore-team" class="btn-secondary btn-sm">
                        <x-icon name="restore" class="h-4 w-4" />Restore team
                    </button>
                </div>
            @endif

            <form method="POST" action="{{ route('teams.update', $team) }}" class="space-y-4">
                @csrf @method('PUT')
                @include('teams.form')
                <x-form-actions :cancel="route('teams.show', $team)" submit="Save changes">
                    <x-slot name="delete">
                        @if ($deletable)
                            <button form="delete-team" class="btn-danger btn-sm">
                                <x-icon name="trash" variant="outline" class="h-4 w-4" />Delete team
                            </button>
                        @elseif (! $team->isArchived())
                            <button form="archive-team" class="btn-ghost btn-sm" title="Teams that own releases are archived rather than deleted">
                                <x-icon name="archive" class="h-4 w-4" />Archive team
                            </button>
                        @endif
                    </x-slot>
                </x-form-actions>
            </form>

            {{-- Forms cannot nest: the lifecycle buttons above point at these. --}}
            <form id="restore-team" method="POST" action="{{ route('teams.restore', $team) }}" class="hidden">@csrf</form>
            <form id="archive-team" method="POST" action="{{ route('teams.archive', $team) }}" class="hidden">@csrf</form>
            @if ($deletable)
                <form id="delete-team" method="POST" action="{{ route('teams.destroy', $team) }}" class="hidden"
                      data-confirm="Delete this team permanently?" data-confirm-verb="Delete">
                    @csrf @method('DELETE')
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
