<x-app-layout>
    <x-slot name="header">
        <x-page-header title="New event" description="Put a meeting, deadline or milestone on the team calendar."
                       :breadcrumbs="['Calendar' => route('calendar.index'), 'New event' => null]" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <form method="POST" action="{{ route('events.store') }}" class="mx-auto max-w-5xl space-y-4">
                @csrf
                @include('events.form')
                <x-form-actions :cancel="route('calendar.index')" submit="Create event" />
            </form>
        </div>
    </div>
</x-app-layout>
