@php
    use App\Models\Task;
    use App\Models\TriageItem;

    $me = auth()->user();
    $teamChoice = ! $me->hasLimitedAccess() || $teams->count() > 1;
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Requests" description="Bugs and requests wait here until a lead accepts or declines them." />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <x-live-region id="triage" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
                <div class="min-w-0 space-y-6">
                    @if ($me->isLead())
                        <section class="card" aria-labelledby="queue-title">
                            <div class="card-header">
                                <h2 id="queue-title" class="card-title">Waiting for a decision</h2>
                                <span class="badge badge-slate tabular">{{ $queue->count() }}</span>
                            </div>
                            @forelse ($queue as $item)
                                <article x-data="{ action: null }" class="border-b border-slate-100 p-4 last:border-b-0 sm:px-5">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="min-w-0 flex-1 break-words text-sm font-semibold text-slate-900">{{ $item->title }}</h3>
                                        @if ($item->isWaitingTooLong())
                                            <span class="badge badge-warning">Waiting {{ $item->created_at->diffForHumans(null, true) }}</span>
                                        @endif
                                        @if ($item->priority)
                                            <span class="badge badge-slate">{{ Task::PRIORITIES[$item->priority] ?? $item->priority }}</span>
                                        @endif
                                        @if ($item->label)
                                            <span class="badge badge-slate"><span class="h-1.5 w-1.5 rounded-full" style="background-color: {{ $item->label->color }}" aria-hidden="true"></span>{{ $item->label->name }}</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $item->reporter?->name ?? 'Former user' }} · {{ $item->team?->name ?? 'No team' }} · <span title="{{ $item->created_at->format('M j, Y g:i a') }}">{{ $item->created_at->diffForHumans() }}</span>
                                    </p>
                                    @if ($item->description)
                                        <p class="mt-2 whitespace-pre-line break-words text-sm text-slate-700">{{ $item->description }}</p>
                                    @endif

                                    <div x-show="! action" class="mt-3 flex gap-2">
                                        <button type="button" @click="action = 'accept'" class="btn-primary btn-sm">Accept</button>
                                        <button type="button" @click="action = 'decline'" class="btn-secondary btn-sm">Decline</button>
                                    </div>

                                    <form x-show="action === 'accept'" x-cloak method="POST" action="{{ route('triage.accept', $item) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                                        @csrf
                                        <div>
                                            <label for="accept-team-{{ $item->id }}" class="field-label !mt-0">Team</label>
                                            <select id="accept-team-{{ $item->id }}" name="team_id" class="field-select field-sm !mt-1">
                                                <option value="">No team</option>
                                                @foreach ($teams as $t)
                                                    <option value="{{ $t->id }}" @selected($item->team_id === $t->id)>{{ $t->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label for="accept-assignee-{{ $item->id }}" class="field-label !mt-0">Assignee</label>
                                            <select id="accept-assignee-{{ $item->id }}" name="assignee_id" class="field-select field-sm !mt-1">
                                                <option value="">Unassigned</option>
                                                @foreach ($users as $u)
                                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label for="accept-priority-{{ $item->id }}" class="field-label !mt-0">Priority</label>
                                            <select id="accept-priority-{{ $item->id }}" name="priority" class="field-select field-sm !mt-1">
                                                <option value="">No priority</option>
                                                @foreach (Task::PRIORITIES as $value => $label)
                                                    <option value="{{ $value }}" @selected($item->priority === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label for="accept-due-{{ $item->id }}" class="field-label !mt-0">Due date</label>
                                            <input id="accept-due-{{ $item->id }}" type="date" name="due_date" class="field-input field-sm !mt-1">
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label for="accept-release-{{ $item->id }}" class="field-label !mt-0">Release</label>
                                            <select id="accept-release-{{ $item->id }}" name="release_id" class="field-select field-sm !mt-1">
                                                <option value="">No release</option>
                                                @foreach ($releases as $r)
                                                    <option value="{{ $r->id }}">{{ $r->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="flex gap-2 sm:col-span-2">
                                            <button class="btn-primary btn-sm">Create task</button>
                                            <button type="button" @click="action = null" class="btn-ghost btn-sm">Cancel</button>
                                        </div>
                                    </form>

                                    <form x-show="action === 'decline'" x-cloak method="POST" action="{{ route('triage.decline', $item) }}" class="mt-3 space-y-2">
                                        @csrf
                                        <label for="decline-reason-{{ $item->id }}" class="field-label !mt-0">Why? The person who sent it will see this.</label>
                                        <input id="decline-reason-{{ $item->id }}" name="reason" required maxlength="500" class="field-control field-sm" autocomplete="off">
                                        <div class="flex gap-2">
                                            <button class="btn-primary btn-sm">Decline request</button>
                                            <button type="button" @click="action = null" class="btn-ghost btn-sm">Cancel</button>
                                        </div>
                                    </form>
                                </article>
                            @empty
                                <p class="px-5 py-6 text-sm text-slate-500">Nothing is waiting.</p>
                            @endforelse
                        </section>

                        @if ($errors->hasAny(['assignee_id', 'release_id', 'team_id', 'priority', 'due_date', 'reason']))
                            <div class="card space-y-1 px-4 py-3">
                                @foreach (['assignee_id', 'release_id', 'team_id', 'priority', 'due_date', 'reason'] as $field)
                                    @error($field)<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                @endforeach
                            </div>
                        @endif
                    @endif

                    <section class="card" aria-labelledby="mine-title">
                        <div class="card-header"><h2 id="mine-title" class="card-title">What you’ve sent</h2></div>
                        @forelse ($mine as $item)
                            <div class="border-b border-slate-100 px-5 py-3 last:border-b-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="min-w-0 flex-1 break-words text-sm font-medium text-slate-800">{{ $item->title }}</span>
                                    <span @class(['badge', 'badge-slate' => $item->isPending(), 'badge-success' => $item->status === TriageItem::ACCEPTED, 'badge-danger' => $item->status === TriageItem::DECLINED])>{{ ucfirst($item->status) }}</span>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $item->created_at->diffForHumans() }}@if ($item->decider) · decided by {{ $item->decider->name }}@endif</p>
                                @if ($item->status === TriageItem::ACCEPTED && $item->task)
                                    <a href="{{ route('tasks.show', $item->task) }}" class="mt-1 inline-block text-xs font-medium text-brand-700 hover:text-brand-800">Open {{ $item->task->reference() }}</a>
                                @endif
                                @if ($item->decline_reason)
                                    <p class="mt-1 break-words text-xs text-slate-600">{{ $item->decline_reason }}</p>
                                @endif
                            </div>
                        @empty
                            <p class="px-5 py-6 text-sm text-slate-500">You haven’t sent any requests.</p>
                        @endforelse
                    </section>
                </div>

                <form method="POST" action="{{ route('triage.store') }}" class="card space-y-3 p-4 lg:sticky lg:top-20">
                    @csrf
                    <h2 class="card-title">Send a request</h2>
                    <div>
                        <label for="request-title" class="field-label !mt-0">What’s needed?</label>
                        <input id="request-title" name="title" required maxlength="255" value="{{ old('title') }}" class="field-control" autocomplete="off">
                        @error('title')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="request-description" class="field-label !mt-0">Details</label>
                        <textarea id="request-description" name="description" rows="4" maxlength="5000" class="field-control">{{ old('description') }}</textarea>
                    </div>
                    @if ($teamChoice)
                        <div>
                            <label for="request-team" class="field-label !mt-0">Team</label>
                            <select id="request-team" name="team_id" class="field-select field-sm !mt-1">
                                <option value="">Not sure</option>
                                @foreach ($teams as $t)
                                    <option value="{{ $t->id }}" @selected((int) old('team_id') === $t->id)>{{ $t->name }}</option>
                                @endforeach
                            </select>
                            @error('team_id')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                        </div>
                    @endif
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="request-priority" class="field-label !mt-0">Priority</label>
                            <select id="request-priority" name="priority" class="field-select field-sm !mt-1">
                                <option value="">Not sure</option>
                                @foreach (Task::PRIORITIES as $value => $label)
                                    <option value="{{ $value }}" @selected(old('priority') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="request-label" class="field-label !mt-0">Type</label>
                            <select id="request-label" name="label_id" class="field-select field-sm !mt-1">
                                <option value="">Not sure</option>
                                @foreach ($labels as $l)
                                    <option value="{{ $l->id }}" @selected((int) old('label_id') === $l->id)>{{ $l->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <button class="btn-primary btn-sm">Send request</button>
                </form>
            </x-live-region>
        </div>
    </div>
</x-app-layout>
