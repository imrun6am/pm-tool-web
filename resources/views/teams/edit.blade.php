<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit team"
                       :breadcrumbs="['Teams' => route('teams.index'), $team->name => route('teams.show', $team), 'Edit' => null]" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <form method="POST" action="{{ route('teams.update', $team) }}" class="mx-auto max-w-5xl space-y-4">
                @csrf @method('PUT')
                @include('teams.form')
                <x-form-actions :cancel="route('teams.show', $team)" submit="Save changes" />
            </form>
        </div>
    </div>
</x-app-layout>
