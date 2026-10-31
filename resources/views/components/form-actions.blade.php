@props(['cancel' => null, 'submit' => 'Save changes'])
{{--
    Sticky action bar for create/edit forms — stays in view while a long form
    scrolls. A `delete` slot sits on the leading side; since forms cannot nest,
    point its button at a separate form: <button form="delete-x" …>.
--}}
<div {{ $attributes->merge(['class' => 'form-actions']) }}>
    @isset($delete)
        <div class="flex items-center gap-2">{{ $delete }}</div>
    @endisset
    <div class="ml-auto flex items-center gap-2">
        {{ $slot }}
        @if ($cancel)
            <a href="{{ $cancel }}" class="btn-ghost">Cancel</a>
        @endif
        <button type="submit" class="btn-primary">{{ $submit }}</button>
    </div>
</div>
