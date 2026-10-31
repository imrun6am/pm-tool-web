<section id="profile" class="card scroll-mt-24">
    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}">
        @csrf
        @method('patch')

        <div class="card-pad">
            <x-form-section :title="__('Profile Information')" :description="__('Update your account\'s profile information and email address.')">
                <div class="grid gap-5 sm:grid-cols-2">
                <x-field :label="__('Name')" for="name">
                    <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name"
                           class="field-input" @invalid('name')>
                </x-field>

                <x-field :label="__('Email')" for="email" hint="Used to sign in and for notifications.">
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="username"
                           class="field-input" @invalid('email')>

                    @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                        <div class="mt-3 rounded-lg bg-warning-50 px-3 py-2 text-sm text-warning-800 ring-1 ring-inset ring-warning-600/20">
                            <p>
                                {{ __('Your email address is unverified.') }}

                                <button form="send-verification" class="rounded font-medium underline underline-offset-2 hover:text-warning-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                                    {{ __('Click here to re-send the verification email.') }}
                                </button>
                            </p>

                            @if (session('status') === 'verification-link-sent')
                                <p class="mt-1 font-medium text-success-700">
                                    {{ __('A new verification link has been sent to your email address.') }}
                                </p>
                            @endif
                        </div>
                    @endif
                </x-field>
                </div>
            </x-form-section>
        </div>

        <div class="card-footer">
            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                   class="mr-auto flex items-center gap-1.5 text-sm text-success-700" role="status">
                    <x-icon name="check" variant="outline" class="h-4 w-4" />{{ __('Saved.') }}
                </p>
            @endif
            <button type="submit" class="btn-primary">{{ __('Save') }}</button>
        </div>
    </form>
</section>
