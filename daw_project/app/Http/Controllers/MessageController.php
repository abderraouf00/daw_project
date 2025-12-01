<?php
namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    // Get inbox messages
    public function inbox(Request $request)
    {
        $messages = Message::where('receiver_id', $request->user()->id)
            ->with(['sender', 'event'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($messages);
    }

    // Get sent messages
    public function sent(Request $request)
    {
        $messages = Message::where('sender_id', $request->user()->id)
            ->with(['receiver', 'event'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($messages);
    }

    // Get single message
    public function show($id)
    {
        $message = Message::with(['sender', 'receiver', 'event'])->findOrFail($id);

        // Check authorization
        if ($message->receiver_id != auth()->user()->id && $message->sender_id != auth()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Mark as read if receiver is viewing
        if ($message->receiver_id == auth()->user()->id && !$message->is_read) {
            $message->update(['is_read' => true]);
        }

        return response()->json($message);
    }

    // Send message
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'nullable|exists:events,id',
            'receiver_id' => 'required|exists:users,id',
            'subject' => 'required|string|max:255',
            'body' => 'required|string'
        ]);

        $message = Message::create([
            'event_id' => $validated['event_id'] ?? null,
            'sender_id' => $request->user()->id,
            'receiver_id' => $validated['receiver_id'],
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'is_read' => false
        ]);

        return response()->json([
            'message' => 'Message sent successfully',
            'data' => $message->load(['sender', 'receiver'])
        ], 201);
    }

    // Mark message as read
    public function markAsRead($id)
    {
        $message = Message::findOrFail($id);

        // Check authorization
        if ($message->receiver_id != auth()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $message->update(['is_read' => true]);

        return response()->json(['message' => 'Marked as read']);
    }

    // Mark all as read
    public function markAllAsRead(Request $request)
    {
        Message::where('receiver_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'All messages marked as read']);
    }

    // Delete message
    public function destroy($id)
    {
        $message = Message::findOrFail($id);

        // Check authorization
        if ($message->receiver_id != auth()->user()->id && $message->sender_id != auth()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $message->delete();

        return response()->json(['message' => 'Message deleted successfully']);
    }

    // Get unread count
    public function unreadCount(Request $request)
    {
        $count = Message::where('receiver_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return response()->json(['unread_count' => $count]);
    }
}