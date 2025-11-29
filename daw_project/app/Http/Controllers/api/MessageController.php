<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index()
    {
        $messages = Message::with('sender:id,name,photo')
                          ->where('receiver_id', auth()->id())
                          ->latest()
                          ->paginate(20);

        return response()->json($messages);
    }

    public function show(Message $message)
    {
        if ($message->receiver_id !== auth()->id() && $message->sender_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $message->load(['sender:id,name,photo', 'receiver:id,name,photo']);
        return response()->json($message);
    }

    public function send(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'event_id' => 'nullable|exists:events,id'
        ]);

        $message = Message::create([
            'sender_id' => auth()->id(),
            'receiver_id' => $request->receiver_id,
            'event_id' => $request->event_id,
            'subject' => $request->subject,
            'body' => $request->body,
        ]);

        return response()->json(['message' => 'Message envoyé', 'data' => $message], 201);
    }

    public function markAsRead(Message $message)
    {
        if ($message->receiver_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $message->update(['is_read' => true, 'read_at' => now()]);
        return response()->json(['message' => 'Message marqué comme lu']);
    }

    public function destroy(Message $message)
    {
        if ($message->receiver_id !== auth()->id() && $message->sender_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $message->delete();
        return response()->json(['message' => 'Message supprimé']);
    }
}