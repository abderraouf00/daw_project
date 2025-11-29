<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Models\Event;
use App\Models\Submission;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function index(Event $event)
    {
        $sessions = $event->sessions()
                         ->with(['chair:id,name', 'submissions.author'])
                         ->orderBy('date')
                         ->orderBy('start_time')
                         ->get();

        return response()->json($sessions);
    }

    public function store(Request $request, Event $event)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'room' => 'nullable|string|max:100',
            'session_chair_id' => 'nullable|exists:users,id',
        ]);

        $session = $event->sessions()->create($request->all());

        return response()->json([
            'message' => 'Session créée avec succès',
            'session' => $session
        ], 201);
    }

    public function show(Session $session)
    {
        $session->load(['event:id,title', 'chair', 'submissions.author', 'questions']);
        return response()->json($session);
    }

    public function update(Request $request, Session $session)
    {
        $session->update($request->all());
        return response()->json(['message' => 'Session mise à jour', 'session' => $session]);
    }

    public function destroy(Session $session)
    {
        $session->delete();
        return response()->json(['message' => 'Session supprimée']);
    }

    public function assignSubmission(Request $request, Session $session)
    {
        $request->validate([
            'submission_id' => 'required|exists:submissions,id',
            'presentation_order' => 'nullable|integer'
        ]);

        $session->submissions()->attach($request->submission_id, [
            'presentation_order' => $request->presentation_order ?? 1
        ]);

        return response()->json(['message' => 'Communication assignée à la session']);
    }

    public function removeSubmission(Session $session, Submission $submission)
    {
        $session->submissions()->detach($submission->id);
        return response()->json(['message' => 'Communication retirée de la session']);
    }
}

// ==================== WorkshopController.php ====================
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Workshop;
use App\Models\Event;
use Illuminate\Http\Request;

class WorkshopController extends Controller
{
    public function index(Event $event)
    {
        $workshops = $event->workshops()
                          ->with('leader:id,name,institution')
                          ->withCount('participants')
                          ->get();

        return response()->json($workshops);
    }

    public function store(Request $request, Event $event)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'room' => 'nullable|string',
            'max_participants' => 'required|integer|min:1',
        ]);

        $workshop = $event->workshops()->create([
            ...$request->all(),
            'leader_id' => auth()->id()
        ]);

        return response()->json(['message' => 'Workshop créé', 'workshop' => $workshop], 201);
    }

    public function show(Workshop $workshop)
    {
        $workshop->load(['event:id,title', 'leader', 'participants']);
        return response()->json($workshop);
    }

    public function update(Request $request, Workshop $workshop)
    {
        $workshop->update($request->all());
        return response()->json(['message' => 'Workshop mis à jour', 'workshop' => $workshop]);
    }

    public function destroy(Workshop $workshop)
    {
        $workshop->delete();
        return response()->json(['message' => 'Workshop supprimé']);
    }

    public function register(Workshop $workshop)
    {
        $user = auth()->user();

        if ($workshop->isFull()) {
            return response()->json(['message' => 'Workshop complet'], 422);
        }

        if ($workshop->participants()->where('users.id', $user->id)->exists()) {
            return response()->json(['message' => 'Déjà inscrit'], 422);
        }

        $workshop->participants()->attach($user->id);

        return response()->json(['message' => 'Inscription au workshop réussie']);
    }

    public function unregister(Workshop $workshop)
    {
        $workshop->participants()->detach(auth()->id());
        return response()->json(['message' => 'Désinscription réussie']);
    }

    public function uploadMaterials(Request $request, Workshop $workshop)
    {
        $request->validate([
            'materials' => 'required|array',
            'materials.*' => 'string|url'
        ]);

        $workshop->update(['materials' => $request->materials]);

        return response()->json(['message' => 'Supports ajoutés']);
    }
}
?>