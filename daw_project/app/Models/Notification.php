<?php
namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // Get all notifications for authenticated user
    public function index(Request $request)
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->with('event')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($notifications);
    }

    // Get unread notifications
    public function unread(Request $request)
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->with('event')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($notifications);
    }

    // Mark notification as read
    public function markAsRead($id)
    {
        $notification = Notification::findOrFail($id);

        // Check authorization
        if ($notification->user_id != auth()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $notification->update(['is_read' => true]);

        return response()->json(['message' => 'Notification marked as read']);
    }

    // Mark all as read
    public function markAllAsRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    // Delete notification
    public function destroy($id)
    {
        $notification = Notification::findOrFail($id);

        // Check authorization
        if ($notification->user_id != auth()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $notification->delete();

        return response()->json(['message' => 'Notification deleted successfully']);
    }

    // Get unread count
    public function unreadCount(Request $request)
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    // Create notification (System use)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'event_id' => 'nullable|exists:events,id',
            'type' => 'required|string',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'data' => 'nullable|array'
        ]);

        $notification = Notification::create([
            ...$validated,
            'is_read' => false
        ]);

        return response()->json([
            'message' => 'Notification created',
            'notification' => $notification
        ], 201);
    }
}