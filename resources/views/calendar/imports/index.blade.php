<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Import events"
                       description="Upload a meeting invite, schedule, agenda or release plan, or paste an email or message. Claude finds the events in the background; you review them before anything is added."
                       :breadcrumbs="['Calendar' => route('calendar.index'), 'Import events' => null]" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-4">
            @if ($apiKeyMissing)
                <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <x-icon name="exclamation-circle" variant="outline" class="mt-0.5 h-5 w-5 flex-none" />
                    <p>Add <code class="font-mono">ANTHROPIC_API_KEY</code> to <code class="font-mono">.env</code> to read PDFs, images and documents. <code class="font-mono">.ics</code> calendar files import without it.</p>
                </div>
            @endif

            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
                {{-- Two ways in: upload a file, or paste text --}}
                @php $maxChars = \App\Http\Requests\EventImportTextRequest::MAX_CHARS; @endphp
                <div class="card overflow-hidden"
                     x-data="{ tab: @js($errors->has('text') || filled(old('text')) ? 'paste' : 'file'), tz: '' }"
                     x-init="tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''">
                    <div class="flex gap-1 border-b border-slate-200 px-3 pt-2" role="tablist" aria-label="How to import">
                        @foreach (['file' => ['document-text', 'Upload a file'], 'paste' => ['clipboard-check', 'Paste text']] as $key => [$icon, $label])
                            <button type="button" role="tab" id="import-tab-{{ $key }}" aria-controls="import-panel-{{ $key }}"
                                    :aria-selected="(tab === '{{ $key }}').toString()" :tabindex="tab === '{{ $key }}' ? 0 : -1"
                                    @click="tab = '{{ $key }}'"
                                    @keydown.arrow-right.prevent="tab = tab === 'file' ? 'paste' : 'file'; $nextTick(() => document.getElementById('import-tab-' + tab).focus())"
                                    @keydown.arrow-left.prevent="tab = tab === 'file' ? 'paste' : 'file'; $nextTick(() => document.getElementById('import-tab-' + tab).focus())"
                                    class="-mb-px inline-flex items-center gap-1.5 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors"
                                    :class="tab === '{{ $key }}' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800'">
                                <x-icon :name="$icon" variant="outline" class="h-4 w-4" />{{ $label }}
                            </button>
                        @endforeach
                    </div>

                    {{-- Upload a file --}}
                    <form method="POST" action="{{ route('calendar.imports.store') }}" enctype="multipart/form-data"
                          id="import-panel-file" role="tabpanel" aria-labelledby="import-tab-file" x-show="tab === 'file'"
                          class="p-4 sm:p-5"
                          x-data="{
                              file: null,
                              dragging: false,
                              busy: false,
                              pick(list) {
                                  if (! list || ! list.length) return;
                                  const dt = new DataTransfer();
                                  dt.items.add(list[0]);
                                  this.$refs.input.files = dt.files;
                                  this.file = list[0];
                              },
                              clear() { this.file = null; this.$refs.input.value = ''; },
                              size(bytes) { return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB'; },
                          }"
                          @submit="busy = true">
                        @csrf
                        <input type="hidden" name="timezone" :value="tz">

                        <label for="document"
                               class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-6 py-10 text-center transition-colors"
                               :class="dragging ? 'border-brand-400 bg-brand-50/60' : 'border-slate-200 bg-slate-50/50 hover:border-slate-300 hover:bg-slate-50'"
                               @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false"
                               @drop.prevent="dragging = false; pick($event.dataTransfer.files)">
                            <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-white text-brand-600 shadow-sm ring-1 ring-inset ring-slate-200">
                                <x-icon name="document-text" variant="outline" class="h-6 w-6" />
                            </span>
                            <template x-if="! file">
                                <div>
                                    <p class="mt-4 text-sm font-semibold text-slate-900">Drop a file here, or <span class="text-brand-700 underline decoration-brand-300 underline-offset-2">browse</span></p>
                                    <p class="mt-1 text-xs text-slate-500">PDF, image, .ics, Word, Excel, CSV or text · up to 20 MB (images 5 MB)</p>
                                </div>
                            </template>
                            <template x-if="file">
                                <div>
                                    <p class="mt-4 max-w-md truncate text-sm font-semibold text-slate-900" x-text="file.name"></p>
                                    <p class="mt-1 text-xs text-slate-500"><span x-text="size(file.size)"></span> · click to choose another file</p>
                                </div>
                            </template>
                            <input id="document" name="document" type="file" x-ref="input" class="sr-only" required
                                   accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.ics,.docx,.xlsx,.csv,.txt,.md"
                                   @change="file = $event.target.files[0] || null" @invalid('document')>
                        </label>
                        @error('document')
                            <p id="document-error" class="field-error">
                                <x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $message }}
                            </p>
                        @enderror

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                            <p class="text-xs text-slate-500">
                                Times are read in your timezone (<span x-text="tz || @js(config('app.timezone'))"></span>). Nothing is added until you confirm.
                            </p>
                            <div class="flex items-center gap-2">
                                <button type="button" class="btn-ghost btn-sm" x-show="file" x-cloak @click="clear()">Remove</button>
                                <button type="submit" class="btn-primary" :disabled="! file || busy">
                                    <x-icon name="sparkles" variant="outline" class="h-4 w-4" />
                                    <span x-text="busy ? 'Uploading…' : 'Upload and find events'">Upload and find events</span>
                                </button>
                            </div>
                        </div>
                    </form>

                    {{-- Paste text --}}
                    <form method="POST" action="{{ route('calendar.imports.text') }}"
                          id="import-panel-paste" role="tabpanel" aria-labelledby="import-tab-paste" x-show="tab === 'paste'" x-cloak
                          class="p-4 sm:p-5"
                          x-data="{ text: @js(old('text', '')), busy: false, max: {{ $maxChars }} }"
                          @submit="busy = true">
                        @csrf
                        <input type="hidden" name="timezone" :value="tz">

                        <x-field label="Text with dates" for="import-text" name="text"
                                 hint="An email or invite, a chat message, meeting minutes, an agenda or a schedule table. Calendar (.ics) text works too.">
                            <textarea id="import-text" name="text" rows="10" x-model="text" maxlength="{{ $maxChars }}" required
                                      class="field-textarea font-mono text-[13px] leading-relaxed"
                                      placeholder="e.g. “Kick-off with Sara and Omar on Monday 9 Nov at 10:00 in Room 4. Code freeze 20 Nov, go-live 27 Nov.”"
                                      @invalid('text')></textarea>
                        </x-field>

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                            <p class="text-xs tabular" :class="text.length > max * 0.95 ? 'text-warning-800' : 'text-slate-500'" aria-live="polite">
                                <span x-text="text.length.toLocaleString()">0</span> / {{ number_format($maxChars) }} characters ·
                                times read in <span x-text="tz || @js(config('app.timezone'))"></span>
                            </p>
                            <div class="flex items-center gap-2">
                                <button type="button" class="btn-ghost btn-sm" x-show="text.length" x-cloak @click="text = ''; $nextTick(() => $el.form.elements.text.focus())">Clear</button>
                                <button type="submit" class="btn-primary" :disabled="! text.trim() || busy">
                                    <x-icon name="sparkles" variant="outline" class="h-4 w-4" />
                                    <span x-text="busy ? 'Sending…' : 'Find events'">Find events</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                {{-- How it works --}}
                <div class="card p-4 sm:p-5">
                    <h2 class="text-sm font-semibold text-slate-900">How it works</h2>
                    <ol class="mt-3 space-y-3 text-sm text-slate-600">
                        @foreach ([
                            ['Upload or paste', 'What you add is stored privately; only you (and admins) can see it.'],
                            ['Extract', 'Claude reads it in the background. Calendar (.ics) data is read directly.'],
                            ['Review', 'Edit titles, times, attendees and releases; untick anything you don’t want.'],
                            ['Confirm', 'The selected events are added to the calendar.'],
                        ] as $i => [$step, $text])
                            <li class="flex gap-3">
                                <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700 ring-1 ring-inset ring-brand-600/15">{{ $i + 1 }}</span>
                                <span><span class="font-medium text-slate-800">{{ $step }}.</span> {{ $text }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>

            {{-- History: updates in place while an import runs (no page reload) --}}
            @php $running = $imports->contains(fn ($i) => $i->isRunning()); @endphp
            <x-poll-region id="calendar-imports" class="card overflow-x-auto" :running="$running" every="3000"
                           done-message="Import finished — review the events Claude found.">
                <div class="card-header">
                    <h2 class="card-title">Your imports</h2>
                </div>
                @if ($imports->isEmpty())
                    <x-empty-state icon="calendar" title="No imports yet"
                                   description="Upload a schedule, invite or release plan, or paste an email, to create events from it." />
                @else
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500">Source</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500">Events</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500">Added</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($imports as $import)
                                <tr>
                                    <td class="max-w-xs px-4 py-3">
                                        <a href="{{ route('calendar.imports.show', $import) }}" class="flex items-center gap-2 font-medium text-slate-800 hover:text-brand-700">
                                            @if ($import->isPasted())
                                                <x-icon name="clipboard-check" variant="outline" class="h-4 w-4 flex-none text-slate-400" />
                                            @else
                                                <span class="flex-none rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] uppercase text-slate-500">{{ $import->extension }}</span>
                                            @endif
                                            <span class="truncate">{{ $import->displayName() }}</span>
                                        </a>
                                        @if ($import->isPasted() && $import->preview)
                                            <span class="block truncate text-xs text-slate-500" title="{{ $import->preview }}">“{{ $import->preview }}”</span>
                                        @endif
                                        <span class="text-xs text-slate-400">{{ $import->sizeLabel() }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="badge {{ $import->statusBadge() }}">
                                            {{ $import->statusLabel() }}@if ($import->isRunning() && $import->chunks_total > 1) · {{ $import->progressPercent() }}%@endif
                                        </span>
                                        @if ($import->error)
                                            <p class="mt-1 max-w-xs text-xs text-danger-700">{{ $import->error }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 tabular text-slate-600">
                                        @if ($import->isReviewable())
                                            {{ $import->events_created }} added
                                            <span class="block text-xs text-slate-400">of {{ $import->proposalCount() }} found</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-slate-500">{{ $import->created_at->diffForHumans() }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            @if ($import->status === \App\Models\EventImport::STATUS_NEEDS_REVIEW)
                                                <a href="{{ route('calendar.imports.show', $import) }}" class="btn-primary btn-sm">Review</a>
                                            @elseif ($import->isReviewable())
                                                <a href="{{ route('calendar.imports.show', $import) }}" class="btn-secondary btn-sm">Re-review</a>
                                            @elseif ($import->isFailed())
                                                <form method="POST" action="{{ route('calendar.imports.retry', $import) }}">
                                                    @csrf
                                                    <button class="btn-secondary btn-sm">Try again</button>
                                                </form>
                                            @else
                                                <a href="{{ route('calendar.imports.show', $import) }}" class="btn-ghost btn-sm">View</a>
                                            @endif
                                            <form method="POST" action="{{ route('calendar.imports.destroy', $import) }}"
                                                  data-confirm="Delete this import and its {{ $import->isPasted() ? 'pasted text' : 'uploaded file' }}? Events already added stay on the calendar." data-confirm-verb="Delete">
                                                @csrf @method('DELETE')
                                                <button class="btn-ghost btn-sm !px-2 text-rose-600 hover:bg-rose-50" aria-label="Delete import">
                                                    <x-icon name="trash" class="h-4 w-4" />
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-poll-region>
            <p class="text-xs text-slate-400">
                Files and pasted text are read by Claude, which can make mistakes; check each event before confirming.
                The queue worker must be running (<code class="font-mono">php artisan queue:work</code>).
            </p>
        </div>
    </div>
</x-app-layout>
