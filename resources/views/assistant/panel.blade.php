{{-- The floating assistant's popup, fetched the first time it opens. Behaviour
     is `assistantChat` in resources/js/assistant.js; the look is in
     resources/css/assistant.css. A proposal is drawn as the record it would
     become, in a dashed outline, and turns solid when it's confirmed. --}}
@php
    $first = strtok((string) $user->name, ' ');
    $lead = $user->isLead();
    // `send` runs at once; the others put a start in the box for the person to finish.
    $suggestions = $lead
        ? [
            ['label' => 'How is my team doing today?', 'send' => true],
            ['label' => 'Who hasn’t filled in today’s tasksheet?', 'send' => true],
            ['label' => 'What’s unassigned? Help me hand it out.', 'send' => true],
            ['label' => 'Draft a status for ', 'hint' => 'Draft a release status', 'send' => false],
        ]
        : [
            ['label' => 'What’s on my plate today?', 'send' => true],
            ['label' => 'Plan my day on the tasksheet', 'send' => true],
            ['label' => 'Create a task to ', 'hint' => 'Create a task', 'send' => false],
            ['label' => 'Add an event ', 'hint' => 'Add an event', 'send' => false],
        ];
    $urls = [
        'conversation' => route('assistant.conversation'),
        'message' => route('assistant.message'),
        'continue' => route('assistant.continue'),
    ];
@endphp

<section id="assistant-panel" x-data="assistantChat({ urls: @js($urls), available: @js($available) })" data-reset-url="{{ route('assistant.reset') }}"
         x-show="panelOpen" x-cloak
         x-transition:enter="assistant-enter" x-transition:leave="assistant-leave"
         @assistant-focus.window="focusInput()"
         class="assistant-panel" role="dialog" aria-modal="false" aria-labelledby="assistant-title">

    <header class="assistant-header">
        <svg class="h-5 w-5 flex-none text-violet-300" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M11 3c.5 5.2 3.3 8 8.5 8.5-5.2.5-8 3.3-8.5 8.5-.5-5.2-3.3-8-8.5-8.500C7.7 11 10.5 8.2 11 3Z"/>
        </svg>
        <div class="min-w-0 flex-1">
            <h2 id="assistant-title" class="text-sm font-semibold leading-tight text-white">Assistant</h2>
            <p class="truncate text-xs leading-tight text-brand-200" x-text="working ? (live?.status || 'Working…') : 'Drafts changes. You confirm them.'">Drafts changes. You confirm them.</p>
        </div>
        <button type="button" class="assistant-icon-button" x-show="items.length > 0" @click="newChat()" :disabled="sending" title="New chat" aria-label="Start a new chat">
            <x-icon name="pencil-square" variant="outline" class="h-[18px] w-[18px]" />
        </button>
        <button type="button" class="assistant-icon-button" @click="close()" title="Close" aria-label="Close assistant">
            <x-icon name="x-mark" variant="outline" class="h-5 w-5" />
        </button>
    </header>

    <div x-ref="log" class="assistant-log" role="log" aria-live="polite" aria-label="Conversation" @scroll.passive="trackScroll()" @click="onLogClick($event)">
        @unless ($available)
            <div class="m-auto max-w-[17rem] text-center" x-show="items.length === 0">
                <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                    <x-icon name="sparkles" variant="outline" class="h-6 w-6" />
                </span>
                <h3 class="mt-3 text-sm font-semibold text-slate-900">The assistant is off right now</h3>
                <p class="mt-1 text-sm text-slate-500">An administrator has switched it off, or AI isn’t set up yet.</p>
                @if ($user->isAdmin())
                    <a href="{{ route('ai.usage') }}" class="mt-3 inline-block text-sm font-medium text-brand-700 hover:text-brand-800">Open AI settings</a>
                @endif
            </div>
        @else
            <div x-show="loaded && items.length === 0 && !live" class="assistant-empty">
                <h3 class="assistant-greeting">Hi {{ $first }}. What needs doing?</h3>
                <p class="mt-1 text-sm leading-relaxed text-slate-500">
                    Ask about your work, or tell me what to add. I draft it here, and nothing changes until you confirm.
                </p>
                <ul class="mt-4 space-y-1.5">
                    @foreach ($suggestions as $suggestion)
                        <li>
                            <button type="button" class="assistant-suggestion"
                                    @click="{{ $suggestion['send'] ? 'send('.Js::from($suggestion['label']).')' : 'start('.Js::from($suggestion['label']).')' }}">
                                <x-icon :name="$suggestion['send'] ? 'chat-bubbles' : 'plus'" variant="outline" class="h-4 w-4 flex-none text-slate-400" />
                                <span>{{ $suggestion['hint'] ?? $suggestion['label'] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endunless

        <div x-show="!loaded && {{ $available ? 'true' : 'false' }}" class="m-auto text-sm text-slate-400" role="status">Loading your conversation…</div>

        <template x-for="item in items" :key="item.key">
            <div>
                <template x-if="item.type === 'user'">
                    <div class="flex justify-end"><p class="assistant-you" x-text="item.text"></p></div>
                </template>
                <template x-if="item.type === 'assistant'">
                    <div class="assistant-answer" x-html="item.html"></div>
                </template>
                <template x-if="item.type === 'action'">
                    <div x-data="{ action: item.action }" x-effect="action = item.action">
                        @include('assistant.draft')
                    </div>
                </template>
            </div>
        </template>

        {{-- The turn in progress. --}}
        <template x-if="live">
            <div class="space-y-3">
                <template x-if="live.text">
                    <div class="assistant-answer"><p class="whitespace-pre-line" x-text="live.text"></p></div>
                </template>
                <template x-for="action in live.actions" :key="action.id">
                    <div>
                        @include('assistant.draft')
                    </div>
                </template>
                <p x-show="sending" class="assistant-status" role="status">
                    <svg class="assistant-status-spark" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M11 3c.5 5.2 3.3 8 8.5 8.5-5.2.5-8 3.3-8.5 8.5-.5-5.2-3.3-8-8.5-8.500C7.7 11 10.5 8.2 11 3Z"/>
                    </svg>
                    <span x-text="live.status + '…'"></span>
                </p>
            </div>
        </template>

        <div x-show="error" x-cloak class="assistant-error" role="alert">
            <x-icon name="exclamation-circle" variant="outline" class="mt-0.5 h-4 w-4 flex-none" />
            <p class="flex-1" x-text="error"></p>
            <button type="button" x-show="retryable" class="flex-none font-semibold underline underline-offset-2" @click="retry()">Try again</button>
        </div>

        <div x-show="pendingCount > 1" x-cloak class="flex justify-end">
            <button type="button" class="btn-primary btn-sm" :disabled="sending || resolving" @click="confirmAll()" x-text="`Confirm all ${pendingCount}`"></button>
        </div>
    </div>

    @if ($available)
        <form class="assistant-composer" @submit.prevent="send()">
            <label for="assistant-input" class="sr-only">Message the assistant</label>
            <textarea id="assistant-input" x-ref="input" x-model="draft" rows="1" maxlength="2000"
                      @input="resize()" @keydown.enter="onEnter($event)"
                      placeholder="Ask, or tell me what to add…" class="assistant-input"></textarea>
            <button type="submit" class="assistant-send" :disabled="sending || draft.trim() === ''" aria-label="Send">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M6 11l6-6 6 6"/></svg>
            </button>
        </form>
        <p class="assistant-footnote">AI can make mistakes. Check a draft before you confirm it.</p>
    @else
        <p x-show="items.length > 0" class="assistant-footnote border-t border-slate-200 !py-3">The assistant is off right now, so this chat can’t continue. Drafts here can still be confirmed.</p>
    @endif
</section>
