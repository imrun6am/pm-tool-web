{{--
    One event in full, in a drawer on the right. The event list sets `log` to
    the row's details (see _events); null closes it. Rendered at <body>, like
    the task panel, to rise above the app header.
--}}
<div x-data="{
        filterUrl(key, value) {
            const url = new URL(window.location.href);
            url.searchParams.set(key, value);
            url.searchParams.delete('page');
            return url.toString();
        },
        copy(text) { navigator.clipboard?.writeText(text); },
     }">
    <template x-teleport="body">
        <div class="relative z-[55]">
            <div x-show="log" x-transition.opacity style="display: none;" class="fixed inset-0 bg-slate-900/40 backdrop-blur-[2px]" @click="log = null" aria-hidden="true"></div>

            <div x-show="log" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="claude-event-title"
                 x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
                 x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
                 x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                 x-effect="if (log) $nextTick(() => $refs.close?.focus())"
                 class="fixed inset-y-0 right-0 flex w-full max-w-xl flex-col bg-white shadow-pop">
                <template x-if="log">
                    <div class="flex min-h-0 flex-1 flex-col">
                        <div class="flex flex-none items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="badge" :class="log.tone" x-text="log.label"></span>
                                    <span class="text-xs text-slate-500" x-show="log.detail" x-text="log.event === 'PostModelSwitch' ? 'from ' + log.detail : log.detail"></span>
                                </div>
                                <h2 id="claude-event-title" class="mt-1.5 text-base font-semibold text-slate-900" x-text="log.when"></h2>
                                <p class="text-xs text-slate-500" x-text="log.ago"></p>
                            </div>
                            <button type="button" x-ref="close" @click="log = null" class="btn-ghost btn-sm btn-icon flex-none" aria-label="Close event details">
                                <x-icon name="x-mark" variant="outline" class="h-5 w-5" />
                            </button>
                        </div>

                        <div class="min-h-0 flex-1 space-y-6 overflow-y-auto px-5 py-5">
                            {{-- What was asked or done --}}
                            <template x-if="log.prompt_excerpt">
                                <section>
                                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Prompt</h3>
                                    <p class="mt-2 whitespace-pre-wrap break-words rounded-lg bg-slate-50 p-3 text-sm leading-relaxed text-slate-800 ring-1 ring-inset ring-slate-200" x-text="log.prompt_excerpt"></p>
                                    <p class="mt-1 text-xs text-slate-500">Only the start of each prompt is kept ({{ number_format(min(\App\Models\ClaudeActivityLog::PROMPT_EXCERPT_MAX, max(0, (int) config('claude_monitor.prompt_excerpt_length')))) }} characters at most).</p>
                                </section>
                            </template>
                            <template x-if="log.url">
                                <section>
                                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Page</h3>
                                    <p class="mt-2 break-all font-mono text-sm text-slate-800" x-text="log.url"></p>
                                </section>
                            </template>

                            <section>
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Details</h3>
                                <dl class="mt-2 divide-y divide-slate-100 text-sm">
                                    @foreach ([
                                        ['Model', 'model', 'model'],
                                        ['Computer', 'hostname', null],
                                        ['OS user', 'os_user', 'os_user'],
                                        ['OS', 'platform', null],
                                        ['Entry point', 'entrypoint', 'entrypoint'],
                                        ['IP address', 'ip', 'ip'],
                                        ['MAC address', 'mac_address', 'mac'],
                                        ['Session', 'session_id', 'session'],
                                        ['Device id', 'device_id', null],
                                        ['Folder', 'cwd', null],
                                        ['Transcript', 'transcript_path', null],
                                    ] as [$label, $field, $filter])
                                        <div class="grid grid-cols-3 gap-3 py-2" x-show="log.{{ $field }}">
                                            <dt class="text-slate-500">{{ $label }}</dt>
                                            <dd class="col-span-2 flex min-w-0 items-start justify-between gap-2">
                                                <span class="break-all font-mono text-slate-800" x-text="log.{{ $field }}"></span>
                                                <span class="flex flex-none items-center gap-1">
                                                    @if ($filter)
                                                        <a :href="filterUrl('{{ $filter }}', log.{{ $field }})" class="text-xs font-medium text-brand-700 hover:underline">Filter</a>
                                                    @endif
                                                    <button type="button" class="text-slate-400 hover:text-slate-700" @click="copy(log.{{ $field }})" aria-label="Copy {{ strtolower($label) }}" title="Copy">
                                                        <x-icon name="clipboard-check" variant="outline" class="h-4 w-4" />
                                                    </button>
                                                </span>
                                            </dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </section>
                        </div>

                        <div class="flex flex-none items-center justify-between gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
                            <span class="text-xs text-slate-500" x-text="'Event #' + log.id"></span>
                            <form method="POST" :action="'{{ url('claude-activity') }}/' + log.id"
                                  data-confirm-title="Delete this event?" data-confirm-verb="Delete" data-confirm="This event will be deleted. This can't be undone.">
                                @csrf @method('DELETE')
                                <button class="btn-ghost btn-sm text-danger-600">
                                    <x-icon name="trash" class="h-4 w-4" />
                                    Delete event
                                </button>
                            </form>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>
