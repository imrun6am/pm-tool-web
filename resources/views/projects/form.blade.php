{{-- Shared create/edit fields. Expects $project. Field names: name, description, color. --}}
<div class="card card-pad">
    <x-form-section title="Project details" description="The product or codebase this project delivers. Releases are planned against it.">
        <x-field label="Name" for="name" hint="Unique among active projects. Archived projects free up their name.">
            <input id="name" name="name" type="text" value="{{ old('name', $project->name) }}" required maxlength="255"
                   autocomplete="off" placeholder="e.g. Checkout" class="field-input sm:max-w-md" @invalid('name')>
        </x-field>

        <x-field label="Description" for="description" optional>
            <div x-data="{ count: {{ mb_strlen((string) old('description', $project->description)) }} }">
                <textarea id="description" name="description" rows="4" maxlength="2000" class="field-textarea"
                          x-on:input="count = $event.target.value.length"
                          placeholder="What this project covers, who it serves, and what “done” looks like." @invalid('description')>{{ old('description', $project->description) }}</textarea>
                <p class="field-hint flex justify-between gap-3">
                    <span>Shown on the project card and at the top of its page.</span>
                    <span class="tabular flex-none" x-text="count + ' / 2000'" aria-hidden="true"></span>
                </p>
            </div>
        </x-field>
    </x-form-section>

    <x-form-section title="Appearance" description="The color marks this project's releases on the dashboard timeline, the board and every list.">
        <x-field label="Color" for="color" hint="Pick a preset or choose a custom color. Distinct colors keep timelines easy to scan.">
            <x-color-picker name="color" :value="old('color', $project->color)" />
        </x-field>
    </x-form-section>
</div>
