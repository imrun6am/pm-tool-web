{{--
    Deleting every event of a device, or every event a filter matches, asks
    for the viewer's password. One dialog per page: a button opens it with
      $dispatch('claude-delete-all', { title, message, fields })
    where `fields` are the hidden inputs sent to the bulk delete (all=1, the
    device and the filters). A wrong password reopens it with what was sent.
--}}
@php
    $bag = $errors->getBag('deleteAll');
    $old = $bag->isNotEmpty() ? collect(session()->getOldInput())->except(['_token', 'password']) : null;
    $initial = $old
        ? ['title' => $old['_title'] ?? 'Delete events?', 'message' => $old['_message'] ?? '', 'fields' => $old->except(['_title', '_message'])->all()]
        : ['title' => '', 'message' => '', 'fields' => []];
@endphp
<div x-data="{ target: @js($initial) }"
     @claude-delete-all.window="target = $event.detail; $dispatch('open-modal', 'claude-delete-all')">
    <x-modal name="claude-delete-all" :show="$bag->isNotEmpty()" maxWidth="md" focusable>
        <form method="POST" action="{{ route('claude-activity.bulk-destroy') }}" class="p-6">
            @csrf
            <template x-for="(value, key) in target.fields" :key="key">
                <input type="hidden" :name="key" :value="value">
            </template>
            <input type="hidden" name="_title" :value="target.title">
            <input type="hidden" name="_message" :value="target.message">

            <h2 class="text-lg font-semibold text-slate-900" x-text="target.title"></h2>
            <p class="mt-1 text-sm text-slate-600" x-text="target.message"></p>
            <p class="mt-1 text-sm text-slate-600">This can't be undone. Enter your password to confirm.</p>

            @php $passwordError = $bag->first('password'); @endphp
            <div class="mt-5">
                <label for="claude-delete-password" class="sr-only">Password</label>
                <input id="claude-delete-password" name="password" type="password" placeholder="Password" required
                       autocomplete="current-password" class="field-input"
                       @if ($passwordError) aria-invalid="true" aria-describedby="claude-delete-password-error" @endif>
                @if ($passwordError)
                    <p id="claude-delete-password-error" class="field-error">
                        <x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $passwordError }}
                    </p>
                @endif
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <x-secondary-button x-on:click="$dispatch('close')">Cancel</x-secondary-button>
                <x-danger-button>Delete</x-danger-button>
            </div>
        </form>
    </x-modal>
</div>
