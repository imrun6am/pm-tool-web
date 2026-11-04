{{-- The floating assistant's button. Kept across soft navigation, so its state
     and any reply in progress survive page changes. The panel's HTML (assistant/
     panel), script and styles load the first time it's needed: behaviour in
     resources/js/assistant-launcher.js and resources/js/assistant.js. --}}
@auth
<div id="ai-assistant" data-turbo-permanent
     x-data="assistantLauncher({ panelUrl: @js(route('assistant.panel')) })"
     @keydown.window="onKeydown($event)">
    <div x-ref="host"></div>

    {{-- Before the panel arrives, or if it can't. --}}
    <div x-show="panelOpen && !panelReady" x-cloak class="assistant-shell" role="status">
        <p x-show="!panelFailed" class="text-sm text-slate-500">Opening the assistant…</p>
        <div x-show="panelFailed" class="space-y-3 text-center">
            <p class="text-sm text-slate-600">The assistant didn’t load. Check your connection.</p>
            <button type="button" class="btn-secondary btn-sm" @click="retry()">Load it again</button>
        </div>
    </div>

    <button type="button" x-ref="launcher" class="assistant-launcher"
            :class="{ 'is-open': panelOpen, 'is-working': working }"
            @click="toggle()" @pointerenter.once="preload()" @focus.once="preload()"
            :aria-expanded="panelOpen.toString()" aria-expanded="false" aria-controls="assistant-panel"
            :aria-label="panelOpen ? 'Close assistant' : (attention ? 'Open assistant, something needs your attention' : 'Open assistant')" aria-label="Open assistant"
            title="Assistant (A)">
        <span class="assistant-ring" aria-hidden="true"></span>
        <svg class="assistant-spark" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M11 3c.5 5.2 3.3 8 8.5 8.5-5.2.5-8 3.3-8.5 8.5-.5-5.2-3.3-8-8.5-8.500C7.7 11 10.5 8.2 11 3Z" fill="currentColor"/>
            <path d="M18.5 2.500c.15 1.7 1.1 2.650 2.8 2.8-1.7.15-2.650 1.1-2.8 2.8-.15-1.7-1.1-2.650-2.8-2.8 1.7-.15 2.650-1.1 2.8-2.800Z" fill="currentColor" opacity=".65"/>
        </svg>
        <svg class="assistant-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
            <path d="M6 6l12 12M18 6 6 18"/>
        </svg>
        <span x-show="attention && !panelOpen" x-cloak class="assistant-badge" aria-hidden="true"></span>
    </button>
</div>
@endauth
