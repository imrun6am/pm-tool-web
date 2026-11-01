<x-app-layout>
    <x-slot name="header">
        <x-page-header title="New team" description="Group people who plan and ship releases together."
                       :breadcrumbs="['Teams' => route('teams.index'), 'New team' => null]" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <form method="POST" action="{{ route('teams.store') }}" class="space-y-4">
                @csrf
                @include('teams.form')
                <x-form-actions :cancel="route('teams.index')" submit="Create team" />
            </form>
        </div>
    </div>
</x-app-layout>
