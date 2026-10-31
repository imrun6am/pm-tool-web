@php
    $pwFields = [
        ['id' => 'update_password_current_password', 'name' => 'current_password', 'label' => __('Current Password'), 'autocomplete' => 'current-password'],
        ['id' => 'update_password_password', 'name' => 'password', 'label' => __('New Password'), 'autocomplete' => 'new-password'],
        ['id' => 'update_password_password_confirmation', 'name' => 'password_confirmation', 'label' => __('Confirm Password'), 'autocomplete' => 'new-password'],
    ];
@endphp
<section class="card">
    <form method="post" action="{{ route('password.update') }}">
        @csrf
        @method('put')

        <div class="card-pad">
            <x-form-section :title="__('Update Password')" :description="__('Ensure your account is using a long, random password to stay secure.')">
                @foreach ($pwFields as $f)
                    @php $err = $errors->updatePassword->first($f['name']); @endphp
                    {{-- Errors live in the "updatePassword" bag, so they're rendered here rather than by x-field. --}}
                    <x-field :label="$f['label']" :for="$f['id']">
                        <input id="{{ $f['id'] }}" name="{{ $f['name'] }}" type="password" autocomplete="{{ $f['autocomplete'] }}"
                               class="field-input sm:max-w-md"
                               @if ($err) aria-invalid="true" aria-describedby="{{ $f['id'] }}-error" @endif>
                        @if ($err)
                            <p id="{{ $f['id'] }}-error" class="field-error">
                                <x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $err }}
                            </p>
                        @endif
                    </x-field>
                @endforeach
            </x-form-section>
        </div>

        <div class="card-footer">
            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                   class="mr-auto flex items-center gap-1.5 text-sm text-success-700" role="status">
                    <x-icon name="check" variant="outline" class="h-4 w-4" />{{ __('Saved.') }}
                </p>
            @endif
            <button type="submit" class="btn-primary">{{ __('Save') }}</button>
        </div>
    </form>
</section>
