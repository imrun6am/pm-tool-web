<div class="form-section">
    <div>
        <h3 class="form-section-title">Project details</h3>
        <p class="form-section-desc">The product this project delivers. The color distinguishes its releases on the dashboard timeline.</p>
    </div>
    <div class="form-section-body">
        <div>
            <label for="name" class="field-label">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $project->name) }}" required
                   placeholder="e.g. Checkout" class="field-input">
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="description" class="field-label">Description <span class="text-slate-400">(optional)</span></label>
            <textarea id="description" name="description" rows="3" class="field-textarea">{{ old('description', $project->description) }}</textarea>
            @error('description') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="color" class="field-label">Color</label>
            <div class="mt-1 flex items-center gap-3">
                <input id="color" name="color" type="color" value="{{ old('color', $project->color ?? '#4f46e5') }}"
                       class="h-10 w-16 cursor-pointer rounded-lg border border-slate-300">
                <span class="text-sm text-slate-500">Shown as a dot beside the project everywhere.</span>
            </div>
            @error('color') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    </div>
</div>
