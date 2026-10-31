@props(['input', 'kind' => 'note', 'titleField' => null, 'typeField' => null])
{{--
    "Enhance with Claude" for a Trix editor: polishes and organizes its content
    in place (nothing is saved). `input` is the editor's hidden input id; for
    meetings, title-field / type-field name inputs sent along as context.
      <x-ai-enhance input="meeting-note-body" kind="meeting" title-field="title" type-field="type" />
--}}
<div x-data="aiEnhance({ input: @js($input), kind: @js($kind), url: @js(route('ai.enhance')), titleField: @js($titleField), typeField: @js($typeField) })"
     {{ $attributes->merge(['class' => 'mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5']) }}>
    <button type="button" @click="run()" :disabled="busy || empty"
            class="btn btn-sm border border-violet-600/20 bg-violet-50 text-violet-700 hover:border-violet-600/30 hover:bg-violet-100 focus-visible:ring-violet-500/50"
            :title="empty ? 'Write something first' : 'Polish and organize this text with Claude — nothing is saved until you save'">
        <svg x-show="busy" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3" />
            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
        </svg>
        <x-icon name="sparkles" variant="outline" class="h-4 w-4" x-show="!busy" />
        <span x-text="busy ? 'Enhancing…' : 'Enhance with Claude'">Enhance with Claude</span>
    </button>

    <span x-show="done && !busy" x-cloak class="flex items-center gap-1.5 text-xs text-slate-600" role="status">
        <x-icon name="check" variant="outline" class="h-4 w-4 text-success-600" />
        Enhanced — review it, then save.
        <button type="button" @click="undo()" class="font-semibold text-brand-700 hover:underline">Undo</button>
    </span>

    <p x-show="error" x-cloak role="alert" class="flex items-center gap-1.5 text-xs text-danger-600">
        <x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />
        <span x-text="error"></span>
    </p>
</div>
