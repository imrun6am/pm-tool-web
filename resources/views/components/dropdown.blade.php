@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1.5 bg-white'])

@php
$alignmentClasses = match ($align) {
    'left' => 'ltr:origin-top-left rtl:origin-top-right start-0',
    'top' => 'origin-top',
    default => 'ltr:origin-top-right rtl:origin-top-left end-0',
};

$width = match ($width) {
    '48' => 'w-48',
    default => $width,
};
@endphp

{{-- Menu popover (account menu, Create menu…), styled by .menu-panel /
     .menu-item like every other dropdown. ↓/↑ move between its links and
     buttons (↓ on the trigger opens it); Esc closes. --}}
<div class="relative"
     x-data="{
        open: false,
        focusItem(step) {
            const items = [...$refs.menu.querySelectorAll('a[href], button:not([disabled])')];
            if (! items.length) return;
            const at = items.indexOf(document.activeElement);
            items[(at + step + items.length) % items.length].focus();
        },
     }"
     @click.outside="open = false" @close.stop="open = false" @keydown.escape.window="open = false"
     @keydown.arrow-down.prevent="open ? focusItem(1) : (open = true, $nextTick(() => focusItem(1)))"
     @keydown.arrow-up.prevent="open && focusItem(-1)">
    <div @click="open = ! open">
        {{ $trigger }}
    </div>

    <div x-show="open"
            x-transition:enter="transition ease-out duration-150 motion-reduce:transition-none"
            x-transition:enter-start="opacity-0 scale-[0.97] -translate-y-1"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-75 motion-reduce:transition-none"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute z-50 mt-2 {{ $width }} menu-panel {{ $alignmentClasses }}"
            style="display: none;"
            @click="open = false">
        <div x-ref="menu" class="rounded-xl {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
