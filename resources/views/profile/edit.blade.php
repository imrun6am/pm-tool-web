<x-app-layout>
    <x-slot name="header">
        {{-- No breadcrumbs: Profile sits outside the menu, so the page must not mark anything aria-current. --}}
        <x-page-header :title="__('Profile')" description="Your name, sign-in email and password." />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <div class="mx-auto max-w-5xl space-y-6">
                @include('profile.partials.update-profile-information-form')
                @include('profile.partials.update-password-form')
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</x-app-layout>
