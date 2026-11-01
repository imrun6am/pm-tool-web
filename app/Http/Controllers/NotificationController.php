<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The signed-in user's own inbox. Nobody can read or touch anyone else's. */
class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): View
    {
        $unread = $request->boolean('unread');
        $user = $request->user();

        return view('notifications.index', [
            'notifications' => $this->notifications->inbox($user, $unread)->paginate(20)->withQueryString(),
            'unreadOnly' => $unread,
            'unreadCount' => $this->notifications->unreadCount($user),
        ]);
    }

    /** Mark it read and go to what it is about. */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $notification = $this->notifications->markRead(
            $this->notifications->find($request->user(), $notification)
        );

        $taskId = $notification->data['task_id'] ?? null;
        if ($taskId && Task::whereKey($taskId)->exists()) {
            return redirect()->route('tasks.show', $taskId);
        }

        return redirect()->route('notifications.index')->with('error', 'That task no longer exists.');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->notifications->markAllRead($request->user());

        return back()->with('success', 'You’re all caught up.');
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['count' => $this->notifications->unreadCount($request->user())]);
    }
}
