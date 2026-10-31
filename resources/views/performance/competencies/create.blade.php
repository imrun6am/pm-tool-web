<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('performance.competencies.index') }}" class="btn-secondary btn-sm !px-2" aria-label="Back">‹</a>
            <h2 class="page-title">New competency</h2>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container max-w-2xl">
            <form method="POST" action="{{ route('performance.competencies.store') }}" class="card card-pad">
                @csrf
                @include('performance.competencies._form')
                <div class="mt-6 flex items-center justify-end gap-2">
                    <a href="{{ route('performance.competencies.index') }}" class="btn-ghost btn-sm">Cancel</a>
                    <button class="btn-primary btn-sm">Add competency</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
