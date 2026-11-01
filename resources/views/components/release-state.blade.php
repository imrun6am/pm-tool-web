@props(['release'])
{{-- The release's state (Release::state()) as a semantic badge. --}}
@php
    $state = $release->state();
    $classes = match ($state) {
        'completed' => 'badge-success',
        'upcoming' => 'badge-info',
        'overdue' => 'badge-warning',
        default => 'badge-brand',
    };
@endphp
<span {{ $attributes->merge(['class' => "badge $classes"]) }}>
    @if ($state === 'completed')
        <x-icon name="check" variant="outline" class="h-3 w-3" />
    @else
        <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
    @endif
    {{ $release->stateLabel() }}
</span>
