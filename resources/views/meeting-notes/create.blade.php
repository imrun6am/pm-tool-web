<x-app-layout>
    <x-slot name="header">
        <x-page-header title="New meeting note" description="Capture what was discussed, decided and assigned."
                       :breadcrumbs="['Meeting notes' => route('meeting-notes.index'), 'New meeting note' => null]" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <form method="POST" action="{{ route('meeting-notes.store') }}" class="mx-auto max-w-5xl space-y-4">
                @csrf
                @include('meeting-notes.form')
                <x-form-actions :cancel="route('meeting-notes.index')" submit="Create note" />
            </form>
        </div>
    </div>
</x-app-layout>
