@props(['note'])

{{-- The meeting's type, coloured from MeetingNote::TYPE_COLORS. A type retired
     from the constant falls back to a slate badge rather than disappearing. --}}
<span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium']) }}
      style="background-color: {{ $note->typeColor() }}1a; color: {{ $note->typeColor() }};">
    <span class="h-1.5 w-1.5 rounded-full" style="background-color: {{ $note->typeColor() }};"></span>
    {{ $note->typeLabel() }}
</span>
