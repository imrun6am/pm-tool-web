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

        <title>{{ config('app.name', 'PM Tool') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    @php
        $highlights = [
            ['icon' => 'rocket', 'title' => 'Release planning that sees conflicts', 'text' => 'Every release on one timeline across projects and teams — warned before a team is double-booked.'],
            ['icon' => 'view-columns', 'title' => 'Daily work in one place', 'text' => 'Board, tasksheet, calendar and meeting notes, with quick capture from anywhere.'],
            ['icon' => 'chart-bar', 'title' => 'Progress you can feel', 'text' => 'Personal analytics, gentle reminders and a small “nice work” when things get done.'],
            ['icon' => 'sparkles', 'title' => 'Claude built in', 'text' => 'Risk briefs, release notes and “Ask your workspace” — answers scoped to what you can see.'],
        ];
    @endphp
    <body class="min-h-full bg-white font-sans antialiased">
        <div class="flex min-h-screen flex-col lg:grid lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]">

            {{-- ===== Story panel (lg+). Below lg it collapses to the compact header. ===== --}}
            <aside class="auth-hero relative hidden overflow-hidden lg:flex lg:flex-col lg:justify-between lg:px-12 lg:py-10 xl:px-16" aria-label="About PM Tool">
                <a href="{{ url('/') }}" class="relative flex items-center gap-3 self-start rounded-lg">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-white/15 text-sm font-bold text-white ring-1 ring-inset ring-white/30 backdrop-blur">PM</span>
                    <span class="text-lg font-semibold tracking-tight text-white">PM Tool</span>
                </a>

                <div class="relative max-w-xl py-10">
                    <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-white ring-1 ring-inset ring-white/20">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span> Release planning &amp; team collaboration
                    </p>
                    <h2 class="mt-5 text-4xl font-bold leading-[1.1] tracking-tight text-white xl:text-[2.75rem]">
                        Plan releases.<br>Ship together.
                    </h2>
                    <p class="mt-4 max-w-md text-base leading-relaxed text-white/90">
                        PM Tool keeps every release, task and meeting in one calm workspace — so your team always knows what’s next and nobody gets double-booked.
                    </p>

                    {{-- Abstract timeline: four phase bars per release, a "today" marker. Decorative. --}}
                    <div class="auth-timeline mt-8" aria-hidden="true">
                        <div class="flex items-center justify-between text-[11px] font-medium text-white/80">
                            <span>Q4 timeline</span>
                            <span class="flex items-center gap-3">
                                <span class="flex items-center gap-1"><i class="h-2 w-2 rounded-sm bg-white"></i>Dev</span>
                                <span class="flex items-center gap-1"><i class="h-2 w-2 rounded-sm bg-amber-300"></i>QA</span>
                                <span class="flex items-center gap-1"><i class="h-2 w-2 rounded-sm bg-emerald-300"></i>Release</span>
                            </span>
                        </div>
                        <div class="relative mt-3 space-y-2.5">
                            <span class="absolute inset-y-[-4px] left-[58%] w-px bg-white/70"><span class="absolute -top-1 left-1/2 h-2 w-2 -translate-x-1/2 rounded-full bg-white"></span></span>
                            @foreach ([['Checkout v2.5', 4, 40], ['Mobile 3.1', 30, 46], ['Data platform', 14, 58]] as [$name, $offset, $width])
                                <div class="flex items-center gap-3">
                                    <span class="w-24 flex-none truncate text-xs text-white/90">{{ $name }}</span>
                                    <span class="relative h-3 flex-1">
                                        <span class="absolute inset-y-0 flex overflow-hidden rounded-full shadow-sm" style="left: {{ $offset }}%; width: {{ $width }}%">
                                            <span class="w-1/2 bg-white"></span><span class="w-1/4 bg-amber-300"></span><span class="w-1/4 bg-emerald-300"></span>
                                        </span>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <ul class="mt-8 grid gap-4 sm:grid-cols-2">
                        @foreach ($highlights as $h)
                            <li class="flex gap-3">
                                <span class="flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-white/15 text-white ring-1 ring-inset ring-white/25">
                                    <x-icon :name="$h['icon']" variant="outline" class="h-5 w-5" />
                                </span>
                                <span>
                                    <span class="block text-sm font-semibold text-white">{{ $h['title'] }}</span>
                                    <span class="mt-0.5 block text-[13px] leading-snug text-white/85">{{ $h['text'] }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <p class="relative text-xs text-white/80">Built for teams who ship on a timeline — releases, tasks and meetings, finally in one place.</p>
            </aside>

            {{-- ===== Form panel ===== --}}
            <main class="flex flex-1 flex-col">
                {{-- Compact brand header below lg --}}
                <div class="auth-hero px-5 pb-6 pt-5 lg:hidden">
                    <div class="flex items-center gap-2.5">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-white/15 text-xs font-bold text-white ring-1 ring-inset ring-white/30">PM</span>
                        <span class="text-base font-semibold tracking-tight text-white">PM Tool</span>
                    </div>
                    <p class="mt-3 text-sm leading-snug text-white/90">Plan releases, run the board and ship together — in one calm workspace.</p>
                </div>

                <div class="flex flex-1 items-center justify-center px-5 py-10 sm:px-8">
                    <div class="w-full max-w-sm">
                        {{ $slot }}
                    </div>
                </div>

                <p class="px-5 pb-6 text-center text-xs text-slate-500">© {{ now()->year }} {{ config('app.name', 'PM Tool') }} · Internal workspace</p>
            </main>
        </div>
    </body>
</html>
