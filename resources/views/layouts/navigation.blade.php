@php
    $user = auth()->user();
@endphp

{{-- Top utility bar: menu button (mobile), command-palette trigger, quick
     links, and the user menu. Navigation itself lives in layouts/sidebar.
     Rendered inside the `appShell` Alpine scope (see layouts/app). --}}
<div class="app-container flex h-16 items-center gap-3">
    {{-- Mobile: open the sidebar drawer --}}
    <button type="button" x-ref="menuButton" @click="mobileOpen = true"
            class="btn-ghost btn-icon -ml-2 lg:hidden"
            aria-controls="app-sidebar" aria-expanded="false" :aria-expanded="mobileOpen.toString()" aria-label="Open navigation">
        <x-icon name="bars-3" variant="outline" class="h-6 w-6" />
    </button>
    <a href="{{ route('dashboard') }}" class="brand-mark lg:hidden" aria-label="PM Tool home">PM</a>

    {{-- Command palette trigger --}}
    <button type="button" @click="$dispatch('open-command-palette')"
            class="flex h-10 min-w-0 flex-1 items-center gap-2.5 rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-500 transition hover:border-slate-300 hover:bg-white hover:text-slate-700 sm:max-w-md">
        <x-icon name="magnifying-glass" variant="outline" class="h-4 w-4 flex-none text-slate-400" />
        <span class="flex-1 truncate text-left">Search or jump to…</span>
        <span class="hidden flex-none items-center gap-1 sm:flex" aria-hidden="true">
            <kbd class="kbd" x-text="modKey">⌘</kbd><kbd class="kbd">K</kbd>
        </span>
    </button>

    {{-- Right: actions + user menu --}}
    <div class="ml-auto flex flex-none items-center gap-1.5 sm:gap-2">
        @include('layouts.notification-bell')

        <button type="button" @click="$dispatch('toggle-quick-links')"
                class="btn-ghost btn-icon text-slate-500" title="Quick links" aria-label="Quick links">
            <x-icon name="link" variant="outline" class="h-5 w-5" />
        </button>

        <span class="mx-1 hidden h-6 w-px bg-slate-200 sm:block" aria-hidden="true"></span>

        <x-dropdown align="right" width="w-56">
            <x-slot name="trigger">
                <button class="flex items-center gap-2.5 rounded-lg py-1 pl-1.5 pr-1 text-sm text-slate-600 transition hover:bg-slate-100">
                    <x-avatar :user="$user" />
                    <span class="hidden text-left leading-tight sm:block">
                        <span class="block max-w-[11rem] truncate text-sm font-semibold text-slate-800">{{ $user->name }}</span>
                        <span class="block">@include('partials.role-badge', ['role' => $user->role, 'variant' => 'text'])</span>
                    </span>
                    <x-icon name="chevron-down" class="h-4 w-4 flex-none text-slate-400" />
                </button>
            </x-slot>
            <x-slot name="content">
                <div class="border-b border-slate-100 px-4 py-3">
                    <div class="flex items-center justify-between gap-2">
                        <p class="truncate text-sm font-semibold text-slate-900">{{ $user->name }}</p>
                        @include('partials.role-badge', ['role' => $user->role])
                    </div>
                    <p class="mt-0.5 truncate text-xs text-slate-500">{{ $user->email }}</p>
                </div>
                <x-dropdown-link :href="route('profile.edit')" class="flex items-center gap-2.5">
                    <x-icon name="user" class="h-4 w-4 text-slate-400" />{{ __('Profile') }}
                </x-dropdown-link>
                <form method="POST" action="{{ route('logout') }}" data-turbo="false">
                    @csrf
                    <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();" class="flex items-center gap-2.5">
                        <x-icon name="logout" class="h-4 w-4 text-slate-400" />{{ __('Log Out') }}
                    </x-dropdown-link>
                </form>
            </x-slot>
        </x-dropdown>
    </div>
</div>
