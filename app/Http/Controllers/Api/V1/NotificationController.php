<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** The caller's own inbox — the same records and counts as the web bell. */
class NotificationController extends ApiController
{
    public function __construct(private readonly NotificationService $notifications) {}

    /** Newest first; `?unread=1` for unread only. `meta.unread_count` is always the total unread. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        return $this->paginate($request, $this->notifications->inbox($user, $request->boolean('unread')), NotificationResource::class)
            ->additional(['meta' => ['unread_count' => $this->notifications->unreadCount($user)]]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $notification = $this->notifications->markRead($this->notifications->find($request->user(), $notification));

        return $this->ok(new NotificationResource($notification));
    }

    public function readAll(Request $request): JsonResponse
    {
        $count = $this->notifications->markAllRead($request->user());

        return $this->message($count === 1 ? '1 notification marked read.' : "{$count} notifications marked read.");
    }
}
