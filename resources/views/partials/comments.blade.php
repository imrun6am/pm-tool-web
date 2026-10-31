{{-- Expects: $comments (Collection), $storeUrl (string) --}}
<div class="space-y-4">
    @forelse ($comments as $comment)
        <div class="flex gap-3" x-data="{ editing: false }">
            <div class="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">
                {{ strtoupper(substr($comment->user->name ?? '?', 0, 1)) }}
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-slate-800">{{ $comment->user->name ?? 'Unknown' }}</span>@include('partials.user-tag', ['tagUser' => $comment->user])
                    <span class="text-xs text-slate-400">{{ $comment->created_at->diffForHumans() }}</span>
                    @if ($comment->created_at != $comment->updated_at)
                        <span class="text-xs text-slate-300">(edited)</span>
                    @endif
                </div>

                <div x-show="!editing" class="mt-0.5 whitespace-pre-line text-sm text-slate-700">{{ $comment->body }}</div>

                @can('update', $comment)
                    <form x-show="editing" x-cloak method="POST" action="{{ route('comments.update', $comment) }}" class="mt-1">
                        @csrf @method('PUT')
                        <textarea name="body" rows="2" class="field-input">{{ $comment->body }}</textarea>
                        <div class="mt-1 flex gap-2">
                            <button class="rounded bg-indigo-600 px-2 py-1 text-xs font-medium text-white hover:bg-indigo-700">Save</button>
                            <button type="button" @click="editing = false" class="text-xs text-slate-500 hover:text-slate-700">Cancel</button>
                        </div>
                    </form>
                    <div x-show="!editing" class="mt-1 flex gap-3">
                        <button type="button" @click="editing = true" class="text-xs text-slate-400 hover:text-indigo-600">Edit</button>
                        <form method="POST" action="{{ route('comments.destroy', $comment) }}" data-confirm="Delete this comment?" data-confirm-verb="Delete">
                            @csrf @method('DELETE')
                            <button class="text-xs text-slate-400 hover:text-rose-600">Delete</button>
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
