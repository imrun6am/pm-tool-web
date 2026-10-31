<x-app-layout>
    <x-slot name="header">
        <h2 class="page-title">Edit meeting note</h2>
    </x-slot>

    <div class="py-8">
        <div class="app-container">
            <form method="POST" action="{{ route('meeting-notes.update', $meetingNote) }}" class="card card-pad">
                @csrf @method('PUT')
                @include('meeting-notes.form')
                <div class="mt-8 flex items-center justify-end gap-3">
                    <a href="{{ route('meeting-notes.show', $meetingNote) }}" class="btn-ghost btn-sm">Cancel</a>
                    <button class="btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
