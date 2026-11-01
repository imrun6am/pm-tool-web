<x-app-layout>
    <x-slot name="header">
        <x-page-header title="New task" description="Capture it now, sort it out later. Tip: press C on any page to do this without leaving it." />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <div class="card mx-auto max-w-2xl p-5 sm:p-6">
                @include('tasks._quick-form')
            </div>
        </div>
    </div>
</x-app-layout>
