<x-app-layout>
    <x-slot name="header">
        <x-page-header title="New user" description="Create an account and choose what this person can manage."
                       :breadcrumbs="['Users' => route('users.index'), 'New user' => null]" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <form method="POST" action="{{ route('users.store') }}" class="mx-auto max-w-5xl space-y-4">
                @csrf
                @include('users.form')
                <x-form-actions :cancel="route('users.index')" submit="Create user" />
            </form>
        </div>
    </div>
</x-app-layout>
