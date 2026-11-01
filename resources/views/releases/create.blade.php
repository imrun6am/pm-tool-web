<x-app-layout>
    <x-slot name="header">
        <x-page-header title="New release plan" description="Name it, set the window, split it into phases, and pick who's on it. You can change everything later."
                       :breadcrumbs="['Releases' => route('releases.index'), 'New release' => null]" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <form method="POST" action="{{ route('releases.store') }}" class="space-y-4">
                @csrf
                @include('releases.form')
                <x-form-actions :cancel="route('releases.index')" submit="Create release" />
            </form>
        </div>
    </div>
</x-app-layout>
