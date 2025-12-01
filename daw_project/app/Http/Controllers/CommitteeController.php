<?php
namespace App\Http\Controllers;

use App\Models\Committee;
use Illuminate\Http\Request;

class CommitteeController extends Controller
{
    // Get committee members for an event
    public function index($eventId)
    {
        $members = Committee::where('event_id', $eventId)
            ->with('user')
            ->get();

        return response()->json($members);
    }

    // Add committee member (Organizer only)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'user_id' => 'required|exists:users,id',
            'role_in_committee' => 'required|string|max:100'
        ]);

        // Check if already a member
        $exists = Committee::where('event_id', $validated['event_id'])
            ->where('user_id', $validated['user_id'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'User already in committee'], 400);
        }

        $member = Committee::create($validated);

        return response()->json([
            'message' => 'Committee member added successfully',
            'member' => $member->load('user')
        ], 201);
    }

    // Update committee member role
    public function update(Request $request, $id)
    {
        $member = Committee::findOrFail($id);

        $validated = $request->validate([
            'role_in_committee' => 'required|string|max:100'
        ]);

        $member->update($validated);

        return response()->json([
            'message' => 'Committee member updated successfully',
            'member' => $member
        ]);
    }

    // Remove committee member
    public function destroy($id)
    {
        $member = Committee::findOrFail($id);
        $member->delete();

        return response()->json(['message' => 'Committee member removed successfully']);
    }

    // Get my committee memberships
    public function myCommittees(Request $request)
    {
        $committees = Committee::where('user_id', $request->user()->id)
            ->with('event')
            ->get();

        return response()->json($committees);
    }
}