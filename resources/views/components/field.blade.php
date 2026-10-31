@props(['label', 'for' => null, 'name' => null, 'optional' => false, 'hint' => null])
{{--
    Label + control + hint + inline error. The control itself opts into the
    invalid state with @error('x') aria-invalid="true" aria-describedby="x-error" @enderror,
    which points at the error rendered here (id "{name}-error").
--}}
@php $errorKey = $name ?? $for; @endphp
<div {{ $attributes }}>
    <label @if ($for) for="{{ $for }}" @endif class="field-label">
        {{ $label }}@if ($optional)<span class="field-optional">Optional</span>@endif
    </label>
    {{ $slot }}
    @if ($hint)
        <p class="field-hint">{{ $hint }}</p>
    @endif
    @if ($errorKey)
        @error($errorKey)
            <p id="{{ $errorKey }}-error" class="field-error">
                <x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $message }}
            </p>
        @enderror
    @endif
</div>
