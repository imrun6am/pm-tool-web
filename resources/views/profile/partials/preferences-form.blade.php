@php $prefs = app(\App\Services\PreferenceService::class)->all($user); @endphp
<section id="preferences" class="card scroll-mt-24">
    <form method="post" action="{{ route('profile.preferences.update') }}">
        @csrf
        @method('put')

        <div class="card-pad">
            <x-form-section title="Preferences" description="Small touches that make the app feel like yours.">
                <label for="pref-celebrations" class="flex cursor-pointer items-start gap-3">
                    <input id="pref-celebrations" name="celebrations" type="checkbox" value="1" @checked($prefs['celebrations'])
                           class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    <span>
                        <span class="block text-sm font-medium text-slate-800">Celebrate finished tasks</span>
                        <span class="block text-sm text-slate-500">A short “nice work” when you move a task to Done. Only you see it, and it stays still if your system asks for reduced motion.</span>
                    </span>
                </label>
            </x-form-section>
        </div>

        <div class="card-footer">
            <button type="submit" class="btn-primary">{{ __('Save') }}</button>
        </div>
    </form>
</section>
