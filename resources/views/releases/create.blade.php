<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="eyebrow">Release planning</p>
                <h2 class="page-title mt-1">New release plan</h2>
                <p class="mt-1 text-sm text-slate-500">Define the window, split it into phases, and flag any off-days.</p>
            </div>
            <a href="{{ route('dashboard') }}" class="btn-secondary btn-sm">Cancel</a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <form method="POST" action="{{ route('releases.store') }}">
                @csrf
                @include('releases.form')

                <div class="mt-6 flex items-center justify-end gap-3 border-t border-slate-200 pt-6">
                    <a href="{{ route('dashboard') }}" class="btn-ghost btn-sm">Cancel</a>
                    <button class="btn-primary">Create release</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
