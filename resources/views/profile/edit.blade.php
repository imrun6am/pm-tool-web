@php
    $sections = [
        ['id' => 'profile', 'label' => __('Profile'), 'icon' => 'identification'],
        ['id' => 'password', 'label' => __('Password'), 'icon' => 'lock-closed'],
        ['id' => 'preferences', 'label' => 'Preferences', 'icon' => 'sparkles'],
        ['id' => 'danger-zone', 'label' => 'Danger zone', 'icon' => 'exclamation-circle'],
    ];
    $teams = $user->teams()->orderBy('name')->get();
@endphp
<x-app-layout>
    <x-slot name="header">
        {{-- No breadcrumbs: Profile sits outside the menu, so the page must not mark anything aria-current="page". --}}
        <x-page-header :title="__('Profile')" description="Manage your personal details, sign-in password and account." />
    </x-slot>

    <div class="py-6 sm:py-8" x-data="settingsNav(@js(array_column($sections, 'id')))">
        <div class="app-container">
            <div class="grid gap-6 lg:grid-cols-[14rem_minmax(0,1fr)] lg:gap-8">

                {{-- Section nav: sticky column on desktop, scrollable tabs on mobile --}}
                <nav aria-label="Settings sections" class="lg:sticky lg:top-24 lg:self-start">
                    <ul class="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1 lg:mx-0 lg:flex-col lg:overflow-visible lg:px-0 lg:pb-0">
                        @foreach ($sections as $s)
                            <li class="flex-none">
                                <a href="#{{ $s['id'] }}" @click="active = '{{ $s['id'] }}'"
                                   :aria-current="active === '{{ $s['id'] }}' ? 'true' : null"
                                   class="flex items-center gap-2.5 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                                   :class="active === '{{ $s['id'] }}'
                                        ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200'
                                        : 'text-slate-600 hover:bg-white/70 hover:text-slate-900'">
                                    <x-icon :name="$s['icon']" variant="outline"
                                            @class(['h-4 w-4 flex-none', $s['id'] === 'danger-zone' ? 'text-danger-500' : 'text-slate-400']) />
                                    {{ $s['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>

                <div class="min-w-0 space-y-6">
                    {{-- Account overview --}}
                    <section class="card overflow-hidden" aria-label="Account overview">
                        <div class="h-20 border-b border-slate-100 bg-gradient-to-r from-brand-100 via-violet-50 to-sky-100"></div>
                        <div class="flex flex-col gap-4 px-5 pb-5 sm:flex-row sm:items-end sm:gap-5 sm:px-6">
                            <x-avatar :user="$user" size="xl" class="-mt-8 shadow-sm ring-4 ring-white" />
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="truncate text-lg font-semibold tracking-tight text-slate-900">{{ $user->name }}</h2>
                                    @include('partials.role-badge', ['role' => $user->role])
                                </div>
                                <p class="mt-0.5 truncate text-sm text-slate-500">{{ $user->email }}</p>
                            </div>
                            <dl class="flex flex-wrap gap-x-8 gap-y-3 text-sm">
                                <div>
                                    <dt class="text-xs text-slate-500">Member since</dt>
                                    <dd class="mt-0.5 font-medium text-slate-900">{{ $user->created_at?->format('M Y') ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-slate-500">Teams</dt>
                                    <dd class="mt-1 flex flex-wrap gap-1.5">
                                        @forelse ($teams as $team)
                                            <span class="badge badge-slate">
                                                <span class="h-1.5 w-1.5 rounded-full" style="background-color: {{ $team->color }}"></span>{{ $team->name }}
                                            </span>
                                        @empty
                                            <span class="font-medium text-slate-900">None yet</span>
                                        @endforelse
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </section>

                    @include('profile.partials.update-profile-information-form')
                    @include('profile.partials.update-password-form')
                    @include('profile.partials.preferences-form')
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
