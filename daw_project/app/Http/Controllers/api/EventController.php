<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EventController extends Controller
{
    /**
     * Liste de tous les événements (publics et publiés)
     */
    public function index(Request $request)
    {
        $query = Event::with(['organizer:id,name,institution', 'committeeMembers:id,name'])
                      ->withCount(['submissions', 'registrations']);

        // Filtres
        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            // Par défaut, afficher seulement les événements publiés
            $query->where('status', 'published');
        }

        if ($request->has('upcoming')) {
            $query->where('start_date', '>', now());
        }

        if ($request->has('ongoing')) {
            $query->where('start_date', '<=', now())
                  ->where('end_date', '>=', now());
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('theme', 'like', "%{$search}%");
            });
        }

        $events = $query->orderBy('start_date', 'desc')->paginate(10);

        return response()->json($events);
    }

    /**
     * Créer un nouvel événement (Organisateur seulement)
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'theme' => 'nullable|string|max:255',
            'location' => 'required|string|max:255',
            'start_date' => 'required|date|after:today',
            'end_date' => 'required|date|after:start_date',
            'submission_deadline' => 'nullable|date|before:start_date',
            'contact_email' => 'required|email',
            'contact_phone' => 'nullable|string|max:20',
            'max_participants' => 'nullable|integer|min:1',
            'banner_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'committee_member_ids' => 'nullable|array',
            'committee_member_ids.*' => 'exists:users,id',
        ]);

        $data = $request->except(['banner_image', 'committee_member_ids']);
        $data['organizer_id'] = auth()->id();
        $data['status'] = 'draft';

        // Upload de la bannière
        if ($request->hasFile('banner_image')) {
            $data['banner_image'] = $request->file('banner_image')->store('events/banners', 'public');
        }

        $event = Event::create($data);

        // Ajouter les membres du comité scientifique
        if ($request->has('committee_member_ids')) {
            $event->committeeMembers()->attach($request->committee_member_ids);
        }

        return response()->json([
            'message' => 'Événement créé avec succès',
            'event' => $event->load(['organizer', 'committeeMembers'])
        ], 201);
    }

    /**
     * Afficher les détails d'un événement
     */
    public function show(Event $event)
    {
        $event->load([
            'organizer:id,name,email,institution,photo',
            'committeeMembers:id,name,institution,research_field',
            'sessions.submissions',
            'workshops'
        ]);

        $event->loadCount(['submissions', 'registrations']);

        // Vérifier si l'utilisateur connecté est inscrit
        if (auth()->check()) {
            $event->is_registered = auth()->user()->isRegisteredToEvent($event->id);
            $event->is_organizer = auth()->user()->isOrganizerOf($event->id);
            $event->is_committee_member = auth()->user()->isCommitteeMemberOf($event->id);
        }

        return response()->json($event);
    }

    /**
     * Mettre à jour un événement
     */
    public function update(Request $request, Event $event)
    {
        // Vérifier que l'utilisateur est bien l'organisateur
        if ($event->organizer_id !== auth()->id() && !auth()->user()->isSuperAdmin()) {
            return response()->json([
                'message' => 'Non autorisé'
            ], 403);
        }

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'theme' => 'nullable|string|max:255',
            'location' => 'sometimes|string|max:255',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'submission_deadline' => 'nullable|date',
            'contact_email' => 'sometimes|email',
            'contact_phone' => 'nullable|string|max:20',
            'max_participants' => 'nullable|integer|min:1',
            'banner_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'status' => 'sometimes|in:draft,published,ongoing,completed,cancelled',
        ]);

        $data = $request->except('banner_image');

        // Upload nouvelle bannière
        if ($request->hasFile('banner_image')) {
            // Supprimer l'ancienne
            if ($event->banner_image) {
                Storage::disk('public')->delete($event->banner_image);
            }
            $data['banner_image'] = $request->file('banner_image')->store('events/banners', 'public');
        }

        $event->update($data);

        return response()->json([
            'message' => 'Événement mis à jour avec succès',
            'event' => $event->fresh()->load(['organizer', 'committeeMembers'])
        ]);
    }

    /**
     * Supprimer un événement
     */
    public function destroy(Event $event)
    {
        // Vérifier que l'utilisateur est bien l'organisateur
        if ($event->organizer_id !== auth()->id() && !auth()->user()->isSuperAdmin()) {
            return response()->json([
                'message' => 'Non autorisé'
            ], 403);
        }

        // Supprimer la bannière
        if ($event->banner_image) {
            Storage::disk('public')->delete($event->banner_image);
        }

        $event->delete();

        return response()->json([
            'message' => 'Événement supprimé avec succès'
        ]);
    }

    /**
     * Publier un événement
     */
    public function publish(Event $event)
    {
        if ($event->organizer_id !== auth()->id() && !auth()->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $event->update(['status' => 'published']);

        return response()->json([
            'message' => 'Événement publié avec succès',
            'event' => $event
        ]);
    }

    /**
     * Ajouter un membre au comité scientifique
     */
    public function addCommitteeMember(Request $request, Event $event)
    {
        if ($event->organizer_id !== auth()->id() && !auth()->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $user = User::findOrFail($request->user_id);

        // Vérifier que l'utilisateur a le rôle committee_member
        if (!$user->hasRole('committee_member')) {
            $user->assignRole('committee_member');
        }

        // Ajouter au comité (éviter les doublons)
        if (!$event->committeeMembers()->where('users.id', $user->id)->exists()) {
            $event->committeeMembers()->attach($user->id);
        }

        return response()->json([
            'message' => 'Membre ajouté au comité scientifique',
            'committee' => $event->committeeMembers
        ]);
    }

    /**
     * Retirer un membre du comité scientifique
     */
    public function removeCommitteeMember(Event $event, User $user)
    {
        if ($event->organizer_id !== auth()->id() && !auth()->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $event->committeeMembers()->detach($user->id);

        return response()->json([
            'message' => 'Membre retiré du comité scientifique'
        ]);
    }
}