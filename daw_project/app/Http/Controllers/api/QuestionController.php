<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Session;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function index(Session $session)
    {
        $questions = $session->questions()
                            ->with('user:id,name')
                            ->orderBy('votes', 'desc')
                            ->orderBy('created_at', 'desc')
                            ->get();

        return response()->json($questions);
    }

    public function store(Request $request, Session $session)
    {
        $request->validate([
            'question' => 'required|string|max:1000'
        ]);

        $question = $session->questions()->create([
            'user_id' => auth()->id(),
            'question' => $request->question,
            'votes' => 0,
        ]);

        return response()->json(['message' => 'Question posée', 'question' => $question], 201);
    }

    public function vote(Question $question)
    {
        $question->increment('votes');
        return response()->json(['message' => 'Vote enregistré', 'votes' => $question->votes]);
    }

    public function answer(Request $request, Question $question)
    {
        $request->validate(['answer' => 'required|string']);

        $question->update([
            'answer' => $request->answer,
            'is_answered' => true
        ]);

        return response()->json(['message' => 'Réponse ajoutée', 'question' => $question]);
    }
}