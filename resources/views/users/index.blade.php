<x-app-layout>
    @php $hasFilters = $filters['search'] !== '' || $filters['role'] || $filters['status']; @endphp

    <x-slot name="header">
        <x-page-header title="Users" description="Accounts, roles and access for everyone in the workspace.">
            <x-slot name="actions">
                <a href="{{ route('users.create') }}" class="btn-primary btn-sm">
                    <x-icon name="plus" variant="outline" class="h-4 w-4" />
                    New user
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-4">
            {{-- Compact overview (whole directory, independent of filters) --}}
            @if ($stats['total'] > 0)
                @php
                    $summary = [
                        ['label' => 'Users', 'value' => $stats['total'], 'sub' => 'total accounts', 'icon' => 'user-group', 'tone' => 'brand'],
                        ['label' => 'Active', 'value' => $stats['active'], 'sub' => 'can sign in', 'icon' => 'check', 'tone' => 'emerald'],
                        ['label' => 'Deactivated', 'value' => $stats['inactive'], 'sub' => 'access revoked', 'icon' => 'lock-closed', 'tone' => 'slate'],
                        ['label' => 'Engineers', 'value' => $stats['engineers'], 'sub' => 'developers + QA', 'icon' => 'identification', 'tone' => 'sky'],
                    ];
                @endphp
                <div class="stat-strip">
                    @foreach ($summary as $s)
                        <x-stat :label="$s['label']" :value="$s['value']" :sub="$s['sub']" :icon="$s['icon']" :tone="$s['tone']" />
                    @endforeach
                </div>
            @endif

            <x-filter-bar :action="route('users.index')" :active="$hasFilters" :count="$users->count()" noun="user">
                <x-slot name="search">
                    <label for="q" class="filter-label">Search</label>
                    <input type="search" name="q" id="q" value="{{ $filters['search'] }}"
                           placeholder="Name or email…" class="filter-search">
                </x-slot>

                <label for="role" class="filter-label">Role</label>
                <select name="role" id="role" class="filter-select">
                    <option value="">All roles</option>
                    @foreach ($roles as $value => $label)
                        <option value="{{ $value }}" @selected($filters['role'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <label for="status" class="filter-label">Status</label>
                <select name="status" id="status" class="filter-select">
                    <option value="">All statuses</option>
                    <option value="active" @selected($filters['status'] === 'active')>Active</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>Deactivated</option>
                </select>
            </x-filter-bar>

            <div class="card overflow-hidden">
                @if ($users->isEmpty())
                    @if ($hasFilters)
                        <x-empty-state icon="funnel" title="No users match these filters."
                                       description="Try a different name, role or status.">
                            <a href="{{ route('users.index') }}" class="btn-secondary btn-sm">Clear all filters</a>
                        </x-empty-state>
                    @else
                        <x-empty-state icon="user-group" title="No users yet."
                                       description="Invite your team so you can assign them to releases.">
                            <a href="{{ route('users.create') }}" class="btn-primary btn-sm">
                                <x-icon name="plus" variant="outline" class="h-4 w-4" />New user
                            </a>
                        </x-empty-state>
                    @endif
                @else
                    <div class="table-wrap">
                        <table class="table-base">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $user)
                                    <tr class="{{ $user->isActive() ? '' : 'bg-slate-50/60' }}">
                                        <td>
                                            <span class="flex items-center gap-2.5 whitespace-nowrap">
                                                <x-avatar :user="$user" size="sm" />
                                                <span class="font-medium {{ $user->isActive() ? 'text-slate-900' : 'text-slate-500' }}">{{ $user->name }}</span>
                                                @if ($user->id === auth()->id())
                                                    <span class="text-xs text-slate-400">(you)</span>
                                                @endif
                                            </span>
                                        </td>
                                        <td class="text-slate-600">{{ $user->email }}</td>
                                        <td>@include('partials.role-badge', ['role' => $user->role])</td>
                                        <td>
                                            @if ($user->isActive())
                                                <span class="badge badge-success">Active</span>
                                            @else
                                                <span class="badge badge-slate">Deactivated</span>
                                            @endif
                                        </td>
                                        <td>
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
                    </div>
                @endif
            </div>

            {{-- Breakdown sits below the table so the directory is visible first --}}
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
                <div class="pt-2">
                    @include('partials.hbar-chart', [
                        'title' => 'Users by role',
                        'subtitle' => 'Across the whole directory',
                        'rows' => $roleRows,
                        'emptyText' => 'No users yet.',
                    ])
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
