{{-- Creating requests from a file, screenshot or text. Outside the page's live region, so accepting a request doesn't drop a chosen file or half-pasted text. --}}
@if ($importAvailable)
    @php
        $maxChars = \App\Http\Requests\TriageImportTextRequest::MAX_CHARS;
        $accept = collect(\App\Services\TriageImportService::EXTENSIONS)->map(fn ($e) => '.'.$e)->implode(',');
    @endphp
    <section class="card overflow-hidden" aria-labelledby="import-title"
             x-data="requestImport({
                 extensions: @js(\App\Services\TriageImportService::EXTENSIONS),
                 imageExtensions: @js(\App\Services\Calendar\EventImportService::IMAGE_EXTENSIONS),
                 maxBytes: {{ \App\Services\TriageImportService::MAX_FILE_KB * 1024 }},
                 maxImageBytes: {{ \App\Services\TriageImportService::MAX_IMAGE_KB * 1024 }},
                 text: @js(old('text', '')),
                 showText: @js($errors->has('text') || filled(old('text'))),
             })"
             @paste.window="onPaste($event)"
             @dragenter.window="onDragEnter($event)" @dragover.window="onDragOver($event)"
             @dragleave.window="onDragLeave($event)" @drop.window="onDrop($event)">
        <div class="fixed inset-0 z-40 p-4 pointer-events-none" x-show="dragging" x-cloak aria-hidden="true">
            <div class="flex h-full items-center justify-center rounded-xl border-2 border-dashed border-brand-400 bg-brand-50/80">
                <p class="text-sm font-semibold text-brand-800">Drop the file to read it with Claude</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-5">
            <span class="flex h-9 w-9 flex-none items-center justify-center rounded-xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-600/15">
                <x-icon name="sparkles" variant="outline" class="h-5 w-5" />
            </span>
            <div class="min-w-0 flex-1 basis-64">
                <h2 id="import-title" class="card-title">Create requests with Claude</h2>
                <p class="mt-0.5 text-xs text-slate-500">Drop a file or paste a screenshot or message anywhere on this page. You check what Claude finds before anything is sent.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-secondary btn-sm" @click="$refs.input.click()">
                    <x-icon name="document-text" variant="outline" class="h-4 w-4" /> Upload a file
                </button>
                <button type="button" class="btn-secondary btn-sm" @click="openText()" :aria-expanded="showText.toString()" aria-controls="import-text-form">
                    <x-icon name="clipboard-check" variant="outline" class="h-4 w-4" /> Paste text
                </button>
            </div>
        </div>

        <p x-show="error" x-cloak x-text="error" class="field-error border-t border-slate-100 px-4 py-3 sm:px-5" role="alert"></p>
        @error('document')
            <p class="field-error border-t border-slate-100 px-4 py-3 sm:px-5" role="alert" x-show="! file">
                <x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $message }}
            </p>
        @enderror

        <form method="POST" action="{{ route('triage.imports.store') }}" enctype="multipart/form-data" @submit="busy = true">
            @csrf
            <input type="file" name="document" x-ref="input" class="sr-only" tabindex="-1" aria-label="File to import" accept="{{ $accept }}" @change="take($event.target.files)">

            <div x-show="file" x-cloak class="flex flex-wrap items-center gap-3 border-t border-slate-100 bg-slate-50/50 px-4 py-3 sm:px-5">
                <template x-if="previewUrl">
                    <img :src="previewUrl" alt="" class="h-12 w-12 flex-none rounded-lg object-cover ring-1 ring-inset ring-slate-200">
                </template>
                <template x-if="! previewUrl">
                    <span class="flex-none rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] uppercase text-slate-500" x-text="extension"></span>
                </template>
                <div class="min-w-0 flex-1 basis-40">
                    <p class="truncate text-sm font-medium text-slate-800" x-text="file?.name"></p>
                    <p class="text-xs text-slate-500" x-text="size(file?.size)"></p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="btn-ghost btn-sm" @click="clearFile()">Remove</button>
                    <button type="submit" class="btn-primary btn-sm" x-ref="send" :disabled="busy">
                        <x-icon name="sparkles" variant="outline" class="h-4 w-4" />
                        <span x-text="busy ? 'Uploading…' : 'Read with Claude'">Read with Claude</span>
                    </button>
                </div>
            </div>
        </form>

        <form id="import-text-form" method="POST" action="{{ route('triage.imports.text') }}" x-show="showText" x-cloak
              class="border-t border-slate-100 px-4 py-3 sm:px-5" @submit="busy = true">
            @csrf
            <label for="import-text" class="field-label !mt-0">An email, chat message, meeting notes or a list of asks</label>
            <textarea id="import-text" name="text" rows="6" x-ref="text" x-model="text" maxlength="{{ $maxChars }}" required
                      class="field-control" @invalid('text')></textarea>
            @error('text')
                <p class="field-error" role="alert"><x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $message }}</p>
            @enderror
            <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs tabular text-slate-500"><span x-text="text.length.toLocaleString()">0</span> / {{ number_format($maxChars) }} characters</p>
                <div class="flex items-center gap-2">
                    <button type="button" class="btn-ghost btn-sm" @click="closeText()">Cancel</button>
                    <button type="submit" class="btn-primary btn-sm" :disabled="! text.trim() || busy">
                        <x-icon name="sparkles" variant="outline" class="h-4 w-4" />
                        <span x-text="busy ? 'Sending…' : 'Read with Claude'">Read with Claude</span>
                    </button>
                </div>
            </div>
        </form>
    </section>
@endif

{{-- What still needs this person. Updates in place while something is being read. --}}
@if ($imports->isNotEmpty())
    <x-poll-region id="triage-imports" class="card" :running="$imports->contains(fn ($i) => $i->isRunning())" every="3000"
                   done-message="Claude has finished reading. Review what it found.">
        <div class="card-header"><h2 class="card-title">Your imports</h2></div>
        @foreach ($imports as $import)
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 px-5 py-3 last:border-b-0">
                <div class="min-w-0 flex-1 basis-56">
                    <a href="{{ route('triage.imports.show', $import) }}" class="flex items-center gap-2 text-sm font-medium text-slate-800 hover:text-brand-700">
                        @if ($import->isPasted())
                            <x-icon name="clipboard-check" variant="outline" class="h-4 w-4 flex-none text-slate-400" />
                        @else
                            <span class="flex-none rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] uppercase text-slate-500">{{ $import->extension }}</span>
                        @endif
                        <span class="truncate">{{ $import->displayName() }}</span>
                    </a>
                    @if ($import->preview)
                        <p class="mt-0.5 truncate text-xs text-slate-500" title="{{ $import->preview }}">“{{ $import->preview }}”</p>
                    @endif
                    @if ($import->isFailed() && $import->error)
                        <p class="mt-1 text-xs text-danger-700">{{ $import->error }}</p>
                    @elseif ($import->isStuckInQueue())
                        <p class="mt-1 text-xs text-warning-800">Still waiting for a background worker. If this doesn’t move, ask an admin to check the queue.</p>
                    @endif
                </div>
                <span class="badge {{ $import->statusBadge() }}">
                    {{ $import->statusLabel() }}@if ($import->isRunning() && $import->chunks_total > 1) · {{ $import->progressPercent() }}%@endif
                    @if ($import->status === \App\Models\TriageImport::STATUS_NEEDS_REVIEW) · {{ $import->proposalCount() }} found @endif
                </span>
                <div class="flex items-center gap-1">
                    @if ($import->status === \App\Models\TriageImport::STATUS_NEEDS_REVIEW)
                        <a href="{{ route('triage.imports.show', $import) }}" class="btn-primary btn-sm">Review</a>
                    @elseif ($import->isFailed() && $importAvailable)
                        <form method="POST" action="{{ route('triage.imports.retry', $import) }}">
                            @csrf
                            <button class="btn-secondary btn-sm">Try again</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('triage.imports.destroy', $import) }}"
                          data-confirm="{{ $import->deleteConfirmation() }}" data-confirm-verb="Delete">
                        @csrf @method('DELETE')
                        <button class="btn-ghost btn-sm !px-2 text-rose-600 hover:bg-rose-50" aria-label="Delete import of {{ $import->displayName() }}">
                            <x-icon name="trash" class="h-4 w-4" />
                        </button>
                    </form>
                </div>
            </div>
        @endforeach
    </x-poll-region>
@endif
