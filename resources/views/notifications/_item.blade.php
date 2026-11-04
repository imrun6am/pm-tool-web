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
        'blocked' => ['icon' => 'exclamation-circle', 'tone' => 'bg-rose-50 text-rose-600'],
        'unblocked' => ['icon' => 'check', 'tone' => 'bg-emerald-50 text-emerald-600'],
        'request_new' => ['icon' => 'inbox', 'tone' => 'bg-brand-50 text-brand-600'],
        'request_accepted' => ['icon' => 'check', 'tone' => 'bg-emerald-50 text-emerald-600'],
        'request_declined' => ['icon' => 'x-mark', 'tone' => 'bg-rose-50 text-rose-600'],
        'release_status' => ['icon' => 'exclamation-circle', 'tone' => 'bg-amber-50 text-amber-600'],
        'unassigned' => ['icon' => 'arrow-right', 'tone' => 'bg-slate-100 text-slate-600'],
        'block_cleared' => ['icon' => 'check', 'tone' => 'bg-emerald-50 text-emerald-600'],
        'sent_back' => ['icon' => 'arrow-left', 'tone' => 'bg-amber-50 text-amber-600'],
        'due_changed' => ['icon' => 'clock', 'tone' => 'bg-amber-50 text-amber-600'],
        'urgent' => ['icon' => 'flag', 'tone' => 'bg-rose-50 text-rose-600'],
        'task_deleted' => ['icon' => 'trash', 'tone' => 'bg-slate-100 text-slate-600'],
        'release_created' => ['icon' => 'rocket', 'tone' => 'bg-brand-50 text-brand-600'],
        'release_member_added' => ['icon' => 'rocket', 'tone' => 'bg-brand-50 text-brand-600'],
        'release_dates_changed' => ['icon' => 'calendar', 'tone' => 'bg-amber-50 text-amber-600'],
        'release_completed' => ['icon' => 'check', 'tone' => 'bg-emerald-50 text-emerald-600'],
        'release_reopened' => ['icon' => 'rocket', 'tone' => 'bg-amber-50 text-amber-600'],
        'release_commented' => ['icon' => 'chat-bubbles', 'tone' => 'bg-teal-50 text-teal-600'],
        'meeting_attendee' => ['icon' => 'document-text', 'tone' => 'bg-brand-50 text-brand-600'],
        'event_invited' => ['icon' => 'calendar', 'tone' => 'bg-brand-50 text-brand-600'],
        'event_changed' => ['icon' => 'calendar', 'tone' => 'bg-amber-50 text-amber-600'],
        'event_cancelled' => ['icon' => 'x-mark', 'tone' => 'bg-rose-50 text-rose-600'],
        'event_tomorrow' => ['icon' => 'clock', 'tone' => 'bg-brand-50 text-brand-600'],
        'note_shared' => ['icon' => 'pencil-square', 'tone' => 'bg-teal-50 text-teal-600'],
        'import_ready' => ['icon' => 'calendar', 'tone' => 'bg-emerald-50 text-emerald-600'],
        'import_failed' => ['icon' => 'exclamation-circle', 'tone' => 'bg-rose-50 text-rose-600'],
        'export_ready' => ['icon' => 'document-text', 'tone' => 'bg-emerald-50 text-emerald-600'],
        'export_failed' => ['icon' => 'exclamation-circle', 'tone' => 'bg-rose-50 text-rose-600'],
        'kpi_ready' => ['icon' => 'chart-bar', 'tone' => 'bg-emerald-50 text-emerald-600'],
        'kpi_failed' => ['icon' => 'exclamation-circle', 'tone' => 'bg-rose-50 text-rose-600'],
        'insight_ready' => ['icon' => 'sparkles', 'tone' => 'bg-emerald-50 text-emerald-600'],
        'insight_failed' => ['icon' => 'exclamation-circle', 'tone' => 'bg-rose-50 text-rose-600'],
        'team_added' => ['icon' => 'user-group', 'tone' => 'bg-brand-50 text-brand-600'],
        'team_removed' => ['icon' => 'user-group', 'tone' => 'bg-slate-100 text-slate-600'],
        'team_lead_assigned' => ['icon' => 'user-group', 'tone' => 'bg-brand-50 text-brand-600'],
        'team_lead_removed' => ['icon' => 'user-group', 'tone' => 'bg-slate-100 text-slate-600'],
        'role_changed' => ['icon' => 'identification', 'tone' => 'bg-brand-50 text-brand-600'],
    ];
    $style = $kinds[$kind] ?? $kinds['assigned'];
@endphp
{{-- One inbox row (bell dropdown and the Notifications page). Opening it
     marks it read and goes to what it's about (see NotificationLink). --}}
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
