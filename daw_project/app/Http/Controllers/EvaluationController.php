<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\Submission;
use Illuminate\Http\Request;

class EvaluationController extends Controller
{
    /**
     * Mes évaluations (comité scientifique)
     */
    public function myEvaluations()
    {
        $evaluations = Evaluation::with(['submission.event:id,title', 'submission.author:id,name'])
                                 ->where('evaluator_id', auth()->id())
                                 ->latest()
                                 ->paginate(15);

        return response()->json($evaluations);
    }

    /**
     * Évaluer une soumission
     */
    public function evaluate(Request $request, Submission $submission)
    {
        $user = auth()->user();

        // Vérifier que l'utilisateur est membre du comité de cet événement
        if (!$user->isCommitteeMemberOf($submission->event_id)) {
            return response()->json([
                'message' => 'Vous n\'êtes pas membre du comité scientifique de cet événement'
            ], 403);
        }

        // Vérifier qu'il n'a pas déjà évalué
        if ($submission->evaluations()->where('evaluator_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'Vous avez déjà évalué cette soumission'
            ], 422);
        }

        $request->validate([
            'score' => 'required|numeric|min:0|max:10',
            'relevance_score' => 'nullable|integer|min:1|max:5',
            'quality_score' => 'nullable|integer|min:1|max:5',
            'originality_score' => 'nullable|integer|min:1|max:5',
            'comments' => 'nullable|string|max:2000',
            'recommendation' => 'required|in:accept,reject,revision',
        ]);

        $evaluation = Evaluation::create([
            'submission_id' => $submission->id,
            'evaluator_id' => $user->id,
            'score' => $request->score,
            'relevance_score' => $request->relevance_score,
            'quality_score' => $request->quality_score,
            'originality_score' => $request->originality_score,
            'comments' => $request->comments,
            'recommendation' => $request->recommendation,
        ]);

        // Mettre à jour le statut de la soumission
        if ($submission->status === 'pending') {
            $submission->update(['status' => 'under_review']);
        }

        // Notifier l'organisateur
        $submission->event->organizer->sendNotification(
            'new_evaluation',
            "Nouvelle évaluation pour la soumission : {$submission->title}",
            ['submission_id' => $submission->id, 'evaluation_id' => $evaluation->id]
        );

        return response()->json([
            'message' => 'Évaluation enregistrée avec succès',
            'evaluation' => $evaluation
        ], 201);
    }

    /**
     * Mettre à jour une évaluation
     */
    public function update(Request $request, Evaluation $evaluation)
    {
        // Seul l'évaluateur peut modifier
        if ($evaluation->evaluator_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $request->validate([
            'score' => 'sometimes|numeric|min:0|max:10',
            'relevance_score' => 'nullable|integer|min:1|max:5',
            'quality_score' => 'nullable|integer|min:1|max:5',
            'originality_score' => 'nullable|integer|min:1|max:5',
            'comments' => 'nullable|string|max:2000',
            'recommendation' => 'sometimes|in:accept,reject,revision',
        ]);

        $evaluation->update($request->all());

        return response()->json([
            'message' => 'Évaluation mise à jour avec succès',
            'evaluation' => $evaluation
        ]);
    }

    /**
     * Toutes les évaluations d'une soumission (Organisateur)
     */
    public function submissionEvaluations(Submission $submission)
    {
        $user = auth()->user();

        // Vérifier l'autorisation
        if (!$user->isOrganizerOf($submission->event_id) && !$user->isSuperAdmin()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $evaluations = $submission->evaluations()
                                  ->with('evaluator:id,name,institution')
                                  ->get();

        return response()->json([
            'submission' => $submission->load('author:id,name'),
            'evaluations' => $evaluations,
            'average_score' => $submission->average_score,
            'majority_recommendation' => $submission->getMajorityRecommendation(),
        ]);
    }

    /**
     * Générer un rapport d'évaluation (Organisateur)
     */
    public function generateReport(Submission $submission)
    {
        $user = auth()->user();

        if (!$user->isOrganizerOf($submission->event_id) && !$user->isSuperAdmin()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $evaluations = $submission->evaluations()->with('evaluator:id,name')->get();

        $report = [
            'submission' => [
                'id' => $submission->id,
                'title' => $submission->title,
                'type' => $submission->type,
                'author' => $submission->author->name,
            ],
            'statistics' => [
                'total_evaluations' => $evaluations->count(),
                'average_score' => $submission->average_score,
                'average_relevance' => $evaluations->avg('relevance_score'),
                'average_quality' => $evaluations->avg('quality_score'),
                'average_originality' => $evaluations->avg('originality_score'),
            ],
            'recommendations' => [
                'accept' => $evaluations->where('recommendation', 'accept')->count(),
                'reject' => $evaluations->where('recommendation', 'reject')->count(),
                'revision' => $evaluations->where('recommendation', 'revision')->count(),
                'majority' => $submission->getMajorityRecommendation(),
            ],
            'evaluations' => $evaluations->map(function($eval) {
                return [
                    'evaluator' => $eval->evaluator->name,
                    'score' => $eval->score,
                    'recommendation' => $eval->recommendation,
                    'comments' => $eval->comments,
                    'date' => $eval->created_at->format('Y-m-d H:i'),
                ];
            }),
        ];

        return response()->json($report);
    }
}