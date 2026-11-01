<?php

namespace App\Support;

use App\Models\User;
use App\Services\Ai\AiFeatures;

/**
 * The app-shell menu: one catalogue of grouped items and create shortcuts,
 * filtered by role. The sidebar, the mobile drawer, and the command palette
 * all read from here, so their role rules cannot drift apart. Visibility
 * mirrors the route-level authorization; it is never the only guard.
 *
 * Each item's hue comes from its area in App\Support\Palette::DOMAINS (the
 * item `key`), so the sidebar, ⌘K, the Create menu and page headers always
 * agree; an item may set its own `tone`. `color` is the resolved icon class.
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
        'ai.ask.page', // answers are scoped to what the asker may see
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
                $item = self::withTone($item);
            }
            unset($item);

            $groups[] = $group + ['items' => $items];
        }

        return $groups;
    }

    /**
     * Shortcuts for the sidebar Create menu, limited to what the user may create.
     *
     * @return list<array{label: string, route: string, icon: string, tone: string, color: string}>
     */
    public static function createActions(User $user): array
    {
        // Any signed-in user may add a task (TaskPolicy::create). The link opens
        // the quick-capture dialog (resources/js/engagement.js); /tasks/new is
        // its full-page fallback.
        $actions = [
            ['label' => 'New task', 'route' => 'tasks.quick', 'icon' => 'check', 'tone' => 'tasks'],
        ];

        if ($user->canManageReleases()) {
            $actions[] = ['label' => 'New release', 'route' => 'releases.create', 'icon' => 'rocket', 'tone' => 'releases'];
        }

        // Any authenticated user may create events and meeting notes
        // (EventPolicy / MeetingNotePolicy).
        $actions[] = ['label' => 'New event', 'route' => 'events.create', 'icon' => 'calendar', 'tone' => 'calendar'];
        $actions[] = ['label' => 'New meeting note', 'route' => 'meeting-notes.create', 'icon' => 'chat-bubbles', 'tone' => 'meetings'];

        return array_map(self::withTone(...), $actions);
    }

    /**
     * The catalogue item the current route belongs to (its icon and hue), or
     * null. Used to decorate page headers; visibility is not re-checked, since
     * the user is already on the page.
     *
     * @return array{key: string, label: string, route: string, patterns: list<string>, icon: string, tone: string, color: string}|null
     */
    public static function activeItem(): ?array
    {
        foreach (self::catalogue() as $group) {
            foreach ($group['items'] as $item) {
                if (self::isActive($item)) {
                    unset($item['visible']);

                    return self::withTone($item);
                }
            }
        }

        return null;
    }

    /** Fill in `tone` (from the item's area unless set) and its icon `color`. */
    private static function withTone(array $item): array
    {
        $item['tone'] = Palette::hue($item['tone'] ?? $item['key'] ?? null);
        $item['color'] = Palette::text($item['tone']);

        return $item;
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
                    ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'dashboard', 'patterns' => ['dashboard'], 'icon' => 'squares'],
                    ['key' => 'ask', 'label' => 'Ask Claude', 'route' => 'ai.ask.page', 'patterns' => ['ai.ask.*'], 'icon' => 'magnifying-glass',
                        'visible' => fn () => app(AiFeatures::class)->available('workspace_ask')],
                ],
            ],
            [
                'key' => 'planning',
                'label' => 'Planning',
                'items' => [
                    ['key' => 'releases', 'label' => 'Releases', 'route' => 'releases.index', 'patterns' => ['releases.*'], 'icon' => 'rocket'],
                    ['key' => 'projects', 'label' => 'Projects', 'route' => 'projects.index', 'patterns' => ['projects.*'], 'icon' => 'folder'],
                    ['key' => 'teams', 'label' => 'Teams', 'route' => 'teams.index', 'patterns' => ['teams.*'], 'icon' => 'user-group'],
                ],
            ],
            [
                'key' => 'work',
                'label' => 'Work',
                'items' => [
                    ['key' => 'board', 'label' => 'Board', 'route' => 'board.index', 'patterns' => ['board.*', 'tasks.*'], 'icon' => 'view-columns'],
                    ['key' => 'tasksheet', 'label' => 'Tasksheet', 'route' => 'tasksheet.index', 'patterns' => ['tasksheet.*'], 'icon' => 'clipboard-check'],
                    ['key' => 'calendar', 'label' => 'Calendar', 'route' => 'calendar.index', 'patterns' => ['calendar.*', 'events.*'], 'icon' => 'calendar'],
                ],
            ],
            [
                'key' => 'collaboration',
                'label' => 'Collaboration',
                'items' => [
                    ['key' => 'notes', 'label' => 'Notes', 'route' => 'notes.index', 'patterns' => ['notes.*'], 'icon' => 'pencil-square'],
                    ['key' => 'meetings', 'label' => 'Meetings', 'route' => 'meeting-notes.index', 'patterns' => ['meeting-notes.*'], 'icon' => 'chat-bubbles'],
                    ['key' => 'activity', 'label' => 'Activity', 'route' => 'activity.index', 'patterns' => ['activity.*'], 'icon' => 'clock'],
                ],
            ],
            [
                'key' => 'administration',
                'label' => 'Administration',
                'items' => [
                    // Lead-only (sensitive HR data); routes enforce this too.
                    ['key' => 'performance', 'label' => 'Performance', 'route' => 'performance.index', 'patterns' => ['performance.*'], 'icon' => 'chart-bar', 'visible' => fn (User $u) => $u->isLead()],
                    ['key' => 'kpi-reports', 'label' => 'KPI reports', 'route' => 'kpi-reports.index', 'patterns' => ['kpi-reports.*'], 'icon' => 'document-text', 'visible' => fn (User $u) => $u->isAdmin()],
                    ['key' => 'users', 'label' => 'Users', 'route' => 'users.index', 'patterns' => ['users.*'], 'icon' => 'identification', 'visible' => fn (User $u) => $u->canManageUsers()],
                    ['key' => 'ai-usage', 'label' => 'AI usage', 'route' => 'ai.usage', 'patterns' => ['ai.usage'], 'icon' => 'sparkles', 'visible' => fn (User $u) => $u->isAdmin()],
                ],
            ],
        ];
    }
}
