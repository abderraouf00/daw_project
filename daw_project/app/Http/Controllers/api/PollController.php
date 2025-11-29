<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use App\Models\Event;
use Illuminate\Http\Request;

class PollController extends Controller
{
    public function index(Event $event)
    {
        $polls = $event->polls()->where('is_active', true)->get();
        return response()->json($polls);
    }

    public function store(Request $request, Event $event)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'question' => 'required|string',
            'options' => 'required|array|min:2',
            'options.*' => 'string',
        ]);

        $poll = $event->polls()->create($request->all());

        return response()->json(['message' => 'Sondage créé', 'poll' => $poll], 201);
    }

    public function show(Poll $poll)
    {
        $poll->load('votes');
        return response()->json($poll);
    }

    public function update(Request $request, Poll $poll)
    {
        $poll->update($request->all());
        return response()->json(['message' => 'Sondage mis à jour', 'poll' => $poll]);
    }

    public function destroy(Poll $poll)
    {
        $poll->delete();
        return response()->json(['message' => 'Sondage supprimé']);
    }

    public function vote(Request $request, Poll $poll)
    {
        $request->validate([
            'selected_option' => 'required|string'
        ]);

        $user = auth()->user();

        if ($poll->votes()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Vous avez déjà voté'], 422);
        }

        $poll->votes()->create([
            'user_id' => $user->id,
            'selected_option' => $request->selected_option
        ]);

        return response()->json(['message' => 'Vote enregistré']);
    }

    public function results(Poll $poll)
    {
        return response()->json(['results' => $poll->results]);
    }
}