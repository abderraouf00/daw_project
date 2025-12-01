<?php
namespace App\Http\Controllers;

use App\Models\Question;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    // Get all questions for a session
    public function index($sessionId)
    {
        $questions = Question::where('session_id', $sessionId)
            ->with('user')
            ->orderBy('votes', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($questions);
    }

    // Ask a question
    public function store(Request $request)
    {
        $validated = $request->validate([
            'session_id' => 'required|exists:sessions,id',
            'question' => 'required|string'
        ]);

        $question = Question::create([
            'session_id' => $validated['session_id'],
            'user_id' => $request->user()->id,
            'question' => $validated['question'],
            'votes' => 0,
            'is_answered' => false
        ]);

        return response()->json([
            'message' => 'Question submitted successfully',
            'question' => $question->load('user')
        ], 201);
    }

    // Vote for a question
    public function vote($id)
    {
        $question = Question::findOrFail($id);
        $question->increment('votes');

        return response()->json([
            'message' => 'Vote added',
            'question' => $question
        ]);
    }

    // Mark question as answered (Session chair only)
    public function markAsAnswered($id)
    {
        $question = Question::findOrFail($id);
        $question->update(['is_answered' => true]);

        return response()->json([
            'message' => 'Question marked as answered',
            'question' => $question
        ]);
    }

    // Delete question
    public function destroy($id)
    {
        $question = Question::findOrFail($id);

        // Check authorization
        if ($question->user_id != auth()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $question->delete();

        return response()->json(['message' => 'Question deleted successfully']);
    }
}