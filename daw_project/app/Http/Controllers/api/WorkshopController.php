<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Workshop;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WorkshopController extends Controller
{
    /**
     * Liste des workshops d'un événement
     */
    public function index(Event $event)
    {
        $workshops = Workshop::where('event_id', $event->id)
                            ->with(['leader:id,name,institution,photo', 'event:id,title'])
                            ->withCount('participants')
                            ->orderBy('date')
                            ->orderBy('start_time')
                            ->get();

        // Ajouter des infos pour l'utilisateur connecté
        if (auth()->check()) {
            $workshops->each(function ($workshop) {
                $workshop->is_registered = $workshop->participants()
                    ->where('users.id', auth()->id())
                    ->exists();
                $workshop->is_full = $workshop->isFull();
                $workshop->is_leader = $workshop->leader_id === auth()->id();
            });
        }

        return response()->json([
            'event' => $event->only(['id', 'title']),
            'workshops' => $workshops
        ]);
    }

    /**
     * Créer un nouveau workshop
     */
    public function store(Request $request, Event $event)
    {
        // Vérifier l'autorisation (Organisateur ou Workshop Leader)
        $user = auth()->user();
        if (!$user->isOrganizerOf($event->id) && !$user->hasRole('workshop_leader') && !$user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Non autorisé. Vous devez être organisateur ou animateur de workshop.'
            ], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'date' => 'required|date|after_or_equal:' . $event->start_date . '|before_or_equal:' . $event->end_date,
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'room' => 'nullable|string|max:100',
            'max_participants' => 'required|integer|min:1|max:500',
            'materials' => 'nullable|array',
            'materials.*' => 'string|max:500',
        ]);

        $workshop = Workshop::create([
            'event_id' => $event->id,
            'leader_id' => $user->id,
            'title' => $request->title,
            'description' => $request->description,
            'date' => $request->date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'room' => $request->room,
            'max_participants' => $request->max_participants,
            'materials' => $request->materials ?? [],
        ]);

        // Assigner le rôle workshop_leader si nécessaire
        if (!$user->hasRole('workshop_leader')) {
            $user->assignRole('workshop_leader');
        }

        return response()->json([
            'message' => 'Workshop créé avec succès',
            'workshop' => $workshop->load('leader')
        ], 201);
    }

    /**
     * Afficher les détails d'un workshop
     */
    public function show(Workshop $workshop)
    {
        $workshop->load([
            'event:id,title,start_date,end_date,location',
            'leader:id,name,email,institution,research_field,photo',
            'participants:id,name,email,institution'
        ]);

        $workshop->loadCount('participants');

        // Infos pour l'utilisateur connecté
        if (auth()->check()) {
            $workshop->is_registered = $workshop->participants()
                ->where('users.id', auth()->id())
                ->exists();
            $workshop->is_full = $workshop->isFull();
            $workshop->is_leader = $workshop->leader_id === auth()->id();
            $workshop->can_register = !$workshop->is_registered && 
                                     !$workshop->is_full && 
                                     auth()->user()->isRegisteredToEvent($workshop->event_id);
        }

        return response()->json($workshop);
    }

    /**
     * Mettre à jour un workshop
     */
    public function update(Request $request, Workshop $workshop)
    {
        $user = auth()->user();

        // Seul le leader ou l'organisateur peut modifier
        if ($workshop->leader_id !== $user->id && 
            !$user->isOrganizerOf($workshop->event_id) && 
            !$user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Non autorisé'
            ], 403);
        }

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'date' => 'sometimes|date',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'room' => 'nullable|string|max:100',
            'max_participants' => 'sometimes|integer|min:1|max:500',
        ]);

        // Vérifier que le nouveau max_participants n'est pas inférieur au nombre actuel
        if ($request->has('max_participants')) {
            $currentParticipants = $workshop->participants()->count();
            if ($request->max_participants < $currentParticipants) {
                return response()->json([
                    'message' => "Impossible de réduire la capacité. {$currentParticipants} participants déjà inscrits."
                ], 422);
            }
        }

        $workshop->update($request->only([
            'title', 'description', 'date', 'start_time', 
            'end_time', 'room', 'max_participants'
        ]));

        return response()->json([
            'message' => 'Workshop mis à jour avec succès',
            'workshop' => $workshop->fresh()->load('leader')
        ]);
    }

    /**
     * Supprimer un workshop
     */
    public function destroy(Workshop $workshop)
    {
        $user = auth()->user();

        // Seul le leader ou l'organisateur peut supprimer
        if ($workshop->leader_id !== $user->id && 
            !$user->isOrganizerOf($workshop->event_id) && 
            !$user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Non autorisé'
            ], 403);
        }

        // Notifier tous les participants inscrits
        $participants = $workshop->participants;
        foreach ($participants as $participant) {
            $participant->sendNotification(
                'workshop_cancelled',
                "Le workshop '{$workshop->title}' a été annulé",
                ['workshop_id' => $workshop->id]
            );
        }

        $workshop->delete();

        return response()->json([
            'message' => 'Workshop supprimé avec succès'
        ]);
    }

    /**
     * S'inscrire à un workshop
     */
    public function register(Workshop $workshop)
    {
        $user = auth()->user();

        // Vérifier que l'utilisateur est inscrit à l'événement
        if (!$user->isRegisteredToEvent($workshop->event_id)) {
            return response()->json([
                'message' => 'Vous devez d\'abord vous inscrire à l\'événement'
            ], 422);
        }

        // Vérifier que le workshop n'est pas complet
        if ($workshop->isFull()) {
            return response()->json([
                'message' => 'Ce workshop est complet'
            ], 422);
        }

        // Vérifier que l'utilisateur n'est pas déjà inscrit
        if ($workshop->participants()->where('users.id', $user->id)->exists()) {
            return response()->json([
                'message' => 'Vous êtes déjà inscrit à ce workshop'
            ], 422);
        }

        // Inscrire l'utilisateur
        $workshop->participants()->attach($user->id);

        // Notifier le leader du workshop
        $workshop->leader->sendNotification(
            'workshop_new_participant',
            "Nouvelle inscription au workshop '{$workshop->title}' : {$user->name}",
            ['workshop_id' => $workshop->id, 'user_id' => $user->id]
        );

        return response()->json([
            'message' => 'Inscription au workshop réussie',
            'workshop' => $workshop->fresh()->load('participants')
        ]);
    }

    /**
     * Se désinscrire d'un workshop
     */
    public function unregister(Workshop $workshop)
    {
        $user = auth()->user();

        // Vérifier que l'utilisateur est inscrit
        if (!$workshop->participants()->where('users.id', $user->id)->exists()) {
            return response()->json([
                'message' => 'Vous n\'êtes pas inscrit à ce workshop'
            ], 422);
        }

        // Désinscrire l'utilisateur
        $workshop->participants()->detach($user->id);

        return response()->json([
            'message' => 'Désinscription réussie'
        ]);
    }

    /**
     * Ajouter/Modifier les supports (PDF, liens, vidéos)
     */
    public function uploadMaterials(Request $request, Workshop $workshop)
    {
        $user = auth()->user();

        // Seul le leader peut ajouter des supports
        if ($workshop->leader_id !== $user->id && !$user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Seul l\'animateur peut ajouter des supports'
            ], 403);
        }

        $request->validate([
            'materials' => 'required|array',
            'materials.*' => 'string|max:500', // URLs ou chemins
        ]);

        // Mettre à jour les supports
        $currentMaterials = $workshop->materials ?? [];
        $newMaterials = array_merge($currentMaterials, $request->materials);

        $workshop->update(['materials' => $newMaterials]);

        // Notifier tous les participants
        $participants = $workshop->participants;
        foreach ($participants as $participant) {
            $participant->sendNotification(
                'workshop_materials_added',
                "Nouveaux supports disponibles pour le workshop '{$workshop->title}'",
                ['workshop_id' => $workshop->id]
            );
        }

        return response()->json([
            'message' => 'Supports ajoutés avec succès',
            'materials' => $workshop->materials
        ]);
    }

    /**
     * Supprimer un support
     */
    public function deleteMaterial(Request $request, Workshop $workshop)
    {
        $user = auth()->user();

        if ($workshop->leader_id !== $user->id && !$user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Non autorisé'
            ], 403);
        }

        $request->validate([
            'material_index' => 'required|integer|min:0'
        ]);

        $materials = $workshop->materials ?? [];
        
        if (!isset($materials[$request->material_index])) {
            return response()->json([
                'message' => 'Support introuvable'
            ], 404);
        }

        // Supprimer le support
        unset($materials[$request->material_index]);
        $materials = array_values($materials); // Réindexer

        $workshop->update(['materials' => $materials]);

        return response()->json([
            'message' => 'Support supprimé',
            'materials' => $workshop->materials
        ]);
    }

    /**
     * Liste des participants (Leader uniquement)
     */
    public function participants(Workshop $workshop)
    {
        $user = auth()->user();

        if ($workshop->leader_id !== $user->id && 
            !$user->isOrganizerOf($workshop->event_id) && 
            !$user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Non autorisé'
            ], 403);
        }

        $participants = $workshop->participants()
                                ->select('users.id', 'users.name', 'users.email', 'users.institution', 'users.phone')
                                ->withPivot('created_at')
                                ->orderBy('workshop_registrations.created_at')
                                ->get();

        return response()->json([
            'workshop' => $workshop->only(['id', 'title', 'max_participants']),
            'participants_count' => $participants->count(),
            'available_spots' => $workshop->max_participants - $participants->count(),
            'is_full' => $workshop->isFull(),
            'participants' => $participants
        ]);
    }

    /**
     * Mes workshops (en tant que leader)
     */
    public function myWorkshops()
    {
        $workshops = Workshop::where('leader_id', auth()->id())
                            ->with(['event:id,title,start_date', 'participants:id,name'])
                            ->withCount('participants')
                            ->latest()
                            ->paginate(10);

        return response()->json($workshops);
    }

    /**
     * Workshops auxquels je suis inscrit
     */
    public function registeredWorkshops()
    {
        $user = auth()->user();
        
        $workshops = $user->registeredWorkshops()
                         ->with(['event:id,title,location', 'leader:id,name,institution'])
                         ->withCount('participants')
                         ->orderBy('date')
                         ->get();

        return response()->json($workshops);
    }
}