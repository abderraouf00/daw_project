<?php
namespace App\Http\Controllers;

use App\Models\Session;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    // Get all sessions for an event
    public function index($eventId)
    {
        $sessions = Session::where('event_id', $eventId)
            ->with(['chair', 'sessionPapers.submission.mainAuthor'])
            ->orderBy('start_time')
            ->get();

        return response()->json($sessions);
    }

    // Get single session
    public function show($id)
    {
        $session = Session::with([
            'chair',
            'sessionPapers.submission.mainAuthor',
            'questions.user'
        ])->findOrFail($id);

        return response()->json($session);
    }

    // Create session (Organizer only)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'title' => 'required|string|max:255',
            'room' => 'required|string|max:100',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'session_chair_id' => 'nullable|exists:users,id'
        ]);

        $session = Session::create($validated);

        return response()->json([
            'message' => 'Session created successfully',
            'session' => $session
        ], 201);
    }

    // Update session
    public function update(Request $request, $id)
    {
        $session = Session::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'room' => 'sometimes|string|max:100',
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date|after:start_time',
            'session_chair_id' => 'nullable|exists:users,id'
        ]);

        $session->update($validated);

        return response()->json([
            'message' => 'Session updated successfully',
            'session' => $session
        ]);
    }

    // Delete session
    public function destroy($id)
    {
        $session = Session::findOrFail($id);
        $session->delete();

        return response()->json(['message' => 'Session deleted successfully']);
    }

    // Assign submissions to session
    public function assignSubmissions(Request $request, $id)
    {
        $validated = $request->validate([
            'submissions' => 'required|array',
            'submissions.*.submission_id' => 'required|exists:submissions,id',
            'submissions.*.presentation_order' => 'required|integer|min:1'
        ]);

        $session = Session::findOrFail($id);

        foreach ($validated['submissions'] as $item) {
            $session->sessionPapers()->updateOrCreate(
                ['submission_id' => $item['submission_id']],
                ['presentation_order' => $item['presentation_order']]
            );
        }

        return response()->json([
            'message' => 'Submissions assigned successfully',
            'session' => $session->load('sessionPapers.submission')
        ]);
    }
}