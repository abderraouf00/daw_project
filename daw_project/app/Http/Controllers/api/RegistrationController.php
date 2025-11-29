<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RegistrationController extends Controller
{
    /**
     * S'inscrire à un événement
     */
    public function register(Request $request, Event $event)
    {
        $user = auth()->user();

        // Vérifier que l'événement est publié
        if ($event->status !== 'published') {
            return response()->json([
                'message' => 'Cet événement n\'est pas encore ouvert aux inscriptions'
            ], 422);
        }

        // Vérifier que l'événement n'est pas complet
        if ($event->max_participants) {
            $currentRegistrations = $event->registrations()->count();
            if ($currentRegistrations >= $event->max_participants) {
                return response()->json([
                    'message' => 'L\'événement est complet'
                ], 422);
            }
        }

        // Vérifier que l'utilisateur n'est pas déjà inscrit
        if ($user->isRegisteredToEvent($event->id)) {
            return response()->json([
                'message' => 'Vous êtes déjà inscrit à cet événement'
            ], 422);
        }

        $request->validate([
            'type' => 'required|in:participant,speaker,author',
        ]);

        // Créer l'inscription
        $registration = Registration::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'type' => $request->type,
            'payment_status' => 'pending',
            'registration_date' => now(),
            'badge_code' => 'BADGE-' . strtoupper(Str::random(8)),
        ]);

        // Assigner le rôle participant si nécessaire
        if (!$user->hasRole('participant')) {
            $user->assignRole('participant');
        }

        // Notifier l'organisateur
        $event->organizer->sendNotification(
            'new_registration',
            "Nouvelle inscription à votre événement : {$event->title}",
            ['event_id' => $event->id, 'user_name' => $user->name]
        );

        return response()->json([
            'message' => 'Inscription réussie',
            'registration' => $registration->load('event:id,title,start_date,location')
        ], 201);
    }

    /**
     * Mes inscriptions
     */
    public function myRegistrations()
    {
        $registrations = Registration::with('event:id,title,start_date,end_date,location,banner_image')
                                     ->where('user_id', auth()->id())
                                     ->latest('registration_date')
                                     ->paginate(10);

        return response()->json($registrations);
    }

    /**
     * Toutes les inscriptions d'un événement (Organisateur)
     */
    public function eventRegistrations(Event $event)
    {
        $user = auth()->user();

        if (!$user->isOrganizerOf($event->id) && !$user->isSuperAdmin()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $registrations = Registration::with('user:id,name,email,institution,phone')
                                     ->where('event_id', $event->id)
                                     ->when(request('type'), function($q) {
                                         $q->where('type', request('type'));
                                     })
                                     ->when(request('payment_status'), function($q) {
                                         $q->where('payment_status', request('payment_status'));
                                     })
                                     ->latest('registration_date')
                                     ->paginate(50);

        return response()->json([
            'event' => $event->only(['id', 'title', 'max_participants']),
            'total_registrations' => $event->registrations()->count(),
            'by_type' => [
                'participant' => $event->registrations()->where('type', 'participant')->count(),
                'speaker' => $event->registrations()->where('type', 'speaker')->count(),
                'author' => $event->registrations()->where('type', 'author')->count(),
            ],
            'by_payment' => [
                'pending' => $event->registrations()->where('payment_status', 'pending')->count(),
                'paid' => $event->registrations()->where('payment_status', 'paid')->count(),
            ],
            'registrations' => $registrations
        ]);
    }

    /**
     * Mettre à jour le statut de paiement (Organisateur)
     */
    public function updatePayment(Request $request, Registration $registration)
    {
        $user = auth()->user();

        if (!$user->isOrganizerOf($registration->event_id) && !$user->isSuperAdmin()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $request->validate([
            'payment_status' => 'required|in:pending,paid'
        ]);

        $registration->update([
            'payment_status' => $request->payment_status
        ]);

        // Notifier l'utilisateur
        if ($request->payment_status === 'paid') {
            $registration->user->sendNotification(
                'payment_confirmed',
                "Votre paiement pour l'événement {$registration->event->title} a été confirmé",
                ['registration_id' => $registration->id]
            );
        }

        return response()->json([
            'message' => 'Statut de paiement mis à jour',
            'registration' => $registration
        ]);
    }

    /**
     * Générer un badge (Organisateur)
     */
    public function generateBadge(Registration $registration)
    {
        $user = auth()->user();

        if (!$user->isOrganizerOf($registration->event_id) && !$user->isSuperAdmin()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Données du badge
        $badge = [
            'code' => $registration->badge_code,
            'event' => $registration->event->title,
            'participant' => $registration->user->name,
            'institution' => $registration->user->institution,
            'type' => $registration->type,
            'registration_date' => $registration->registration_date->format('Y-m-d'),
        ];

        // TODO: Générer un vrai badge PDF avec DomPDF
        // Pour l'instant, retourner les données JSON

        return response()->json($badge);
    }
}