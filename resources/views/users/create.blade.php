<x-app-layout>
    <x-slot name="header">
        <h2 class="page-title">New user</h2>
    </x-slot>

    <div class="py-8">
        <div class="app-container">
            <form method="POST" action="{{ route('users.store') }}" class="card card-pad">
                @csrf
                @include('users.form')
                <div class="mt-8 flex items-center justify-end gap-3">
                    <a href="{{ route('users.index') }}" class="btn-ghost btn-sm">Cancel</a>
                    <button class="btn-primary">Create user</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
