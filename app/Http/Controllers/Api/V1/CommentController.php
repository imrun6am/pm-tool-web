<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\CommentRequest;
use App\Http\Resources\V1\CommentResource;
use App\Models\Comment;
use App\Models\Release;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Threaded comments on releases and tasks. Anyone signed in may post; editing
 * and deleting are the author's or a lead's, per CommentPolicy.
 */
class CommentController extends ApiController
{
    public function indexForRelease(Request $request, Release $release): AnonymousResourceCollection
    {
        return $this->paginate($request, $release->comments()->with('user'), CommentResource::class);
    }

    public function indexForTask(Request $request, Task $task): AnonymousResourceCollection
    {
        return $this->paginate($request, $task->comments()->with('user'), CommentResource::class);
    }

    public function storeForRelease(CommentRequest $request, Release $release): JsonResponse
    {
        $comment = $release->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        return $this->created(new CommentResource($comment->load('user')), 'Comment posted.');
    }

    public function storeForTask(CommentRequest $request, Task $task): JsonResponse
    {
        $comment = $task->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        return $this->created(new CommentResource($comment->load('user')), 'Comment posted.');
    }

    public function update(CommentRequest $request, Comment $comment): JsonResponse
    {
        $this->authorize('update', $comment);

        $comment->update(['body' => $request->validated('body')]);

        return $this->ok(new CommentResource($comment->load('user')), 'Comment updated.');
    }

    public function destroy(Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return $this->message('Comment deleted.');
    }
}
