{{-- Start a release from a template. Requires $release and $templates. --}}
@if ($templates->isNotEmpty())
    <form method="POST" action="{{ route('releases.apply-template', $release) }}" class="mb-3 flex flex-wrap items-center gap-2">
        @csrf
        <label for="apply-template" class="text-xs font-medium text-slate-500">Add tasks from a template</label>
        <select id="apply-template" name="template_id" required data-native class="field-control field-sm w-auto pr-8">
            @foreach ($templates as $template)
                <option value="{{ $template->id }}">{{ $template->name }} ({{ $template->tasks->count() }})</option>
            @endforeach
        </select>
        <button class="btn-secondary btn-sm">Add tasks</button>
        <a href="{{ route('release-templates.index') }}" class="text-xs font-medium text-brand-700 hover:text-brand-800">Manage</a>
    </form>
@else
    <p class="mb-3 text-xs text-slate-500"><a href="{{ route('release-templates.index') }}" class="font-medium text-brand-700 hover:text-brand-800">Set up a release template</a> to add a standard task set in one click.</p>
@endif
