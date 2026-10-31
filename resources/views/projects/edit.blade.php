<x-app-layout>
    <x-slot name="header">
        <h2 class="page-title">Edit project</h2>
    </x-slot>

    <div class="py-8">
        <div class="app-container">
            <form method="POST" action="{{ route('projects.update', $project) }}" class="card card-pad">
                @csrf @method('PUT')
                @include('projects.form')
                <div class="mt-8 flex items-center justify-end gap-3">
                    <a href="{{ route('projects.index') }}" class="btn-ghost btn-sm">Cancel</a>
                    <button class="btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
