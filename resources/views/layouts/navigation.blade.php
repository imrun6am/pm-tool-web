@php
    $user = auth()->user();
    $nav = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'patterns' => ['dashboard'], 'icon' => 'home'],
        ['label' => 'Releases', 'route' => 'releases.index', 'patterns' => ['releases.*'], 'icon' => 'rocket'],
        ['label' => 'Board', 'route' => 'board.index', 'patterns' => ['board.*'], 'icon' => 'board'],
        ['label' => 'Calendar', 'route' => 'calendar.index', 'patterns' => ['calendar.*', 'events.*'], 'icon' => 'calendar'],
        ['label' => 'Notes', 'route' => 'notes.index', 'patterns' => ['notes.*'], 'icon' => 'note'],
        ['label' => 'Meetings', 'route' => 'meeting-notes.index', 'patterns' => ['meeting-notes.*'], 'icon' => 'chat'],
        ['label' => 'Tasksheet', 'route' => 'tasksheet.index', 'patterns' => ['tasksheet.*'], 'icon' => 'list'],
        ['label' => 'Projects', 'route' => 'projects.index', 'patterns' => ['projects.*'], 'icon' => 'folder'],
        ['label' => 'Teams', 'route' => 'teams.index', 'patterns' => ['teams.*'], 'icon' => 'team'],
        ['label' => 'Activity', 'route' => 'activity.index', 'patterns' => ['activity.*'], 'icon' => 'activity'],
    ];
    if ($user->isLead()) {
        // Performance is lead-only (sensitive HR data). Never offered to
        // developers, QA, or viewers; the routes enforce this server-side too.
        $nav[] = ['label' => 'Performance', 'route' => 'performance.index', 'patterns' => ['performance.*'], 'icon' => 'activity'];
    }
    if ($user->canManageUsers()) {
        $nav[] = ['label' => 'Users', 'route' => 'users.index', 'patterns' => ['users.*'], 'icon' => 'users'];
    }
    if ($user->hasLimitedAccess()) {
        // Developers/QA: work surfaces only — no planning sections.
        $allowed = ['dashboard', 'board.index', 'calendar.index', 'notes.index', 'meeting-notes.index', 'tasksheet.index'];
        $nav = array_values(array_filter($nav, fn ($item) => in_array($item['route'], $allowed, true)));
    }
    $initials = collect(explode(' ', $user->name))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');
@endphp

<nav x-data="{ open: false }" class="app-container">
    <div class="flex h-16 items-center justify-between gap-4">
        {{-- Left: brand + primary nav --}}
        <div class="flex items-center gap-6">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white shadow-sm">RP</span>
                <span class="hidden text-sm font-semibold tracking-tight text-slate-900 sm:inline">Release Planner</span>
            </a>

            <div class="hidden items-center gap-0.5 lg:flex">
                @foreach ($nav as $item)
                    @php $active = request()->routeIs(...$item['patterns']); @endphp
                    <a href="{{ route($item['route']) }}"
                       @class([
                           'rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                           'bg-brand-50 text-brand-700' => $active,
                           'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! $active,
                       ])>{{ $item['label'] }}</a>
                @endforeach
            </div>
        </div>

        {{-- Right: actions + user menu --}}
        <div class="flex items-center gap-3">
            @if ($user->canManageReleases())
                <a href="{{ route('releases.create') }}" class="btn-primary btn-sm hidden sm:inline-flex">
                    <x-icon name="plus" class="h-4 w-4" />
                    New release
                </a>
            @endif

            <x-dropdown align="right" width="w-56">
                <x-slot name="trigger">
                    <button class="flex items-center gap-2.5 rounded-lg py-1 pl-1.5 pr-1 text-sm text-slate-600 transition hover:bg-slate-100">
                        <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">{{ strtoupper($initials) }}</span>
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
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();" class="flex items-center gap-2.5">
                            <x-icon name="logout" class="h-4 w-4 text-slate-400" />{{ __('Log Out') }}
                        </x-dropdown-link>
                    </form>
                </x-slot>
            </x-dropdown>

            {{-- Hamburger --}}
            <button @click="open = ! open" class="inline-flex items-center justify-center rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Mobile menu --}}
    <div x-show="open" x-cloak class="border-t border-slate-100 py-2 lg:hidden">
        <div class="space-y-1">
            @foreach ($nav as $item)
                @php $active = request()->routeIs(...$item['patterns']); @endphp
                <a href="{{ route($item['route']) }}"
                   @class([
                       'flex items-center gap-3 rounded-lg px-3 py-2 text-base font-medium',
                       'bg-brand-50 text-brand-700' => $active,
                       'text-slate-600 hover:bg-slate-100' => ! $active,
                   ])>
                    <x-icon name="{{ $item['icon'] }}" @class(['h-5 w-5', 'text-brand-600' => $active, 'text-slate-400' => ! $active]) />
                    {{ $item['label'] }}
                </a>
            @endforeach
            @if ($user->canManageReleases())
                <a href="{{ route('releases.create') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-base font-medium text-brand-700 hover:bg-brand-50">
                    <x-icon name="plus" class="h-5 w-5" />
                    New release
                </a>
            @endif
        </div>
    </div>
</nav>
