<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use App\Support\NotificationLink;
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

        $url = NotificationLink::url($notification, $request->user());

        return $url
            ? redirect($url)
            : redirect()->route('notifications.index')->with('error', 'That item is no longer available.');
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
