@php $isCreate = ! $user->exists; @endphp

<div class="form-section">
    <div>
        <h3 class="form-section-title">Account</h3>
        <p class="form-section-desc">Only Admins manage projects, teams and releases. Every role can comment and check tasks. Admin + CTO manage users.</p>
    </div>
    <div class="form-section-body">
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="name" class="field-label">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required class="field-input">
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="role" class="field-label">Role</label>
                <select id="role" name="role" required class="field-select">
                    @foreach (\App\Models\User::ROLES as $value => $label)
                        <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('role') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label for="email" class="field-label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required class="field-input">
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    </div>
</div>

<div class="form-section">
    <div>
        <h3 class="form-section-title">Password</h3>
        <p class="form-section-desc">{{ $isCreate ? 'Set an initial password and share it with the user — they can change it later in Profile.' : 'Leave blank to keep the current password.' }}</p>
    </div>
    <div class="form-section-body">
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="password" class="field-label">{{ $isCreate ? 'Password' : 'New password' }}</label>
                <input id="password" name="password" type="password" {{ $isCreate ? 'required' : '' }} autocomplete="new-password" class="field-input">
                @error('password') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password_confirmation" class="field-label">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="field-input">
            </div>
        </div>
    </div>
</div>
