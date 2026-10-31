<?php

namespace App\Support;

use App\Models\User;

/**
 * The app-shell menu: one catalogue of grouped items and create shortcuts,
 * filtered by role. The sidebar, the mobile drawer, and the command palette
 * all read from here, so their role rules cannot drift apart. Visibility
 * mirrors the route-level authorization; it is never the only guard.
 *
 * Accent classes are written out in full so Tailwind's scanner keeps them
 * (this file is listed in tailwind.config.js `content`).
 */
class Navigation
{
    /** Routes developers/QA (limited access) may see: work surfaces only. */
    private const LIMITED_ROUTES = [
        'dashboard',
        'board.index',
        'tasksheet.index',
        'calendar.index',
        'notes.index',
        'meeting-notes.index',
    ];

    /**
     * The grouped menu the user may see, in display order. Groups left with
     * no visible items are dropped.
     *
     * @return list<array{key: string, label: string, items: list<array{key: string, label: string, route: string, patterns: list<string>, icon: string, color: string}>}>
     */
    public static function groups(User $user): array
    {
        $groups = [];

        foreach (self::catalogue() as $group) {
            $items = array_values(array_filter(
                $group['items'],
                fn (array $item) => self::visible($item, $user),
            ));

            if ($items === []) {
                continue;
            }

            unset($group['items']);
            foreach ($items as &$item) {
                unset($item['visible']);
            }
            unset($item);

            $groups[] = $group + ['items' => $items];
        }

        return $groups;
    }

    /**
     * Shortcuts for the sidebar Create menu, limited to what the user may create.
     *
     * @return list<array{label: string, route: string, icon: string, color: string}>
     */
    public static function createActions(User $user): array
    {
        $actions = [];

        if ($user->canManageReleases()) {
            $actions[] = ['label' => 'New release', 'route' => 'releases.create', 'icon' => 'rocket', 'color' => 'text-violet-600'];
        }

        // Any authenticated user may create events and meeting notes
        // (EventPolicy / MeetingNotePolicy).
        $actions[] = ['label' => 'New event', 'route' => 'events.create', 'icon' => 'calendar', 'color' => 'text-rose-600'];
        $actions[] = ['label' => 'New meeting note', 'route' => 'meeting-notes.create', 'icon' => 'chat-bubbles', 'color' => 'text-teal-600'];

        return $actions;
    }

    /** Whether the current route belongs to this item (nested pages included). */
    public static function isActive(array $item): bool
    {
        return request()->routeIs(...$item['patterns']);
    }

    private static function visible(array $item, User $user): bool
    {
        if ($user->hasLimitedAccess() && ! in_array($item['route'], self::LIMITED_ROUTES, true)) {
            return false;
        }

        return ($item['visible'] ?? null) === null || ($item['visible'])($user);
    }

    private static function catalogue(): array
    {
        return [
            [
                // Rendered without a heading.
                'key' => 'overview',
                'label' => 'Overview',
                'items' => [
                    ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'dashboard', 'patterns' => ['dashboard'], 'icon' => 'squares', 'color' => 'text-brand-600'],
                ],
            ],
            [
                'key' => 'planning',
                'label' => 'Planning',
                'items' => [
                    ['key' => 'releases', 'label' => 'Releases', 'route' => 'releases.index', 'patterns' => ['releases.*'], 'icon' => 'rocket', 'color' => 'text-violet-600'],
                    ['key' => 'projects', 'label' => 'Projects', 'route' => 'projects.index', 'patterns' => ['projects.*'], 'icon' => 'folder', 'color' => 'text-amber-700'],
                    ['key' => 'teams', 'label' => 'Teams', 'route' => 'teams.index', 'patterns' => ['teams.*'], 'icon' => 'user-group', 'color' => 'text-pink-600'],
                ],
            ],
            [
                'key' => 'work',
                'label' => 'Work',
                'items' => [
                    ['key' => 'board', 'label' => 'Board', 'route' => 'board.index', 'patterns' => ['board.*', 'tasks.*'], 'icon' => 'view-columns', 'color' => 'text-sky-600'],
                    ['key' => 'tasksheet', 'label' => 'Tasksheet', 'route' => 'tasksheet.index', 'patterns' => ['tasksheet.*'], 'icon' => 'clipboard-check', 'color' => 'text-emerald-600'],
                    ['key' => 'calendar', 'label' => 'Calendar', 'route' => 'calendar.index', 'patterns' => ['calendar.*', 'events.*'], 'icon' => 'calendar', 'color' => 'text-rose-600'],
                ],
            ],
            [
                'key' => 'collaboration',
                'label' => 'Collaboration',
                'items' => [
                    ['key' => 'notes', 'label' => 'Notes', 'route' => 'notes.index', 'patterns' => ['notes.*'], 'icon' => 'pencil-square', 'color' => 'text-orange-600'],
                    ['key' => 'meetings', 'label' => 'Meetings', 'route' => 'meeting-notes.index', 'patterns' => ['meeting-notes.*'], 'icon' => 'chat-bubbles', 'color' => 'text-teal-600'],
                    ['key' => 'activity', 'label' => 'Activity', 'route' => 'activity.index', 'patterns' => ['activity.*'], 'icon' => 'clock', 'color' => 'text-cyan-600'],
                ],
            ],
            [
                'key' => 'administration',
                'label' => 'Administration',
                'items' => [
                    // Lead-only (sensitive HR data); routes enforce this too.
                    ['key' => 'performance', 'label' => 'Performance', 'route' => 'performance.index', 'patterns' => ['performance.*'], 'icon' => 'chart-bar', 'color' => 'text-fuchsia-600', 'visible' => fn (User $u) => $u->isLead()],
                    ['key' => 'users', 'label' => 'Users', 'route' => 'users.index', 'patterns' => ['users.*'], 'icon' => 'identification', 'color' => 'text-slate-600', 'visible' => fn (User $u) => $u->canManageUsers()],
                ],
            ],
        ];
    }
}
