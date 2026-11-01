@props(['error' => 'error'])
{{-- Inline error line for an AI component; shows the `error` expression when set. --}}
<p x-show="{{ $error }}" x-cloak role="alert" {{ $attributes->class(['flex items-start gap-1.5 text-xs text-danger-600']) }}>
    <x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />
    <span x-text="{{ $error }}"></span>
</p>
