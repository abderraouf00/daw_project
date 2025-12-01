<?php
namespace App\Http\Controllers;

use App\Models\ReviewAssignment;
use App\Models\Notification;
use Illuminate\Http\Request;

class ReviewAssignmentController extends Controller
{
    // Get all review assignments for an event
    public function index($eventId)
    {
        $assignments = ReviewAssignment::whereHas('submission', function($q) use ($eventId) {
            $q->where('event_id', $eventId);
        })->with(['submission', 'reviewer', 'review'])->get();

        return response()->json($assignments);
    }

    // Assign reviewer to submission (Organizer only)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'submission_id' => 'required|exists:submissions,id',
            'reviewer_id' => 'required|exists:users,id'
        ]);

        $assignment = ReviewAssignment::create([
            'submission_id' => $validated['submission_id'],
            'reviewer_id' => $validated['reviewer_id'],
            'status' => 'pending'
        ]);

        // Notify reviewer
        $submission = $assignment->submission;
        Notification::create([
            'user_id' => $validated['reviewer_id'],
            'event_id' => $submission->event_id,
            'type' => 'review_assignment',
            'title' => 'New Review Assignment',
            'message' => "You have been assigned to review '{$submission->title}'.",
            'is_read' => false
        ]);

        return response()->json([
            'message' => 'Reviewer assigned successfully',
            'assignment' => $assignment
        ], 201);
    }

    // Get my review assignments
    public function myAssignments(Request $request)
    {
        $assignments = ReviewAssignment::where('reviewer_id', $request->user()->id)
            ->with(['submission.mainAuthor', 'review'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($assignments);
    }

    // Delete assignment
    public function destroy($id)
    {
        $assignment = ReviewAssignment::findOrFail($id);
        $assignment->delete();

        return response()->json(['message' => 'Assignment removed successfully']);
    }
}