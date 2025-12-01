<?php
namespace App\Http\Controllers;

use App\Models\Workshop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WorkshopController extends Controller
{
    // Get all workshops for an event
    public function index($eventId)
    {
        $workshops = Workshop::where('event_id', $eventId)
            ->with(['responsible', 'registrations'])
            ->withCount('registrations')
            ->get();

        return response()->json($workshops);
    }

    // Get single workshop
    public function show($id)
    {
        $workshop = Workshop::with(['responsible', 'participants'])
            ->withCount('registrations')
            ->findOrFail($id);

        return response()->json($workshop);
    }

    // Create workshop (Organizer only)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'responsible_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'max_places' => 'required|integer|min:1',
            'materials' => 'nullable|file|mimes:pdf,zip|max:20480'
        ]);

        // Upload materials if provided
        if ($request->hasFile('materials')) {
            $validated['materials_path'] = $request->file('materials')->store('workshops', 'public');
        }

        $workshop = Workshop::create($validated);

        return response()->json([
            'message' => 'Workshop created successfully',
            'workshop' => $workshop
        ], 201);
    }

    // Update workshop
    public function update(Request $request, $id)
    {
        $workshop = Workshop::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'responsible_id' => 'sometimes|exists:users,id',
            'date' => 'sometimes|date',
            'max_places' => 'sometimes|integer|min:1',
            'materials' => 'nullable|file|mimes:pdf,zip|max:20480'
        ]);

        // Update materials if provided
        if ($request->hasFile('materials')) {
            if ($workshop->materials_path) {
                Storage::disk('public')->delete($workshop->materials_path);
            }
            $validated['materials_path'] = $request->file('materials')->store('workshops', 'public');
        }

        $workshop->update($validated);

        return response()->json([
            'message' => 'Workshop updated successfully',
            'workshop' => $workshop
        ]);
    }

    // Delete workshop
    public function destroy($id)
    {
        $workshop = Workshop::findOrFail($id);
        
        if ($workshop->materials_path) {
            Storage::disk('public')->delete($workshop->materials_path);
        }

        $workshop->delete();

        return response()->json(['message' => 'Workshop deleted successfully']);
    }

    // Register for workshop
    public function register(Request $request, $id)
    {
        $workshop = Workshop::withCount('registrations')->findOrFail($id);

        // Check if workshop is full
        if ($workshop->registrations_count >= $workshop->max_places) {
            return response()->json(['message' => 'Workshop is full'], 400);
        }

        // Check if already registered
        $exists = $workshop->registrations()
            ->where('user_id', $request->user()->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Already registered'], 400);
        }

        $workshop->registrations()->create([
            'user_id' => $request->user()->id
        ]);

        return response()->json([
            'message' => 'Registered successfully',
            'workshop' => $workshop->load('registrations')
        ]);
    }

    // Unregister from workshop
    public function unregister(Request $request, $id)
    {
        $workshop = Workshop::findOrFail($id);

        $deleted = $workshop->registrations()
            ->where('user_id', $request->user()->id)
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Not registered'], 400);
        }

        return response()->json(['message' => 'Unregistered successfully']);
    }

    // Get workshop participants (Workshop responsible only)
    public function participants($id)
    {
        $workshop = Workshop::with('participants')->findOrFail($id);

        return response()->json([
            'workshop' => $workshop->title,
            'participants' => $workshop->participants
        ]);
    }
}
