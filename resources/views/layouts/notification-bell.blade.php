{{-- Top-bar inbox: unread badge + latest notifications ($bellUnread,
     $bellLatest from EngagementServiceProvider). Rendered fresh on every
     soft navigation; `notificationBell` (resources/js/engagement.js) also
     re-checks the count every minute while the tab is visible. --}}
<div class="relative" x-data="notificationBell({{ $bellUnread }}, @js(route('notifications.unread-count')))"
     @click.outside="open = false" @keydown.escape.window="open = false">
    <button type="button" @click="open = ! open" x-ref="trigger"
            class="btn-ghost btn-icon relative text-slate-500" :aria-expanded="open.toString()" aria-haspopup="true"
            :aria-label="count ? `Notifications, ${count} unread` : 'Notifications'" aria-label="Notifications">
        <x-icon name="bell" variant="outline" class="h-5 w-5" />
        <span x-show="count > 0" x-cloak x-text="count > 9 ? '9+' : count"
              class="bell-badge absolute right-1 top-1 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold leading-none text-white ring-2 ring-white">{{ $bellUnread > 9 ? '9+' : $bellUnread }}</span>
    </button>

    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-150 motion-reduce:transition-none" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100 motion-reduce:transition-none" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="absolute right-0 z-50 mt-2 w-[min(24rem,calc(100vw-1.5rem))] overflow-hidden rounded-xl bg-white shadow-pop ring-1 ring-slate-900/10"
         role="dialog" aria-label="Notifications">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
            <p class="text-sm font-semibold text-slate-900">Notifications</p>
            @if ($bellUnread > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="text-xs font-medium text-brand-700 hover:text-brand-800">Mark all read</button>
                </form>
            @endif
        </div>

        @if ($bellLatest->isEmpty())
            <div class="px-6 py-10 text-center">
                <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                    <x-icon name="check" variant="outline" class="h-5 w-5" />
                </span>
                <p class="mt-3 text-sm font-semibold text-slate-900">Nothing new</p>
                <p class="mt-1 text-sm text-slate-500">We’ll let you know when a task is assigned to you, someone comments, or a due date is near.</p>
            </div>
        @else
            <div class="scroll-area max-h-[min(26rem,70vh)] divide-y divide-slate-100 overflow-y-auto">
                @foreach ($bellLatest as $notification)
                    @include('notifications._item', ['notification' => $notification, 'compact' => true])
                @endforeach
            </div>
        @endif

        <a href="{{ route('notifications.index') }}" class="block border-t border-slate-100 bg-slate-50/70 px-4 py-2.5 text-center text-sm font-medium text-slate-600 hover:text-slate-900">
            See all notifications
        </a>
    </div>
</div>
