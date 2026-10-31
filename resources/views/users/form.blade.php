@php $isCreate = ! $user->exists; @endphp

<div class="card card-pad">
    <x-form-section title="Account" description="Only Admins manage projects, teams and releases. Every role can comment and check tasks. Admin + CTO manage users.">
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field label="Name" for="name">
                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required
                       autocomplete="off" class="field-input" @invalid('name')>
            </x-field>
            <x-field label="Role" for="role">
                <select id="role" name="role" required class="field-select" @invalid('role')>
                    @foreach (\App\Models\User::ROLES as $value => $label)
                        <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-field>
        </div>
        <x-field label="Email" for="email">
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required
                   autocomplete="off" placeholder="name@company.com" class="field-input sm:max-w-md" @invalid('email')>
        </x-field>
    </x-form-section>

    <x-form-section title="Password" :description="$isCreate ? 'Set an initial password and share it with the user — they can change it later in Profile.' : 'Leave blank to keep the current password.'">
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field :label="$isCreate ? 'Password' : 'New password'" for="password" :optional="! $isCreate">
                <input id="password" name="password" type="password" {{ $isCreate ? 'required' : '' }} autocomplete="new-password" class="field-input" @invalid('password')>
            </x-field>
            <x-field label="Confirm password" for="password_confirmation" name="password_confirmation" :optional="! $isCreate">
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="field-input" @invalid('password_confirmation')>
            </x-field>
        </div>
    </x-form-section>
</div>
