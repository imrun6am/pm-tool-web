@props(['color' => '#94a3b8', 'label', 'href' => null])
{{--
    A team or project, as a dot in its own color plus its name.
      <x-color-chip :color="$release->team->color" :label="$release->team->name" :href="route('teams.show', $release->team)" />
--}}
@php $tag = $href ? 'a' : 'span'; @endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'inline-flex min-w-0 items-center gap-1.5 whitespace-nowrap'.($href ? ' rounded transition-colors hover:text-brand-700' : '')]) }}>
    <span class="h-2.5 w-2.5 flex-none rounded-full ring-2 ring-white" style="background-color: {{ $color }}" aria-hidden="true"></span>
    <span class="truncate">{{ $label }}</span>
</{{ $tag }}>
