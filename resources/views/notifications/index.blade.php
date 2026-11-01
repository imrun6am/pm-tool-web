<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Notifications" icon="bell" tone="notifications" description="Assignments, comments on your tasks, and gentle due-date reminders.">
            @if ($unreadCount > 0)
                <x-slot name="actions">
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        <button type="submit" class="btn-secondary">
                            <x-icon name="check" variant="outline" class="h-4 w-4" /> Mark all read
                        </button>
                    </form>
                </x-slot>
            @endif
        </x-page-header>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-4">
            <nav class="inline-flex rounded-lg bg-slate-100 p-1 text-sm font-medium" aria-label="Filter notifications">
                <a href="{{ route('notifications.index') }}" @class(['rounded-md px-3 py-1.5 transition', 'bg-white text-slate-900 shadow-sm' => ! $unreadOnly, 'text-slate-500 hover:text-slate-800' => $unreadOnly])
                   @if (! $unreadOnly) aria-current="page" @endif>All</a>
                <a href="{{ route('notifications.index', ['unread' => 1]) }}" @class(['rounded-md px-3 py-1.5 transition', 'bg-white text-slate-900 shadow-sm' => $unreadOnly, 'text-slate-500 hover:text-slate-800' => ! $unreadOnly])
                   @if ($unreadOnly) aria-current="page" @endif>
                    Unread @if ($unreadCount > 0)<span class="ml-1 rounded-full bg-brand-100 px-1.5 text-xs text-brand-700">{{ $unreadCount }}</span>@endif
                </a>
            </nav>

            <div class="card overflow-hidden">
                @if ($notifications->isEmpty())
                    <x-empty-state icon="bell" tone="emerald"
                                   :title="$unreadOnly ? 'You’re all caught up' : 'No notifications yet'"
                                   description="You’ll hear here when a task is assigned to you, someone comments on your work, or a due date is close." />
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach ($notifications as $notification)
                            @include('notifications._item', ['notification' => $notification])
                        @endforeach
                    </div>
                @endif
            </div>

            {{ $notifications->links() }}
        </div>
    </div>
</x-app-layout>
