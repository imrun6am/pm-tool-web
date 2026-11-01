<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        {{-- Soft navigation (Turbo Drive): no snapshot cache, so back/forward
             re-fetch clean HTML instead of re-booting Alpine over a cloned
             DOM; renders crossfade where view transitions are supported. --}}
        <meta name="turbo-cache-control" content="no-cache">
        <meta name="view-transition" content="same-origin">
        {{-- An action that redirects back to the same page keeps its scroll. --}}
        <meta name="turbo-refresh-scroll" content="preserve">

        <title>{{ config('app.name', 'PM Tool') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Restore the collapsed sidebar before first paint (no layout flash). -->
        <script>try { if (localStorage.getItem('rp.sidebar') === 'collapsed') document.documentElement.dataset.sidebar = 'collapsed'; } catch (e) {}</script>

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
        <div x-data="appShell" @keydown.escape.window="closeMobile()">
            {{-- Grouped sidebar: fixed rail on lg+, off-canvas drawer below. --}}
            @include('layouts.sidebar')

            {{-- Mobile drawer backdrop --}}
            <div x-show="mobileOpen" x-cloak @click="closeMobile()" aria-hidden="true"
                 x-transition:enter="transition-opacity duration-200 motion-reduce:transition-none" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity duration-150 motion-reduce:transition-none" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 class="fixed inset-0 z-[45] bg-slate-900/40 backdrop-blur-[2px] lg:hidden"></div>

            {{-- Content column, offset by the sidebar on lg+ --}}
            <div @class([
                'app-main',
                'lg:h-screen lg:overflow-hidden' => $fullHeight,
            ])>
                {{-- Sticky utility bar (sticky still matters on mobile, where
                     full-height pages fall back to normal page scroll). --}}
                <div @class(['app-topbar', 'lg:flex-none' => $fullHeight])>
                    @include('layouts.navigation')
                </div>

                {{-- Page sub-header --}}
                @isset($header)
                    <header @class([
                        'app-page-header',
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
                    'app-content flex-1',
                    'lg:flex lg:min-h-0 lg:flex-col lg:overflow-hidden' => $fullHeight,
                ])>
                    {{ $slot }}
                </main>
            </div>
        </div>

        {{-- Quick-links slide-over (data via view composer) --}}
        @include('partials.quick-links-drawer')

        {{-- ⌘K command palette (role-filtered entries via view composer) --}}
        @include('layouts.command-palette')

        {{-- Quick capture (press C) + keyboard shortcut sheet (press ?) --}}
        @include('layouts.quick-capture')

        {{-- Colour dots for team/project dropdowns --}}
        @include('layouts.select-swatches')

        {{-- Floating toasts + confirmation modal --}}
        @include('layouts.toasts')
        @include('layouts.confirm')
    </body>
</html>

