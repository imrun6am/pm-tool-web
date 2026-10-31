<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommentRequest;
use App\Models\Comment;
use App\Models\Release;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;

class CommentController extends Controller
{
    public function storeForRelease(CommentRequest $request, Release $release): RedirectResponse
    {
        $release->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        return back()->with('success', 'Comment posted.');
    }

    public function storeForTask(CommentRequest $request, Task $task): RedirectResponse
    {
        $task->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        return back()->with('success', 'Comment posted.');
    }

    public function update(CommentRequest $request, Comment $comment): RedirectResponse
    {
        $this->authorize('update', $comment);

        $comment->update(['body' => $request->validated('body')]);

        return back()->with('success', 'Comment updated.');
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return back()->with('success', 'Comment deleted.');
    }
}
