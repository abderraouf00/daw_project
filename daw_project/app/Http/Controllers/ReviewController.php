<?php
namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Models\Notification;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    // Submit review
    public function store(Request $request)
    {
        $validated = $request->validate([
            'assignment_id' => 'required|exists:review_assignments,id',
            'score_quality' => 'required|integer|min:1|max:10',
            'score_originality' => 'required|integer|min:1|max:10',
            'score_relevance' => 'required|integer|min:1|max:10',
            'comments' => 'required|string',
            'recommendation' => 'required|in:accept,reject,revise'
        ]);

        // Check if user is the assigned reviewer
        $assignment = ReviewAssignment::findOrFail($validated['assignment_id']);
        if ($assignment->reviewer_id != $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if review already exists
        if ($assignment->review) {
            return response()->json(['message' => 'Review already submitted'], 400);
        }

        $review = Review::create($validated);

        // Update assignment status
        $assignment->update(['status' => 'completed']);

        // Notify organizer
        $submission = $assignment->submission;
        Notification::create([
            'user_id' => $submission->event->created_by,
            'event_id' => $submission->event_id,
            'type' => 'review_completed',
            'title' => 'Review Completed',
            'message' => "A review for '{$submission->title}' has been completed.",
            'is_read' => false
        ]);

        return response()->json([
            'message' => 'Review submitted successfully',
            'review' => $review
        ], 201);
    }

    // Update review
    public function update(Request $request, $id)
    {
        $review = Review::findOrFail($id);

        // Check authorization
        if ($review->assignment->reviewer_id != $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'score_quality' => 'sometimes|integer|min:1|max:10',
            'score_originality' => 'sometimes|integer|min:1|max:10',
            'score_relevance' => 'sometimes|integer|min:1|max:10',
            'comments' => 'sometimes|string',
            'recommendation' => 'sometimes|in:accept,reject,revise'
        ]);

        $review->update($validated);

        return response()->json([
            'message' => 'Review updated successfully',
            'review' => $review
        ]);
    }

    // Get reviews for a submission (Organizer only)
    public function getSubmissionReviews($submissionId)
    {
        $reviews = Review::whereHas('assignment', function($q) use ($submissionId) {
            $q->where('submission_id', $submissionId);
        })->with('assignment.reviewer')->get();

        return response()->json($reviews);
    }
}