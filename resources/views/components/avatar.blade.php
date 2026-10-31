@props(['user' => null, 'name' => null, 'size' => 'md'])
{{-- Initials avatar. The tone is derived from the user id, so a person keeps
     the same color everywhere. Decorative: always pair with a visible name. --}}
@php
    $name = $name ?? $user?->name ?? '?';
    $initials = collect(preg_split('/\s+/', trim($name)))->filter()->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') ?: '?';
    $tones = [
        'bg-brand-100 text-brand-700',
        'bg-violet-100 text-violet-700',
        'bg-sky-100 text-sky-800',
        'bg-emerald-100 text-emerald-800',
        'bg-amber-100 text-amber-800',
        'bg-rose-100 text-rose-700',
        'bg-teal-100 text-teal-800',
        'bg-fuchsia-100 text-fuchsia-700',
    ];
    $tone = $tones[($user?->id ?? crc32($name)) % count($tones)];
    $sizes = [
        'xs' => 'h-5 w-5 text-[9px]',
        'sm' => 'h-6 w-6 text-[10px]',
        'md' => 'h-8 w-8 text-xs',
        'lg' => 'h-10 w-10 text-sm',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex flex-none select-none items-center justify-center rounded-full font-semibold '.$tone.' '.($sizes[$size] ?? $sizes['md'])]) }} aria-hidden="true">{{ $initials }}</span>
