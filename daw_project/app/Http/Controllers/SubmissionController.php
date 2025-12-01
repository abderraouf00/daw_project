<?php
namespace App\Http\Controllers;

use App\Models\Submission;
use App\Models\Event;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SubmissionController extends Controller
{
    // Get all submissions for an event
    public function index($eventId)
    {
        $submissions = Submission::where('event_id', $eventId)
            ->with(['mainAuthor', 'coAuthors', 'reviewAssignments.reviewer'])
            ->paginate(20);

        return response()->json($submissions);
    }

    // Get single submission
    public function show($id)
    {
        $submission = Submission::with([
            'mainAuthor',
            'coAuthors',
            'reviewAssignments.review',
            'sessions'
        ])->findOrFail($id);

        return response()->json($submission);
    }

    // Create new submission
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'title' => 'required|string|max:255',
            'abstract' => 'required|string',
            'keywords' => 'required|string',
            'type' => 'required|in:oral,poster,affiche',
            'file' => 'required|file|mimes:pdf|max:10240',
            'co_authors' => 'nullable|array',
            'co_authors.*' => 'exists:users,id'
        ]);

        // Upload file
        $filePath = $request->file('file')->store('submissions', 'public');

        $submission = Submission::create([
            'event_id' => $validated['event_id'],
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'abstract' => $validated['abstract'],
            'keywords' => $validated['keywords'],
            'type' => $validated['type'],
            'file_path' => $filePath,
            'status' => 'pending'
        ]);

        // Attach co-authors
        if (!empty($validated['co_authors'])) {
            $submission->coAuthors()->attach($validated['co_authors']);
        }

        // Notify organizer
        $event = Event::find($validated['event_id']);
        Notification::create([
            'user_id' => $event->created_by,
            'event_id' => $event->id,
            'type' => 'new_submission',
            'title' => 'New Submission',
            'message' => "A new submission '{$submission->title}' has been submitted.",
            'is_read' => false
        ]);

        return response()->json([
            'message' => 'Submission created successfully',
            'submission' => $submission->load('coAuthors')
        ], 201);
    }

    // Update submission
    public function update(Request $request, $id)
    {
        $submission = Submission::findOrFail($id);

        // Check authorization
        if ($submission->user_id != $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if submission can be edited
        if ($submission->status != 'pending') {
            return response()->json([
                'message' => 'Cannot edit submission after review'
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'abstract' => 'sometimes|string',
            'keywords' => 'sometimes|string',
            'type' => 'sometimes|in:oral,poster,affiche',
            'file' => 'nullable|file|mimes:pdf|max:10240',
            'co_authors' => 'nullable|array',
            'co_authors.*' => 'exists:users,id'
        ]);

        // Update file if provided
        if ($request->hasFile('file')) {
            Storage::disk('public')->delete($submission->file_path);
            $validated['file_path'] = $request->file('file')->store('submissions', 'public');
        }

        $submission->update($validated);

        // Update co-authors
        if (isset($validated['co_authors'])) {
            $submission->coAuthors()->sync($validated['co_authors']);
        }

        return response()->json([
            'message' => 'Submission updated successfully',
            'submission' => $submission->load('coAuthors')
        ]);
    }

    // Delete submission
    public function destroy($id)
    {
        $submission = Submission::findOrFail($id);

        // Check authorization
        if ($submission->user_id != auth()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete file
        Storage::disk('public')->delete($submission->file_path);

        $submission->delete();

        return response()->json(['message' => 'Submission deleted successfully']);
    }

    // Get my submissions
    public function mySubmissions(Request $request)
    {
        $submissions = Submission::where('user_id', $request->user()->id)
            ->orWhereHas('coAuthors', function($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            })
            ->with(['event', 'reviewAssignments.review'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($submissions);
    }

    // Update submission status (Organizer only)
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,accepted,rejected,revision'
        ]);

        $submission = Submission::findOrFail($id);
        $submission->update(['status' => $validated['status']]);

        // Notify author
        Notification::create([
            'user_id' => $submission->user_id,
            'event_id' => $submission->event_id,
            'type' => 'submission_status',
            'title' => 'Submission Status Updated',
            'message' => "Your submission '{$submission->title}' has been {$validated['status']}.",
            'is_read' => false
        ]);

        return response()->json([
            'message' => 'Status updated successfully',
            'submission' => $submission
        ]);
    }
}