<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit project"
                       :breadcrumbs="['Projects' => route('projects.index'), $project->name => route('projects.show', $project), 'Edit' => null]" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <form method="POST" action="{{ route('projects.update', $project) }}" class="mx-auto max-w-5xl space-y-4">
                @csrf @method('PUT')
                @include('projects.form')
                <x-form-actions :cancel="route('projects.show', $project)" submit="Save changes" />
            </form>
        </div>
    </div>
</x-app-layout>
