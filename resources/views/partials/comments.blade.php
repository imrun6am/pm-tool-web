{{-- Expects: $comments (Collection), $storeUrl (string) --}}
<div class="space-y-4">
    @forelse ($comments as $comment)
        <div class="flex gap-3" x-data="{ editing: false }">
            <x-avatar :user="$comment->user" :name="$comment->user->name ?? '?'" />
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-slate-800">{{ $comment->user->name ?? 'Unknown' }}</span>@include('partials.user-tag', ['tagUser' => $comment->user])
                    <span class="text-xs text-slate-500" title="{{ $comment->created_at->format('M j, Y g:i a') }}">{{ $comment->created_at->diffForHumans() }}</span>
                    @if ($comment->created_at != $comment->updated_at)
                        <span class="text-xs text-slate-400">(edited)</span>
                    @endif
                </div>

                <div x-show="!editing" class="mt-0.5 whitespace-pre-line text-sm text-slate-700">{{ $comment->body }}</div>

                @can('update', $comment)
                    <form x-show="editing" x-cloak method="POST" action="{{ route('comments.update', $comment) }}" class="mt-1">
                        @csrf @method('PUT')
                        <textarea name="body" rows="2" class="field-input">{{ $comment->body }}</textarea>
                        <div class="mt-2 flex gap-2">
                            <button class="btn-primary btn-sm">Save</button>
                            <button type="button" @click="editing = false" class="btn-ghost btn-sm">Cancel</button>
                        </div>
                    </form>
                    <div x-show="!editing" class="mt-1 flex gap-3">
                        <button type="button" @click="editing = true" class="text-xs font-medium text-slate-500 hover:text-brand-700">Edit</button>
                        <form method="POST" action="{{ route('comments.destroy', $comment) }}" data-confirm="Delete this comment?" data-confirm-verb="Delete">
                            @csrf @method('DELETE')
                            <button class="text-xs font-medium text-slate-500 hover:text-danger-600">Delete</button>
                        </form>
                    </div>
                @endcan
            </div>
        </div>
    @empty
        <p class="text-sm text-slate-400">No comments yet. Start the conversation.</p>
    @endforelse
</div>

<form method="POST" action="{{ $storeUrl }}" class="mt-4">
    @csrf
    <textarea name="body" rows="2" required placeholder="Write a comment…"
              class="field-input">{{ old('body') }}</textarea>
    <div class="mt-2 flex justify-end">
        <button class="btn-primary btn-sm">Post comment</button>
    </div>
</form>
