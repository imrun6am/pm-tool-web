<x-app-layout>
    <x-slot name="header">
        <x-page-header title="New competency" description="Add a skill or behaviour to score during performance evaluations."
                       :breadcrumbs="['Performance' => route('performance.index'), 'Competencies' => route('performance.competencies.index'), 'New competency' => null]" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <form method="POST" action="{{ route('performance.competencies.store') }}" class="mx-auto max-w-5xl space-y-4">
                @csrf
                @include('performance.competencies._form')
                <x-form-actions :cancel="route('performance.competencies.index')" submit="Add competency" />
            </form>
        </div>
    </div>
</x-app-layout>
