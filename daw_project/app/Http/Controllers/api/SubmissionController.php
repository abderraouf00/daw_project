<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SubmissionController extends Controller
{
    /**
     * Mes soumissions (auteur connecté)
     */
    public function mySubmissions()
    {
        $submissions = Submission::with(['event:id,title,start_date', 'coAuthors'])
                                 ->where('user_id', auth()->id())
                                 ->withCount('evaluations')
                                 ->latest()
                                 ->paginate(10);

        return response()->json($submissions);
    }

    /**
     * Toutes les soumissions d'un événement (Organisateur)
     */
    public function eventSubmissions(Event $event)
    {
        // Vérifier l'autorisation
        $user = auth()->user();
        if (!$user->isOrganizerOf($event->id) && !$user->isCommitteeMemberOf($event->id) && !$user->isSuperAdmin()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $submissions = Submission::with(['author:id,name,email,institution', 'coAuthors', 'evaluations.evaluator'])
                                 ->where('event_id', $event->id)
                                 ->withCount('evaluations')
                                 ->when(request('status'), function($q) {
                                     $q->where('status', request('status'));
                                 })
                                 ->when(request('type'), function($q) {
                                     $q->where('type', request('type'));
                                 })
                                 ->latest()
                                 ->paginate(15);

        return response()->json($submissions);
    }

    /**
     * Créer une nouvelle soumission
     */
    public function store(Request $request)
    {
        $request->validate([
            'event_id' => 'required|exists:events,id',
            'title' => 'required|string|max:255',
            'abstract' => 'required|string|max:5000',
            'keywords' => 'required|array|min:1|max:10',
            'keywords.*' => 'string|max:50',
            'type' => 'required|in:oral,poster,affichee',
            'co_authors' => 'nullable|array|max:10',
            'co_authors.*.name' => 'required|string|max:255',
            'co_authors.*.email' => 'required|email',
            'co_authors.*.institution' => 'nullable|string|max:255',
        ]);

        // Vérifier que l'événement accepte encore des soumissions
        $event = Event::findOrFail($request->event_id);
        
        if (!$event->isSubmissionOpen()) {
            return response()->json([
                'message' => 'La date limite de soumission est dépassée'
            ], 422);
        }

        // Créer la soumission
        $submission = Submission::create([
            'event_id' => $request->event_id,
            'user_id' => auth()->id(),
            'title' => $request->title,
            'abstract' => $request->abstract,
            'keywords' => $request->keywords,
            'type' => $request->type,
            'status' => 'pending',
        ]);

        // Ajouter les co-auteurs
        if ($request->has('co_authors')) {
            foreach ($request->co_authors as $index => $coAuthor) {
                $submission->coAuthors()->create([
                    'name' => $coAuthor['name'],
                    'email' => $coAuthor['email'],
                    'institution' => $coAuthor['institution'] ?? null,
                    'order' => $index + 1,
                ]);
            }
        }

        // Assigner le rôle author si nécessaire
        if (!auth()->user()->hasRole('author')) {
            auth()->user()->assignRole('author');
        }

        return response()->json([
            'message' => 'Soumission créée avec succès',
            'submission' => $submission->load(['coAuthors', 'event'])
        ], 201);
    }

    /**
     * Afficher une soumission
     */
    public function show(Submission $submission)
    {
        $user = auth()->user();

        // Vérifier l'autorisation
        $isAuthor = $submission->user_id === $user->id;
        $isOrganizer = $user->isOrganizerOf($submission->event_id);
        $isCommittee = $user->isCommitteeMemberOf($submission->event_id);

        if (!$isAuthor && !$isOrganizer && !$isCommittee && !$user->isSuperAdmin()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $submission->load([
            'event:id,title,start_date',
            'author:id,name,email,institution',
            'coAuthors',
            'evaluations.evaluator:id,name'
        ]);

        // Ajouter le score moyen si évalué
        if ($submission->evaluations->count() > 0) {
            $submission->average_score = $submission->average_score;
            $submission->majority_recommendation = $submission->getMajorityRecommendation();
        }

        return response()->json($submission);
    }

    /**
     * Mettre à jour une soumission (avant deadline)
     */
    public function update(Request $request, Submission $submission)
    {
        // Seul l'auteur peut modifier
        if ($submission->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Vérifier la deadline
        if (!$submission->event->isSubmissionOpen()) {
            return response()->json([
                'message' => 'Modification impossible après la date limite'
            ], 422);
        }

        // Ne pas modifier si déjà évalué
        if ($submission->status !== 'pending') {
            return response()->json([
                'message' => 'Impossible de modifier une soumission déjà évaluée'
            ], 422);
        }

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'abstract' => 'sometimes|string|max:5000',
            'keywords' => 'sometimes|array|min:1|max:10',
            'keywords.*' => 'string|max:50',
            'type' => 'sometimes|in:oral,poster,affichee',
        ]);

        $submission->update($request->only(['title', 'abstract', 'keywords', 'type']));

        return response()->json([
            'message' => 'Soumission mise à jour avec succès',
            'submission' => $submission->fresh()->load('coAuthors')
        ]);
    }

    /**
     * Supprimer une soumission
     */
    public function destroy(Submission $submission)
    {
        // Seul l'auteur peut supprimer
        if ($submission->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Supprimer le fichier PDF
        if ($submission->file_path) {
            Storage::disk('public')->delete($submission->file_path);
        }

        $submission->delete();

        return response()->json([
            'message' => 'Soumission supprimée avec succès'
        ]);
    }

    /**
     * Upload du fichier PDF
     */
    public function uploadFile(Request $request, Submission $submission)
    {
        // Vérifier l'autorisation
        if ($submission->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf|max:5120', // 5MB max
        ]);

        // Supprimer l'ancien fichier
        if ($submission->file_path) {
            Storage::disk('public')->delete($submission->file_path);
        }

        // Stocker le nouveau fichier
        $path = $request->file('file')->store('submissions', 'public');

        $submission->update(['file_path' => $path]);

        return response()->json([
            'message' => 'Fichier uploadé avec succès',
            'file_url' => Storage::url($path)
        ]);
    }

    /**
     * Supprimer le fichier PDF
     */
    public function deleteFile(Submission $submission)
    {
        if ($submission->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if ($submission->file_path) {
            Storage::disk('public')->delete($submission->file_path);
            $submission->update(['file_path' => null]);
        }

        return response()->json([
            'message' => 'Fichier supprimé avec succès'
        ]);
    }

    /**
     * Mettre à jour le statut (Organisateur)
     */
    public function updateStatus(Request $request, Submission $submission)
    {
        $user = auth()->user();
        
        if (!$user->isOrganizerOf($submission->event_id) && !$user->isSuperAdmin()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $request->validate([
            'status' => 'required|in:pending,under_review,accepted,rejected,revision',
            'admin_comments' => 'nullable|string',
        ]);

        $submission->update([
            'status' => $request->status,
            'admin_comments' => $request->admin_comments,
        ]);

        // Envoyer une notification à l'auteur
        $submission->author->sendNotification(
            'submission_status_updated',
            "Le statut de votre soumission '{$submission->title}' a été mis à jour : {$request->status}",
            ['submission_id' => $submission->id, 'status' => $request->status]
        );

        return response()->json([
            'message' => 'Statut mis à jour avec succès',
            'submission' => $submission
        ]);
    }

    /**
     * Assigner un évaluateur (Organisateur)
     */
    public function assignEvaluator(Request $request, Submission $submission)
    {
        $user = auth()->user();
        
        if (!$user->isOrganizerOf($submission->event_id) && !$user->isSuperAdmin()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $request->validate([
            'evaluator_id' => 'required|exists:users,id'
        ]);

        $evaluator = User::findOrFail($request->evaluator_id);

        // Vérifier que c'est un membre du comité
        if (!$evaluator->isCommitteeMemberOf($submission->event_id)) {
            return response()->json([
                'message' => 'Cet utilisateur n\'est pas membre du comité scientifique'
            ], 422);
        }

        // Vérifier qu'il n'a pas déjà évalué
        if ($submission->evaluations()->where('evaluator_id', $evaluator->id)->exists()) {
            return response()->json([
                'message' => 'Cet évaluateur a déjà évalué cette soumission'
            ], 422);
        }

        // Mettre à jour le statut si nécessaire
        if ($submission->status === 'pending') {
            $submission->update(['status' => 'under_review']);
        }

        // Envoyer une notification à l'évaluateur
        $evaluator->sendNotification(
            'evaluation_assigned',
            "Une nouvelle soumission vous a été assignée pour évaluation : {$submission->title}",
            ['submission_id' => $submission->id]
        );

        return response()->json([
            'message' => 'Évaluateur assigné avec succès'
        ]);
    }
}