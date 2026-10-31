{{--
    A 1–5 performance score chip with the canonical colour scale.
    @include('partials.perf-score', ['value' => 4.2, 'showLabel' => true])
    `value` may be null (renders a neutral "—"). `showLabel` appends the anchor.
--}}
@php
    use App\Support\ScoreColor;
    $value = $value ?? null;
    $showLabel = $showLabel ?? false;
@endphp
<span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset {{ ScoreColor::pill($value) }}">
    {{ ScoreColor::fmt($value) }}
    @if ($showLabel && $value !== null)<span class="font-normal opacity-75">{{ ScoreColor::label($value) }}</span>@endif
</span>
