<div class="card card-pad">
    <x-form-section title="Team details" description="A team owns releases for their whole window, and its tasks, tasksheet and standups are tracked together.">
        <x-field label="Name" for="name" hint="Must be unique among active teams.">
            <input id="name" name="name" type="text" value="{{ old('name', $team->name) }}" required maxlength="255"
                   autocomplete="off" placeholder="e.g. Team Alpha" class="field-input" @invalid('name')>
        </x-field>

        <x-field label="Description" for="description" optional hint="Shown on the team page and its card in the directory.">
            <textarea id="description" name="description" rows="3" maxlength="2000" class="field-textarea"
                      placeholder="What this team works on, in a sentence or two." @invalid('description')>{{ old('description', $team->description) }}</textarea>
        </x-field>
    </x-form-section>

    <x-form-section title="Leadership" description="The lead runs the team’s standups and reviews its tasksheet. Members are added from the team page.">
        <x-field label="Team lead" for="team_lead_id" optional hint="Any user can lead a team — their role doesn’t matter.">
            <select id="team_lead_id" name="team_lead_id" class="field-select" @invalid('team_lead_id')>
                <option value="">— No lead assigned —</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected((int) old('team_lead_id', $team->team_lead_id) === $user->id)>
                        {{ $user->name }} · {{ $user->roleLabel() }}
                    </option>
                @endforeach
            </select>
        </x-field>
    </x-form-section>

    <x-form-section title="Appearance" description="The color marks this team everywhere it appears: the dashboard timeline, the board, release lists and the team directory.">
        <x-field label="Color" for="color" hint="Pick a preset or choose a custom color.">
            <x-color-picker name="color" :value="old('color', $team->color ?? '#0891b2')" />
        </x-field>
    </x-form-section>
</div>
