<x-guest-layout>
    <div class="mb-7">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Reset your password</h1>
        <p class="mt-1.5 text-sm text-slate-500">Enter your work email and we’ll send you a link to choose a new one.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-5" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" x-data="{ busy: false }" @submit="busy = true" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="field-label">{{ __('Email') }}</label>
            <div class="relative mt-1.5">
                <x-icon name="envelope" variant="outline" class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                       placeholder="you@company.com" class="field-control h-11 pl-10"
                       @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            </div>
            @error('email')
                <p id="email-error" class="field-error"><x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn-primary h-11 w-full justify-center" :disabled="busy">
            <span x-text="busy ? 'Sending…' : 'Email me a reset link'">{{ __('Email Password Reset Link') }}</span>
        </button>
    </form>

    <a href="{{ route('login') }}" class="mt-6 inline-flex items-center gap-1.5 rounded text-sm font-medium text-slate-600 hover:text-brand-700">
        <x-icon name="arrow-left" variant="outline" class="h-4 w-4" /> Back to sign in
    </a>
</x-guest-layout>
