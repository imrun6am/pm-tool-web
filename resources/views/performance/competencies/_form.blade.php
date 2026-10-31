@php use App\Models\PerformanceCompetency; @endphp

<div class="card card-pad">
    <x-form-section title="Competency" description="A skill or behaviour leads score members on during evaluations.">
        <x-field label="Name" for="name">
            <input id="name" name="name" type="text" value="{{ old('name', $competency->name) }}" required
                   class="field-input sm:max-w-md" placeholder="e.g. Code Quality" @invalid('name')>
        </x-field>

        <x-field label="Description" for="description" optional>
            <textarea id="description" name="description" rows="2" class="field-textarea"
                      placeholder="What good looks like for this competency." @invalid('description')>{{ old('description', $competency->description) }}</textarea>
        </x-field>
    </x-form-section>

    <x-form-section title="Scoring" description="Who it applies to, how often it's scored, and how much it counts.">
        <div class="grid gap-5 sm:grid-cols-3">
            <x-field label="Category" for="category">
                <select id="category" name="category" class="field-select" @invalid('category')>
                    @foreach (PerformanceCompetency::CATEGORIES as $val => $label)
                        <option value="{{ $val }}" @selected(old('category', $competency->category) === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Applies to" for="role_scope">
                <select id="role_scope" name="role_scope" class="field-select" @invalid('role_scope')>
                    @foreach (PerformanceCompetency::ROLE_SCOPES as $val => $label)
                        <option value="{{ $val }}" @selected(old('role_scope', $competency->role_scope) === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Cadence" for="cadence">
                <select id="cadence" name="cadence" class="field-select" @invalid('cadence')>
                    @foreach (PerformanceCompetency::CADENCES as $val => $label)
                        <option value="{{ $val }}" @selected(old('cadence', $competency->cadence) === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-field>
        </div>

        <div class="grid gap-5 sm:grid-cols-3">
            <x-field label="Weight" for="weight" hint="Higher weight counts more in the blended score.">
                <input id="weight" name="weight" type="number" min="1" max="100" value="{{ old('weight', $competency->weight ?? 1) }}"
                       class="field-input tabular" @invalid('weight')>
            </x-field>
            <x-field label="Order" for="position" hint="Lower numbers are listed first.">
                <input id="position" name="position" type="number" min="0" value="{{ old('position', $competency->position ?? 0) }}"
                       class="field-input tabular" @invalid('position')>
            </x-field>
        </div>

        <label class="flex cursor-pointer items-start gap-3">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" name="active" value="1" @checked(old('active', $competency->active ?? true)) class="field-check mt-0.5">
            <span>
                <span class="block text-sm font-medium text-slate-700">Active</span>
                <span class="block text-xs text-slate-500">Inactive competencies leave the evaluation grid; past scores are kept.</span>
            </span>
        </label>
    </x-form-section>
</div>
