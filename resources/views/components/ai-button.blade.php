@props(['label', 'busyLabel' => 'Working…', 'click', 'busy' => 'busy', 'disabled' => 'false', 'size' => 'sm'])
{{--
    The violet "Claude" button every AI feature uses, with a spinner while the
    surrounding Alpine component's `busy` expression is true.
      <x-ai-button label="Draft with Claude" busy-label="Drafting…" click="run()" />
--}}
<button type="button" @click="{{ $click }}" :disabled="{{ $busy }} || {{ $disabled }}" :aria-busy="{{ $busy }} ? 'true' : 'false'"
        {{ $attributes->class(['btn border border-violet-600/20 bg-violet-50 text-violet-700 hover:border-violet-600/30 hover:bg-violet-100 focus-visible:ring-violet-500/50 disabled:opacity-60',
                               'btn-sm' => $size === 'sm']) }}>
    <svg x-show="{{ $busy }}" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3" />
        <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
    </svg>
    <x-icon name="sparkles" variant="outline" class="h-4 w-4" x-show="!{{ $busy }}" />
    <span x-text="{{ $busy }} ? @js($busyLabel) : @js($label)">{{ $label }}</span>
</button>
