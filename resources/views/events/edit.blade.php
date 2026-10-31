<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit event"
                       :breadcrumbs="['Calendar' => route('calendar.index'), $event->title => route('events.show', $event), 'Edit' => null]" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <form method="POST" action="{{ route('events.update', $event) }}" class="mx-auto max-w-5xl space-y-4">
                @csrf @method('PUT')
                @include('events.form')
                <x-form-actions :cancel="route('events.show', $event)" submit="Save changes" />
            </form>
        </div>
    </div>
</x-app-layout>
