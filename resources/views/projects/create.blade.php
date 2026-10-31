<x-app-layout>
    <x-slot name="header">
        <h2 class="page-title">New project</h2>
    </x-slot>

    <div class="py-8">
        <div class="app-container">
            <form method="POST" action="{{ route('projects.store') }}" class="card card-pad">
                @csrf
                @include('projects.form')
                <div class="mt-8 flex items-center justify-end gap-3">
                    <a href="{{ route('projects.index') }}" class="btn-ghost btn-sm">Cancel</a>
                    <button class="btn-primary">Create project</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
