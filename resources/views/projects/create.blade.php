<x-app-layout>
    <x-slot name="header">
        <x-page-header title="New project" description="Group releases under the product they ship."
                       :breadcrumbs="['Projects' => route('projects.index'), 'New project' => null]" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <form method="POST" action="{{ route('projects.store') }}" class="mx-auto max-w-5xl space-y-4">
                @csrf
                @include('projects.form')
                <x-form-actions :cancel="route('projects.index')" submit="Create project" />
            </form>
        </div>
    </div>
</x-app-layout>
