<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Release Planner') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    @php
        // Opt-in "app shell": on lg+, the whole page fits the viewport and the
        // main region scrolls internally instead of the window. Pages request it
        // with <x-app-layout full-height>. Mobile always uses normal page scroll.
        $fullHeight = filter_var($attributes->get('full-height', false), FILTER_VALIDATE_BOOLEAN);
        // Opt-in: pin ONLY the page sub-header below the nav while the page
        // scrolls normally. <x-app-layout sticky-header>. Ignored under full-height.
        $stickyHeader = filter_var($attributes->get('sticky-header', false), FILTER_VALIDATE_BOOLEAN) && ! $fullHeight;
    @endphp
    <body class="min-h-full font-sans">
        <div @class([
            'flex min-h-screen flex-col',
            'lg:h-screen lg:overflow-hidden' => $fullHeight,
        ])>
            {{-- Sticky top bar (sticky still matters on mobile, where full-height
                 pages fall back to normal page scroll). --}}
            <div @class([
                'sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur-md',
                'lg:flex-none' => $fullHeight,
            ])>
                @include('layouts.navigation')
            </div>

            {{-- Page sub-header --}}
            @isset($header)
                <header @class([
                    'border-b border-slate-200 bg-white',
                    'lg:flex-none' => $fullHeight,
                    'sticky top-16 z-30 shadow-sm' => $stickyHeader,
                ])>
                    <div class="app-container py-4 sm:py-5">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            {{-- Inline validation summary (page-scoped, persists while fixing) --}}
            <div @class(['app-container', 'lg:flex-none' => $fullHeight])>
                @include('layouts.flash')
            </div>

            {{-- Page content --}}
            <main @class([
                'flex-1',
                'lg:flex lg:min-h-0 lg:flex-col lg:overflow-hidden' => $fullHeight,
            ])>
                {{ $slot }}
            </main>
        </div>

        {{-- Quick-links slide-over (data via view composer) --}}
        @include('partials.quick-links-drawer')

        {{-- Floating toasts + confirmation modal --}}
        @include('layouts.toasts')
        @include('layouts.confirm')
    </body>
</html>

