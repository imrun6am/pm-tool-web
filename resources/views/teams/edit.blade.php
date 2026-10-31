<x-app-layout>
    <x-slot name="header">
        <h2 class="page-title">Edit team</h2>
    </x-slot>

    <div class="py-8">
        <div class="app-container">
            <form method="POST" action="{{ route('teams.update', $team) }}" class="card card-pad">
                @csrf @method('PUT')
                @include('teams.form')
                <div class="mt-8 flex items-center justify-end gap-3">
                    <a href="{{ route('teams.index') }}" class="btn-ghost btn-sm">Cancel</a>
                    <button class="btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
