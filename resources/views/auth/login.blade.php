<x-guest-layout>
    <div class="mb-7">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Welcome back</h1>
        <p class="mt-1.5 text-sm text-slate-500">Sign in to pick up where your team left off.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-5" :status="session('status')" />

    {{-- `busy` shows the loading state; a failed sign-in re-renders the page, which resets it. --}}
    <form method="POST" action="{{ route('login') }}" x-data="{ busy: false, show: false }" @submit="busy = true" class="space-y-5">
        @csrf

        <!-- Email Address -->
        <div>
            <label for="email" class="field-label">{{ __('Email') }}</label>
            <div class="relative mt-1.5">
                <x-icon name="envelope" variant="outline" class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                       placeholder="you@company.com" class="field-control h-11 pl-10"
                       @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            </div>
            @error('email')
                <p id="email-error" class="field-error"><x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $message }}</p>
            @enderror
        </div>

        <!-- Password -->
        <div>
            <div class="flex items-center justify-between">
                <label for="password" class="field-label">{{ __('Password') }}</label>
                @if (Route::has('password.request'))
                    <a class="rounded text-sm font-medium text-brand-600 hover:text-brand-700" href="{{ route('password.request') }}">
                        {{ __('Forgot password?') }}
                    </a>
                @endif
            </div>
            <div class="relative mt-1.5">
                <x-icon name="lock-closed" variant="outline" class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                <input id="password" name="password" type="password" :type="show ? 'text' : 'password'" required autocomplete="current-password"
                       class="field-control h-11 pl-10 pr-11"
                       @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                <button type="button" @click="show = ! show"
                        class="absolute right-1.5 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                        :aria-label="show ? 'Hide password' : 'Show password'" aria-label="Show password" :aria-pressed="show.toString()" aria-controls="password">
                    <x-icon name="eye" variant="outline" class="h-5 w-5" x-show="! show" />
                    <x-icon name="eye-slash" variant="outline" class="h-5 w-5" x-show="show" x-cloak />
                </button>
            </div>
            @error('password')
                <p id="password-error" class="field-error"><x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $message }}</p>
            @enderror
        </div>

        <!-- Remember Me -->
        <label for="remember_me" class="inline-flex cursor-pointer items-center gap-2">
            <input id="remember_me" type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            <span class="text-sm text-slate-600">{{ __('Keep me signed in') }}</span>
        </label>

        <button type="submit" class="btn-primary h-11 w-full justify-center text-[15px]" :disabled="busy" :aria-busy="busy.toString()">
            <svg x-show="busy" x-cloak class="h-4 w-4 animate-spin motion-reduce:animate-none" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.3" stroke-width="3"/>
                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>
            <span x-text="busy ? 'Signing in…' : 'Sign in'">{{ __('Sign in') }}</span>
            <x-icon name="arrow-right" variant="outline" class="h-4 w-4" x-show="! busy" />
        </button>
    </form>

    <p class="mt-8 rounded-lg bg-slate-50 px-4 py-3 text-center text-xs leading-relaxed text-slate-500 ring-1 ring-inset ring-slate-200">
        New here? Accounts are created by a team lead — there’s no public sign-up.
    </p>
</x-guest-layout>
