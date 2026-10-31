<div class="form-section">
    <div>
        <h3 class="form-section-title">Team details</h3>
        <p class="form-section-desc">A team owns releases for their whole window. The color distinguishes the team on the dashboard.</p>
    </div>
    <div class="form-section-body">
        <div>
            <label for="name" class="field-label">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $team->name) }}" required
                   placeholder="e.g. Team Alpha" class="field-input">
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="description" class="field-label">Description <span class="text-slate-400">(optional)</span></label>
            <textarea id="description" name="description" rows="3" class="field-textarea">{{ old('description', $team->description) }}</textarea>
            @error('description') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="color" class="field-label">Color</label>
            <div class="mt-1 flex items-center gap-3">
                <input id="color" name="color" type="color" value="{{ old('color', $team->color ?? '#0891b2') }}"
                       class="h-10 w-16 cursor-pointer rounded-lg border border-slate-300">
                <span class="text-sm text-slate-500">Shown as a dot beside the team everywhere.</span>
            </div>
            @error('color') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="team_lead_id" class="field-label">Team lead <span class="text-slate-400">(optional)</span></label>
            <select id="team_lead_id" name="team_lead_id" class="field-input">
                <option value="">— No lead assigned —</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected((int) old('team_lead_id', $team->team_lead_id) === $user->id)>
                        {{ $user->name }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">Any user can lead a team — their role doesn’t matter.</p>
            @error('team_lead_id') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    </div>
</div>
