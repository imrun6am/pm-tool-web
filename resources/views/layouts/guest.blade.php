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
    <body class="min-h-full font-sans antialiased">
        <div class="flex min-h-screen flex-col items-center justify-center bg-slate-100 px-4 py-10">
            <div class="mb-6 flex items-center gap-2.5">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white shadow-sm">PM</span>
                <span class="text-lg font-semibold tracking-tight text-slate-900">PM Tool</span>
            </div>

            <div class="w-full max-w-md">
                <div class="card card-pad">
                    {{ $slot }}
                </div>
                <p class="mt-6 text-center text-xs text-slate-400">Coordinate releases, tasks and meetings in one place.</p>
            </div>
        </div>
    </body>
</html>
