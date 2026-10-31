<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit user" :description="$user->email"
                       :breadcrumbs="['Users' => route('users.index'), $user->name => route('users.index', ['q' => $user->email]), 'Edit' => null]" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <form method="POST" action="{{ route('users.update', $user) }}" class="mx-auto max-w-5xl space-y-4">
                @csrf @method('PUT')
                @include('users.form')
                <x-form-actions :cancel="route('users.index')" submit="Save changes" />
            </form>
        </div>
    </div>
</x-app-layout>
