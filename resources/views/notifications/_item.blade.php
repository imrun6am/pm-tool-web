@php
    /** @var \Illuminate\Notifications\DatabaseNotification $notification */
    $data = $notification->data;
    $kind = $data['kind'] ?? 'assigned';
    $unread = $notification->read_at === null;
    $compact = $compact ?? false;
    $kinds = [
        'assigned' => ['icon' => 'user-plus', 'tone' => 'bg-brand-50 text-brand-600'],
        'commented' => ['icon' => 'chat-bubbles', 'tone' => 'bg-teal-50 text-teal-600'],
        'due_tomorrow' => ['icon' => 'clock', 'tone' => 'bg-amber-50 text-amber-600'],
        'overdue' => ['icon' => 'exclamation-circle', 'tone' => 'bg-rose-50 text-rose-600'],
    ];
    $style = $kinds[$kind] ?? $kinds['assigned'];
@endphp
{{-- One inbox row (bell dropdown and the Notifications page). Opening it
     marks it read and goes to the task. --}}
<a href="{{ route('notifications.open', $notification->id) }}"
   @class([
       'group flex items-start gap-3 px-4 transition-colors hover:bg-slate-50 focus-visible:bg-slate-50',
       'py-3' => $compact,
       'py-4' => ! $compact,
       'bg-brand-50/40' => $unread,
   ])>
    <span class="relative mt-0.5 flex-none">
        @if (! empty($data['actor_name']))
            <x-avatar :name="$data['actor_name']" size="md" />
            <span @class(['absolute -bottom-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full ring-2 ring-white', $style['tone']])>
                <x-icon :name="$style['icon']" variant="outline" class="h-2.5 w-2.5" />
            </span>
        @else
            <span @class(['flex h-8 w-8 items-center justify-center rounded-full', $style['tone']])>
                <x-icon :name="$style['icon']" variant="outline" class="h-4 w-4" />
            </span>
        @endif
    </span>
    <span class="min-w-0 flex-1">
        <span @class(['block text-sm leading-snug', 'font-medium text-slate-900' => $unread, 'text-slate-600' => ! $unread])>{{ $data['message'] ?? '' }}</span>
        @if (! empty($data['excerpt']))
            <span class="mt-0.5 block truncate text-sm text-slate-500">{{ $data['excerpt'] }}</span>
        @endif
        <span class="mt-1 block text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</span>
    </span>
    @if ($unread)
        <span class="mt-2 h-2 w-2 flex-none rounded-full bg-brand-500" aria-hidden="true"></span>
        <span class="sr-only">Unread</span>
    @endif
</a>
