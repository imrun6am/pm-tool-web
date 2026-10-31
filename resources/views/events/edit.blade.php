<x-app-layout>
    <x-slot name="header">
        <h2 class="page-title">Edit event</h2>
    </x-slot>

    <div class="py-8">
        <div class="app-container">
            <form method="POST" action="{{ route('events.update', $event) }}" class="card card-pad">
                @csrf @method('PUT')
                @include('events.form')
                <div class="mt-8 flex items-center justify-end gap-3">
                    <a href="{{ route('events.show', $event) }}" class="btn-ghost btn-sm">Cancel</a>
                    <button class="btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
