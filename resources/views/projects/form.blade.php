<div class="card card-pad">
    <x-form-section title="Project details" description="The product this project delivers. The color distinguishes its releases on the dashboard timeline.">
        <x-field label="Name" for="name">
            <input id="name" name="name" type="text" value="{{ old('name', $project->name) }}" required
                   placeholder="e.g. Checkout" class="field-input sm:max-w-md" @invalid('name')>
        </x-field>

        <x-field label="Description" for="description" optional>
            <textarea id="description" name="description" rows="3" class="field-textarea"
                      placeholder="What this project covers, in a sentence or two." @invalid('description')>{{ old('description', $project->description) }}</textarea>
        </x-field>

        <x-field label="Color" for="color" hint="Shown as a dot beside the project everywhere.">
            <x-color-picker name="color" :value="old('color', $project->color ?? '#4f46e5')" />
        </x-field>
    </x-form-section>
</div>
