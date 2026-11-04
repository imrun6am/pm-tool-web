@php
    use App\Models\Task;
    use App\Models\TriageImport;

    $me = auth()->user();
    $teamChoice = ! $me->hasLimitedAccess() || $teams->count() > 1;
    $pending = collect($rows)->whereNull('triage_item_id');
    $sent = collect($rows)->whereNotNull('triage_item_id');
    $hasOld = session()->hasOldInput('requests');
    $isImage = in_array($import->extension, \App\Services\Calendar\EventImportService::IMAGE_EXTENSIONS, true);
    $source = $import->isPasted() ? 'text' : 'file';
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$import->displayName()"
                       :description="($import->isPasted() ? 'Pasted ' : 'Uploaded ').$import->created_at->diffForHumans().' · '.$import->sizeLabel().' · Only you can see this import'"
                       :breadcrumbs="['Requests' => route('triage.index'), $import->displayName() => null]">
            <x-slot name="actions">
                <span id="import-status"><span class="badge {{ $import->statusBadge() }}">{{ $import->statusLabel() }}</span></span>
                @unless ($import->isPasted())
                    <a href="{{ route('triage.imports.file', $import) }}" class="btn-secondary btn-sm" @if ($isImage) target="_blank" rel="noopener" @else data-turbo="false" @endif>
                        <x-icon name="document-text" variant="outline" class="h-4 w-4" /> Open original
                    </a>
                @endunless
                <form method="POST" action="{{ route('triage.imports.destroy', $import) }}"
                      data-confirm="{{ $import->deleteConfirmation() }}" data-confirm-verb="Delete">
                    @csrf @method('DELETE')
                    <button class="btn-ghost btn-sm text-rose-600 hover:bg-rose-50">
                        <x-icon name="trash" class="h-4 w-4" /> Delete
                    </button>
                </form>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-6 sm:py-8">
        {{-- Outside the poll region, so they stay open while the status updates. --}}
        @if ($pastedText !== null)
            <div class="app-container mb-4">
                <details class="card group">
                    <summary class="flex cursor-pointer list-none items-center gap-2 px-4 py-3 text-sm font-medium text-slate-700 hover:text-slate-900">
                        <x-icon name="clipboard-check" variant="outline" class="h-4 w-4 flex-none text-slate-400" />
                        <span class="flex-none">Show pasted text</span>
                        <span class="min-w-0 truncate font-normal text-slate-400 group-open:hidden">— {{ $import->preview }}</span>
                    </summary>
                    <pre class="max-h-80 overflow-auto whitespace-pre-wrap border-t border-slate-100 px-4 py-3 font-mono text-xs leading-relaxed text-slate-700">{{ $pastedText }}</pre>
                </details>
            </div>
        @elseif ($isImage)
            <div class="app-container mb-4">
                <details class="card group" open>
                    <summary class="flex cursor-pointer list-none items-center gap-2 px-4 py-3 text-sm font-medium text-slate-700 hover:text-slate-900">
                        <x-icon name="document-text" variant="outline" class="h-4 w-4 flex-none text-slate-400" />
                        The original image
                    </summary>
                    <div class="border-t border-slate-100 p-4">
                        <img src="{{ route('triage.imports.file', $import) }}" alt="The uploaded image: {{ $import->original_name }}" class="max-h-48 rounded-lg ring-1 ring-inset ring-slate-200">
                    </div>
                </details>
            </div>
        @endif

        <x-poll-region id="triage-import-{{ $import->id }}" class="app-container space-y-4" :running="$import->isRunning()" every="3000" :also="['import-status']">
            @if ($import->isRunning())
                <div class="card p-6 sm:p-8">
                    <div class="flex flex-col items-center text-center">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-600/15">
                            <x-icon name="sparkles" variant="outline" class="h-6 w-6 animate-pulse" />
                        </span>
                        <h2 class="mt-4 text-base font-semibold text-slate-900">{{ $import->status === TriageImport::STATUS_PROCESSING ? 'Claude is reading this' : 'Waiting to be read' }}</h2>
                        <p class="mt-1 max-w-md text-sm text-slate-500">
                            This usually takes under a minute; long documents take longer. You can leave this page. The import stays on <a href="{{ route('triage.index') }}" class="font-medium text-brand-700 hover:underline">Requests</a>.
                        </p>
                        @if ($import->isStuckInQueue())
                            <p class="mt-2 max-w-md text-sm text-warning-800">Still waiting for a background worker. If this doesn’t move, ask an admin to check the queue.</p>
                        @endif
                        @if ($import->chunks_total > 1)
                            <div class="mt-5 w-full max-w-sm">
                                <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-brand-500 transition-all duration-500" style="width: {{ $import->progressPercent() }}%"></div>
                                </div>
                                <p class="mt-2 text-xs tabular text-slate-500">Part {{ min($import->chunks_done + 1, $import->chunks_total) }} of {{ $import->chunks_total }}</p>
                            </div>
                        @endif
                    </div>
                </div>

            @elseif ($import->isFailed())
                <div class="card p-6 sm:p-8">
                    <div class="flex flex-col items-center text-center">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-danger-50 text-danger-600 ring-1 ring-inset ring-danger-600/15">
                            <x-icon name="exclamation-circle" variant="outline" class="h-6 w-6" />
                        </span>
                        <h2 class="mt-4 text-base font-semibold text-slate-900">This {{ $source }} couldn’t be read</h2>
                        <p class="mt-1 max-w-lg text-sm text-slate-600">{{ $import->error ?? 'Something went wrong. Please try again.' }}</p>
                        <div class="mt-5 flex flex-wrap justify-center gap-2">
                            @if ($canRetry)
                                <form method="POST" action="{{ route('triage.imports.retry', $import) }}">
                                    @csrf
                                    <button class="btn-primary btn-sm"><x-icon name="restore" class="h-4 w-4" /> Try again</button>
                                </form>
                            @endif
                            <a href="{{ route('triage.index') }}" class="btn-secondary btn-sm">Back to Requests</a>
                        </div>
                    </div>
                </div>

            @elseif ($rows === [])
                <div class="card">
                    <x-empty-state icon="inbox" title="No requests found"
                                   description="Claude didn’t find anything being asked for in this {{ $source }}. Try a clearer copy, or send the request with the form.">
                        <a href="{{ route('triage.index') }}" class="btn-primary btn-sm">Back to Requests</a>
                    </x-empty-state>
                </div>

            @else
                {{-- $refs, not $root: recount() also runs from each row's own x-data, where $root is that row. --}}
                <form method="POST" action="{{ route('triage.imports.confirm', $import) }}" x-ref="review"
                      x-data="{
                          count: 0,
                          busy: false,
                          boxes() { return [...this.$refs.review.querySelectorAll('input[data-include]')] },
                          recount() { this.count = this.boxes().filter(b => b.checked).length },
                          setAll(on) { this.boxes().forEach(b => { if (b.checked !== on) { b.checked = on; b.dispatchEvent(new Event('change')) } }); this.recount() },
                      }"
                      x-init="$nextTick(() => recount())" @submit="busy = true">
                    @csrf

                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">
                                {{ count($rows) }} {{ Str::plural('request', count($rows)) }} found
                                <span class="font-normal text-slate-500">· Claude can make mistakes, so check each one</span>
                            </h2>
                            @if ($sent->isNotEmpty())
                                <p class="mt-0.5 text-xs text-slate-500">{{ $sent->count() }} already sent from this import.</p>
                            @endif
                            @if ($import->proposals_dropped > 0)
                                <p class="mt-0.5 text-xs text-warning-800">Claude found {{ $import->proposals_dropped }} more than can be reviewed at once. Split the {{ $source }} to import the rest.</p>
                            @endif
                            @if (! $teamChoice && $teams->count() === 1)
                                <p class="mt-0.5 text-xs text-slate-500">These go to {{ $teams->first()->name }}.</p>
                            @endif
                        </div>
                        @if ($pending->isNotEmpty())
                            <div class="flex items-center gap-1 text-sm">
                                <button type="button" class="btn-ghost btn-sm" @click="setAll(true)">Select all</button>
                                <button type="button" class="btn-ghost btn-sm" @click="setAll(false)">Select none</button>
                            </div>
                        @endif
                    </div>

                    <div class="space-y-3">
                        @foreach ($rows as $i => $row)
                            @php
                                $key = "requests.$i";
                                $already = $row['already_sent'];
                                $unsure = $row['confidence'] < 0.5;
                                $on = $hasOld ? old("$key.include") === '1' : (! $already && ! $unsure);
                            @endphp

                            @if ($row['triage_item_id'])
                                <div class="card flex flex-wrap items-center gap-3 px-4 py-3">
                                    <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-success-50 text-success-600 ring-1 ring-inset ring-success-600/20">
                                        <x-icon name="check" variant="outline" class="h-3.5 w-3.5" />
                                    </span>
                                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-slate-800">{{ $row['title'] }}</span>
                                    <span class="badge badge-success">Sent</span>
                                </div>
                                @continue
                            @endif

                            <div class="card transition-opacity" x-data="{ on: @js($on) }" :class="on ? '' : 'opacity-60'">
                                <div class="flex flex-wrap items-start gap-3 border-b border-slate-100 px-4 py-3">
                                    <input type="hidden" name="{{ "requests[$i][include]" }}" value="0">
                                    <input type="checkbox" name="{{ "requests[$i][include]" }}" value="1" data-include
                                           class="field-check mt-2.5" x-model="on" @change="recount()" @checked($on)
                                           aria-label="Send {{ $row['title'] }}">

                                    <div class="min-w-0 flex-1 basis-64">
                                        <label for="req-{{ $i }}-title" class="sr-only">What’s needed?</label>
                                        <input id="req-{{ $i }}-title" name="{{ "requests[$i][title]" }}" type="text" maxlength="255"
                                               value="{{ old("$key.title", $row['title']) }}"
                                               class="field-input font-medium" @invalid("$key.title")>
                                        @error("$key.title")
                                            <p id="{{ $key }}.title-error" class="field-error"><x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="flex w-full flex-wrap items-center gap-1.5 sm:w-auto sm:pt-2">
                                        @if ($unsure)
                                            <span class="badge badge-warning" title="Claude wasn’t sure this is a request">Might not be a request</span>
                                        @endif
                                        @if ($already)
                                            <span class="badge badge-warning" title="One of your waiting requests has this title">Already sent?</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="grid gap-4 px-4 py-4 sm:grid-cols-3">
                                    <x-field label="Details" for="req-{{ $i }}-description" name="{{ $key }}.description" optional class="sm:col-span-3">
                                        <textarea id="req-{{ $i }}-description" name="{{ "requests[$i][description]" }}" rows="3" maxlength="5000"
                                                  class="field-textarea" @invalid("$key.description")>{{ old("$key.description", $row['description']) }}</textarea>
                                    </x-field>
                                    @if ($teamChoice)
                                        <x-field label="Team" for="req-{{ $i }}-team" name="{{ $key }}.team_id">
                                            <select id="req-{{ $i }}-team" name="{{ "requests[$i][team_id]" }}" class="field-select" @invalid("$key.team_id")>
                                                <option value="">Not sure</option>
                                                @foreach ($teams as $t)
                                                    <option value="{{ $t->id }}" @selected((int) old("$key.team_id", $row['team_id']) === $t->id)>{{ $t->name }}</option>
                                                @endforeach
                                            </select>
                                        </x-field>
                                    @endif
                                    <x-field label="Priority" for="req-{{ $i }}-priority" name="{{ $key }}.priority">
                                        <select id="req-{{ $i }}-priority" name="{{ "requests[$i][priority]" }}" class="field-select" @invalid("$key.priority")>
                                            <option value="">Not sure</option>
                                            @foreach (Task::PRIORITIES as $value => $label)
                                                <option value="{{ $value }}" @selected(old("$key.priority", $row['priority']) === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </x-field>
                                    <x-field label="Type" for="req-{{ $i }}-label" name="{{ $key }}.label_id">
                                        <select id="req-{{ $i }}-label" name="{{ "requests[$i][label_id]" }}" class="field-select" @invalid("$key.label_id")>
                                            <option value="">Not sure</option>
                                            @foreach ($labels as $l)
                                                <option value="{{ $l->id }}" @selected((int) old("$key.label_id", $row['label_id']) === $l->id)>{{ $l->name }}</option>
                                            @endforeach
                                        </select>
                                    </x-field>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($pending->isNotEmpty())
                        <div class="sticky bottom-4 z-10 mt-4">
                            <div class="card flex flex-wrap items-center justify-between gap-3 px-4 py-3 shadow-pop">
                                <p class="min-w-0 flex-1 basis-64 text-sm text-slate-600">
                                    <span class="font-semibold tabular text-slate-900" x-text="count">0</span> of {{ $pending->count() }} selected. Nothing is sent until you confirm.
                                    <span class="block text-xs text-slate-500">The original {{ $source }} stays linked to each request, so whoever decides can open it.</span>
                                </p>
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('triage.index') }}" class="btn-ghost btn-sm">Later</a>
                                    <button type="submit" class="btn-primary" :disabled="count === 0 || busy">
                                        <x-icon name="check" variant="outline" class="h-4 w-4" />
                                        <span x-text="busy ? 'Sending…' : `Send ${count} ${count === 1 ? 'request' : 'requests'}`">Send requests</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                </form>
            @endif
        </x-poll-region>
    </div>
</x-app-layout>
