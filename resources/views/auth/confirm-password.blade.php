<x-guest-layout>
    <div class="mb-7">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Confirm it’s you</h1>
        <p class="mt-1.5 text-sm text-slate-500">{{ __('This is a secure area of the application. Please confirm your password before continuing.') }}</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" x-data="{ busy: false }" @submit="busy = true" class="space-y-5">
        @csrf

        <div>
            <label for="password" class="field-label">{{ __('Password') }}</label>
            <input id="password" name="password" type="password" required autofocus autocomplete="current-password"
                   class="field-control mt-1.5 h-11" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
            @error('password')
                <p id="password-error" class="field-error"><x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn-primary h-11 w-full justify-center" :disabled="busy">{{ __('Confirm') }}</button>
    </form>
</x-guest-layout>
