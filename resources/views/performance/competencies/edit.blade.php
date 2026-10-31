<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit competency" :description="$competency->name"
                       :breadcrumbs="['Performance' => route('performance.index'), 'Competencies' => route('performance.competencies.index'), 'Edit' => null]" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <form method="POST" action="{{ route('performance.competencies.update', $competency) }}" class="mx-auto max-w-5xl space-y-4">
                @csrf @method('PUT')
                @include('performance.competencies._form')
                <x-form-actions :cancel="route('performance.competencies.index')" submit="Save changes" />
            </form>
        </div>
    </div>
</x-app-layout>
