@php use App\Models\PerformanceCompetency; @endphp

<div class="space-y-5">
    <div>
        <label for="name" class="field-label">Name</label>
        <input id="name" name="name" type="text" value="{{ old('name', $competency->name) }}" required
               class="field-input w-full" placeholder="e.g. Code Quality">
        @error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="description" class="field-label">Description <span class="font-normal text-slate-400">(optional)</span></label>
        <textarea id="description" name="description" rows="2" class="field-input w-full"
                  placeholder="What good looks like for this competency.">{{ old('description', $competency->description) }}</textarea>
        @error('description')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <label for="category" class="field-label">Category</label>
            <select id="category" name="category" class="field-input w-full">
                @foreach (PerformanceCompetency::CATEGORIES as $val => $label)
                    <option value="{{ $val }}" @selected(old('category', $competency->category) === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="role_scope" class="field-label">Applies to</label>
            <select id="role_scope" name="role_scope" class="field-input w-full">
                @foreach (PerformanceCompetency::ROLE_SCOPES as $val => $label)
                    <option value="{{ $val }}" @selected(old('role_scope', $competency->role_scope) === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="cadence" class="field-label">Cadence</label>
            <select id="cadence" name="cadence" class="field-input w-full">
                @foreach (PerformanceCompetency::CADENCES as $val => $label)
                    <option value="{{ $val }}" @selected(old('cadence', $competency->cadence) === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <label for="weight" class="field-label">Weight</label>
            <input id="weight" name="weight" type="number" min="1" max="100" value="{{ old('weight', $competency->weight ?? 1) }}" class="field-input w-full">
            <p class="mt-1 text-[11px] text-slate-400">Higher weight counts more in the blended score.</p>
            @error('weight')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="position" class="field-label">Order</label>
            <input id="position" name="position" type="number" min="0" value="{{ old('position', $competency->position ?? 0) }}" class="field-input w-full">
        </div>
        <div class="flex items-end">
            <label class="inline-flex items-center gap-2 pb-2">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" value="1" @checked(old('active', $competency->active ?? true))
                       class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                <span class="text-sm text-slate-700">Active</span>
            </label>
        </div>
    </div>
</div>
