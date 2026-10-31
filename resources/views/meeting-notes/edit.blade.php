<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit meeting note"
                       :breadcrumbs="['Meeting notes' => route('meeting-notes.index'), $meetingNote->title => route('meeting-notes.show', $meetingNote), 'Edit' => null]" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <form method="POST" action="{{ route('meeting-notes.update', $meetingNote) }}" class="mx-auto max-w-5xl space-y-4">
                @csrf @method('PUT')
                @include('meeting-notes.form')
                <x-form-actions :cancel="route('meeting-notes.show', $meetingNote)" submit="Save changes" />
            </form>
        </div>
    </div>
</x-app-layout>
