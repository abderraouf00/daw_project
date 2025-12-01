<?php
namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Notification;
use Illuminate\Http\Request;

class EventController extends Controller
{
    // Get all events (public - with filters)
    public function index(Request $request)
    {
        $query = Event::with(['creator', 'committees', 'invitedSpeakers']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('start_date', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('end_date', '<=', $request->to_date);
        }

        // Search by title or theme
        if ($request->has('search')) {
            $query->where(function($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('theme', 'like', '%' . $request->search . '%');
            });
        }

        $events = $query->orderBy('start_date', 'desc')->paginate(10);
        return response()->json($events);
    }

    // Get single event details
    public function show($id)
    {
        $event = Event::with([
            'creator',
            'committees.user',
            'invitedSpeakers',
            'sessions.chair',
            'workshops.responsible',
            'registrations.user'
        ])->findOrFail($id);

        return response()->json($event);
    }

    // Create new event (Organizer/Admin only)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'location' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'theme' => 'nullable|string|max:255',
            'contact' => 'required|string|max:255',
            'status' => 'nullable|in:upcoming,ongoing,completed,cancelled'
        ]);

        $validated['created_by'] = $request->user()->id;
        $validated['status'] = $validated['status'] ?? 'upcoming';

        $event = Event::create($validated);

        return response()->json([
            'message' => 'Event created successfully',
            'event' => $event
        ], 201);
    }

    // Update event
    public function update(Request $request, $id)
    {
        $event = Event::findOrFail($id);

        // Check authorization
        if ($event->created_by != $request->user()->id && !$request->user()->hasRole('super_admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'location' => 'sometimes|string|max:255',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'theme' => 'nullable|string|max:255',
            'contact' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:upcoming,ongoing,completed,cancelled'
        ]);

        $event->update($validated);

        // Notify registered users about changes
        $this->notifyEventUpdate($event);

        return response()->json([
            'message' => 'Event updated successfully',
            'event' => $event
        ]);
    }

    // Delete event
    public function destroy($id)
    {
        $event = Event::findOrFail($id);

        // Check authorization
        if ($event->created_by != auth()->user()->id && !auth()->user()->hasRole('super_admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $event->delete();

        return response()->json(['message' => 'Event deleted successfully']);
    }

    // Get events created by authenticated user
    public function myEvents(Request $request)
    {
        $events = Event::where('created_by', $request->user()->id)
            ->with(['committees', 'sessions', 'workshops', 'registrations'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($events);
    }

    // Helper: Notify users about event updates
    private function notifyEventUpdate($event)
    {
        $registrations = $event->registrations;

        foreach ($registrations as $registration) {
            Notification::create([
                'user_id' => $registration->user_id,
                'event_id' => $event->id,
                'type' => 'event_update',
                'title' => 'Event Updated',
                'message' => "The event '{$event->title}' has been updated. Please check the new details.",
                'is_read' => false
            ]);
        }
    }
}