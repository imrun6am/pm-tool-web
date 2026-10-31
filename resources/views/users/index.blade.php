<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">Users</h2>
            <a href="{{ route('users.create') }}" class="btn-primary btn-sm">
                <x-icon name="plus" class="h-4 w-4" />
                New user
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="app-container space-y-6">
            @php $hasFilters = $filters['search'] !== '' || $filters['role'] || $filters['status']; @endphp

            {{-- Overview (whole directory, independent of filters) --}}
            @if ($stats['total'] > 0)
                @php
                    $roleColors = [
                        'admin' => '#6366f1',      // indigo-500
                        'cto' => '#a855f7',        // purple-500
                        'tech_lead' => '#10b981',  // emerald-500
                        'team_lead' => '#06b6d4',  // cyan-500
                        'developer' => '#3b82f6',  // blue-500
                        'qa' => '#f59e0b',         // amber-500
                        'viewer' => '#94a3b8',     // slate-400
                    ];
                    $roleRows = collect($roleDistribution)
                        ->map(fn ($r) => ['label' => $r['label'], 'value' => $r['count'], 'color' => $roleColors[$r['role']] ?? '#94a3b8'])
                        ->all();
                @endphp

                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    @include('partials.stat-tile', ['label' => 'Users', 'value' => $stats['total'], 'sub' => 'total accounts', 'icon' => 'users', 'tone' => 'brand'])
                    @include('partials.stat-tile', ['label' => 'Active', 'value' => $stats['active'], 'sub' => 'can sign in', 'icon' => 'user', 'tone' => 'emerald'])
                    @include('partials.stat-tile', ['label' => 'Deactivated', 'value' => $stats['inactive'], 'sub' => 'access revoked', 'icon' => 'pause', 'tone' => 'slate'])
                    @include('partials.stat-tile', ['label' => 'Engineers', 'value' => $stats['engineers'], 'sub' => 'developers + QA', 'icon' => 'team', 'tone' => 'sky'])
                </div>

                @include('partials.hbar-chart', [
                    'title' => 'Users by role',
                    'subtitle' => 'Across the whole directory',
                    'rows' => $roleRows,
                    'emptyText' => 'No users yet.',
                ])
            @endif

            {{-- Filters --}}
            <form method="GET" action="{{ route('users.index') }}" class="card p-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="lg:col-span-2">
                        <label class="eyebrow" for="q">Search</label>
                        <div class="relative mt-1">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <x-icon name="search" class="h-4 w-4" />
                            </span>
                            <input type="search" name="q" id="q" value="{{ $filters['search'] }}"
                                   placeholder="Name or email" class="field-input !mt-0 !pl-9">
                        </div>
                    </div>
                    <div>
                        <label class="eyebrow" for="role">Role</label>
                        <select name="role" id="role" class="field-select">
                            <option value="">All roles</option>
                            @foreach ($roles as $value => $label)
                                <option value="{{ $value }}" @selected($filters['role'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="eyebrow" for="status">Status</label>
                        <select name="status" id="status" class="field-select">
                            <option value="">All</option>
                            <option value="active" @selected($filters['status'] === 'active')>Active</option>
                            <option value="inactive" @selected($filters['status'] === 'inactive')>Deactivated</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-4">
                        <button class="btn-primary btn-sm">Apply</button>
                        @if ($hasFilters)
                            <a href="{{ route('users.index') }}" class="btn-secondary btn-sm">Reset</a>
                        @endif
                        <span class="ml-auto self-center text-xs text-slate-400">
                            {{ $users->count() }} {{ Str::plural('user', $users->count()) }}
                        </span>
                    </div>
                </div>
            </form>

            <div class="card overflow-hidden">
                @if ($users->isEmpty())
                    <div class="p-12 text-center">
                        <x-icon name="users" class="mx-auto h-10 w-10 text-slate-300" />
                        <p class="mt-3 text-sm text-slate-500">
                            {{ $hasFilters ? 'No users match these filters.' : 'No users yet.' }}
                        </p>
                    </div>
                @else
                <table class="table-base">
                    <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Name</th>
                            <th class="px-6 py-3">Email</th>
                            <th class="px-6 py-3">Role</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($users as $user)
                            <tr class="{{ $user->isActive() ? '' : 'bg-slate-50/60' }}">
                                <td class="px-6 py-4">
                                    <span class="font-medium text-slate-900">{{ $user->name }}</span>
                                    @if ($user->id === auth()->id())
                                        <span class="ml-1 text-xs text-slate-400">(you)</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-slate-600">{{ $user->email }}</td>
                                <td class="px-6 py-4">@include('partials.role-badge', ['role' => $user->role])</td>
                                <td class="px-6 py-4">
                                    @if ($user->isActive())
                                        <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Active</span>
                                    @else
                                        <span class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700">Deactivated</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-1">
                                        @if ($user->hasLimitedAccess())
                                            <x-action-btn icon="list" tone="brand" :href="route('tasksheet.user', $user)" title="Tasksheet history" aria-label="Tasksheet history of {{ $user->name }}" />
                                        @endif
                                        <x-action-btn icon="pencil" tone="brand" :href="route('users.edit', $user)" title="Edit" aria-label="Edit {{ $user->name }}" />
                                        <form method="POST" action="{{ route('users.toggle', $user) }}">
                                            @csrf
                                            @if ($user->isActive())
                                                <x-action-btn icon="pause" tone="amber" title="Deactivate" aria-label="Deactivate {{ $user->name }}" />
                                            @else
                                                <x-action-btn icon="restore" tone="emerald" title="Reactivate" aria-label="Reactivate {{ $user->name }}" />
                                            @endif
                                        </form>
                                        <form method="POST" action="{{ route('users.destroy', $user) }}"
                                              data-confirm="Delete this user? They can no longer sign in; their data stays visible tagged “Deleted user”." data-confirm-verb="Delete">
                                            @csrf @method('DELETE')
                                            <x-action-btn icon="trash" tone="rose" title="Delete" aria-label="Delete {{ $user->name }}" />
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
