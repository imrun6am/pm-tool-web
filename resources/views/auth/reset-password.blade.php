<x-guest-layout>
    <div class="mb-7">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Choose a new password</h1>
        <p class="mt-1.5 text-sm text-slate-500">At least 8 characters — a longer passphrase is stronger.</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" x-data="{ busy: false, show: false }" @submit="busy = true" class="space-y-5">
        @csrf

        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="email" class="field-label">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username"
                   class="field-control mt-1.5 h-11" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')
                <p id="email-error" class="field-error"><x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $message }}</p>
            @enderror
        </div>

        @foreach ([['password', __('New password')], ['password_confirmation', __('Confirm new password')]] as [$field, $label])
            <div>
                <label for="{{ $field }}" class="field-label">{{ $label }}</label>
                <input id="{{ $field }}" name="{{ $field }}" type="password" :type="show ? 'text' : 'password'" required autocomplete="new-password"
                       class="field-control mt-1.5 h-11" @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>
                @error($field)
                    <p id="{{ $field }}-error" class="field-error"><x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $message }}</p>
                @enderror
            </div>
        @endforeach

        <label class="inline-flex cursor-pointer items-center gap-2">
            <input type="checkbox" x-model="show" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            <span class="text-sm text-slate-600">Show passwords</span>
        </label>

        <button type="submit" class="btn-primary h-11 w-full justify-center" :disabled="busy">
            <span x-text="busy ? 'Saving…' : 'Reset password'">{{ __('Reset Password') }}</span>
        </button>
    </form>
</x-guest-layout>
