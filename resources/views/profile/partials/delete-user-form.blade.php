<section id="danger-zone" class="card scroll-mt-24 border-danger-200">
    <div class="card-pad">
        <div class="form-section">
            <div>
                <h2 class="form-section-title text-danger-700">{{ __('Delete Account') }}</h2>
                <p class="form-section-desc">Danger zone — this can't be undone.</p>
            </div>
            <div class="form-section-body">
                <div class="flex flex-col gap-4 rounded-lg bg-danger-50/60 p-4 ring-1 ring-inset ring-danger-600/10 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm leading-relaxed text-slate-600">
                        {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
                    </p>
                    <x-danger-button
                        class="flex-none self-start sm:self-center"
                        x-data=""
                        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
                    >{{ __('Delete Account') }}</x-danger-button>
                </div>
            </div>
        </div>
    </div>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-semibold text-slate-900">
                {{ __('Are you sure you want to delete your account?') }}
            </h2>

            <p class="mt-1 text-sm text-slate-600">
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
            </p>

            @php $delErr = $errors->userDeletion->first('password'); @endphp
            <div class="mt-6">
                <label for="password" class="sr-only">{{ __('Password') }}</label>
                <input id="password" name="password" type="password" placeholder="{{ __('Password') }}"
                       autocomplete="current-password" class="field-input sm:max-w-sm"
                       @if ($delErr) aria-invalid="true" aria-describedby="password-error" @endif>
                @if ($delErr)
                    <p id="password-error" class="field-error">
                        <x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $delErr }}
                    </p>
                @endif
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-danger-button>
                    {{ __('Delete Account') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
