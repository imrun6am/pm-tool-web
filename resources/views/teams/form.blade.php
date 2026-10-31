<div class="card card-pad">
    <x-form-section title="Team details" description="A team owns releases for their whole window. The color distinguishes the team on the dashboard.">
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field label="Name" for="name">
                <input id="name" name="name" type="text" value="{{ old('name', $team->name) }}" required
                       placeholder="e.g. Team Alpha" class="field-input" @invalid('name')>
            </x-field>

            <x-field label="Team lead" for="team_lead_id" optional hint="Any user can lead a team — their role doesn’t matter.">
                <select id="team_lead_id" name="team_lead_id" class="field-select" @invalid('team_lead_id')>
                    <option value="">— No lead assigned —</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((int) old('team_lead_id', $team->team_lead_id) === $user->id)>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
            </x-field>
        </div>

        <x-field label="Description" for="description" optional>
            <textarea id="description" name="description" rows="3" class="field-textarea"
                      placeholder="What this team works on, in a sentence or two." @invalid('description')>{{ old('description', $team->description) }}</textarea>
        </x-field>

        <x-field label="Color" for="color" hint="Shown as a dot beside the team everywhere.">
            <x-color-picker name="color" :value="old('color', $team->color ?? '#0891b2')" />
        </x-field>
    </x-form-section>
</div>
