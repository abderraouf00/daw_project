<?php
namespace App\Http\Controllers;

use App\Models\Poll;
use Illuminate\Http\Request;

class PollController extends Controller
{
    // Get all polls for an event
    public function index($eventId)
    {
        $polls = Poll::where('event_id', $eventId)
            ->withCount('responses')
            ->get();

        return response()->json($polls);
    }

    // Get single poll with responses
    public function show($id)
    {
        $poll = Poll::with(['responses.user'])
            ->withCount('responses')
            ->findOrFail($id);

        return response()->json($poll);
    }

    // Create poll (Organizer only)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'question_text' => 'required|string'
        ]);

        $poll = Poll::create($validated);

        return response()->json([
            'message' => 'Poll created successfully',
            'poll' => $poll
        ], 201);
    }

    // Update poll
    public function update(Request $request, $id)
    {
        $poll = Poll::findOrFail($id);

        $validated = $request->validate([
            'question_text' => 'sometimes|string'
        ]);

        $poll->update($validated);

        return response()->json([
            'message' => 'Poll updated successfully',
            'poll' => $poll
        ]);
    }

    // Delete poll
    public function destroy($id)
    {
        $poll = Poll::findOrFail($id);
        $poll->delete();

        return response()->json(['message' => 'Poll deleted successfully']);
    }

    // Submit poll response
    public function respond(Request $request, $id)
    {
        $validated = $request->validate([
            'response' => 'required|string'
        ]);

        $poll = Poll::findOrFail($id);

        // Check if already responded
        $exists = $poll->responses()
            ->where('user_id', $request->user()->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Already responded'], 400);
        }

        $response = $poll->responses()->create([
            'user_id' => $request->user()->id,
            'response' => $validated['response']
        ]);

        return response()->json([
            'message' => 'Response submitted successfully',
            'response' => $response
        ], 201);
    }

    // Get poll results
    public function results($id)
    {
        $poll = Poll::with('responses')->findOrFail($id);

        // Group responses by answer
        $results = $poll->responses()
            ->select('response', \DB::raw('count(*) as count'))
            ->groupBy('response')
            ->get();

        return response()->json([
            'poll' => $poll,
            'total_responses' => $poll->responses->count(),
            'results' => $results
        ]);
    }
}
