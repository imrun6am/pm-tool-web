{{-- Success / error / warning messages surface as floating toasts (layouts/toasts).
     Validation errors stay inline here so they persist while the user fixes them. --}}
@if ($errors->any())
    <div class="mt-4 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-card">
        <svg class="mt-0.5 h-5 w-5 flex-none text-rose-500" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.7 7.3a1 1 0 00-1.4 1.4L8.6 10l-1.3 1.3a1 1 0 101.4 1.4L10 11.4l1.3 1.3a1 1 0 001.4-1.4L11.4 10l1.3-1.3a1 1 0 00-1.4-1.4L10 8.6 8.7 7.3z" clip-rule="evenodd" />
        </svg>
        <div>
            <p class="font-medium">Please fix the following:</p>
            <ul class="mt-1 list-inside list-disc space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
