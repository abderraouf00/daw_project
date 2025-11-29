<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'institution',
        'research_field',
        'bio',
        'photo',
        'phone',
        'country',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Append custom attributes
     */
    protected $appends = [
        'photo_url',
        'role_name',
    ];

    // ============================================
    // ACCESSORS (Getters)
    // ============================================

    /**
     * Get the full URL of the user's photo
     */
    public function getPhotoUrlAttribute()
    {
        if ($this->photo) {
            return asset('storage/' . $this->photo);
        }
        
        // Default avatar
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&size=200&background=random';
    }

    /**
     * Get the user's primary role name
     */
    public function getRoleNameAttribute()
    {
        return $this->roles->first()?->name ?? 'participant';
    }

    /**
     * Get the user's full name with title (if applicable)
     */
    public function getFullNameAttribute()
    {
        return $this->name;
    }

    // ============================================
    // RELATIONS
    // ============================================

    /**
     * Événements organisés par cet utilisateur
     */
    public function organizedEvents()
    {
        return $this->hasMany(Event::class, 'organizer_id');
    }

    /**
     * Soumissions en tant qu'auteur principal
     */
    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    /**
     * Événements où l'utilisateur est membre du comité scientifique
     */
    public function committeeEvents()
    {
        return $this->belongsToMany(Event::class, 'event_committee')
                    ->withTimestamps();
    }

    /**
     * Évaluations faites par cet utilisateur
     */
    public function evaluations()
    {
        return $this->hasMany(Evaluation::class, 'evaluator_id');
    }

    /**
     * Soumissions assignées à cet utilisateur pour évaluation
     */
    public function assignedSubmissions()
    {
        return $this->hasManyThrough(
            Submission::class,
            Evaluation::class,
            'evaluator_id', // Foreign key on evaluations table
            'id', // Foreign key on submissions table
            'id', // Local key on users table
            'submission_id' // Local key on evaluations table
        )->distinct();
    }

    /**
     * Inscriptions aux événements
     */
    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * Événements auxquels l'utilisateur est inscrit
     */
    public function registeredEvents()
    {
        return $this->belongsToMany(Event::class, 'registrations')
                    ->withPivot('type', 'payment_status', 'registration_date', 'badge_code')
                    ->withTimestamps();
    }

    /**
     * Sessions présidées par cet utilisateur
     */
    public function chairedSessions()
    {
        return $this->hasMany(Session::class, 'session_chair_id');
    }

    /**
     * Workshops animés par cet utilisateur
     */
    public function ledWorkshops()
    {
        return $this->hasMany(Workshop::class, 'leader_id');
    }

    /**
     * Workshops auxquels l'utilisateur est inscrit
     */
    public function registeredWorkshops()
    {
        return $this->belongsToMany(Workshop::class, 'workshop_registrations')
                    ->withTimestamps();
    }

    /**
     * Questions posées par cet utilisateur
     */
    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    /**
     * Votes aux sondages
     */
    public function pollVotes()
    {
        return $this->hasMany(PollVote::class);
    }

    /**
     * Attestations de cet utilisateur
     */
    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * Messages envoyés
     */
    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Messages reçus
     */
    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    /**
     * Messages non lus
     */
    public function unreadMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id')
                    ->where('is_read', false);
    }

    /**
     * Notifications de l'utilisateur
     */
    public function userNotifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Notifications non lues
     */
    public function unreadNotifications()
    {
        return $this->hasMany(Notification::class)
                    ->where('is_read', false);
    }

    // ============================================
    // SCOPES (Query Builders)
    // ============================================

    /**
     * Scope: Filtrer par rôle
     */
    public function scopeWithRole($query, $role)
    {
        return $query->role($role);
    }

    /**
     * Scope: Organisateurs seulement
     */
    public function scopeOrganizers($query)
    {
        return $query->role('organizer');
    }

    /**
     * Scope: Auteurs seulement
     */
    public function scopeAuthors($query)
    {
        return $query->role('author');
    }

    /**
     * Scope: Membres du comité seulement
     */
    public function scopeCommitteeMembers($query)
    {
        return $query->role('committee_member');
    }

    /**
     * Scope: Participants seulement
     */
    public function scopeParticipants($query)
    {
        return $query->role('participant');
    }

    /**
     * Scope: Filtrer par institution
     */
    public function scopeFromInstitution($query, $institution)
    {
        return $query->where('institution', 'like', '%' . $institution . '%');
    }

    /**
     * Scope: Filtrer par pays
     */
    public function scopeFromCountry($query, $country)
    {
        return $query->where('country', $country);
    }

    /**
     * Scope: Recherche par nom ou email
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('name', 'like', '%' . $search . '%')
              ->orWhere('email', 'like', '%' . $search . '%')
              ->orWhere('institution', 'like', '%' . $search . '%');
        });
    }

    // ============================================
    // HELPER METHODS (Méthodes utilitaires)
    // ============================================

    /**
     * Vérifier si l'utilisateur est un organisateur
     */
    public function isOrganizer(): bool
    {
        return $this->hasRole('organizer') || $this->hasRole('super_admin');
    }

    /**
     * Vérifier si l'utilisateur est un auteur
     */
    public function isAuthor(): bool
    {
        return $this->hasRole('author');
    }

    /**
     * Vérifier si l'utilisateur est membre du comité
     */
    public function isCommitteeMember(): bool
    {
        return $this->hasRole('committee_member');
    }

    /**
     * Vérifier si l'utilisateur est un participant
     */
    public function isParticipant(): bool
    {
        return $this->hasRole('participant');
    }

    /**
     * Vérifier si l'utilisateur est super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Vérifier si l'utilisateur est inscrit à un événement
     */
    public function isRegisteredToEvent($eventId): bool
    {
        return $this->registrations()
                    ->where('event_id', $eventId)
                    ->exists();
    }

    /**
     * Vérifier si l'utilisateur est membre du comité d'un événement
     */
    public function isCommitteeMemberOf($eventId): bool
    {
        return $this->committeeEvents()
                    ->where('events.id', $eventId)
                    ->exists();
    }

    /**
     * Vérifier si l'utilisateur est organisateur d'un événement
     */
    public function isOrganizerOf($eventId): bool
    {
        return $this->organizedEvents()
                    ->where('id', $eventId)
                    ->exists();
    }

    /**
     * Obtenir le nombre de soumissions de l'utilisateur
     */
    public function getSubmissionsCount(): int
    {
        return $this->submissions()->count();
    }

    /**
     * Obtenir le nombre de soumissions acceptées
     */
    public function getAcceptedSubmissionsCount(): int
    {
        return $this->submissions()
                    ->where('status', 'accepted')
                    ->count();
    }

    /**
     * Obtenir le nombre d'évaluations à faire
     */
    public function getPendingEvaluationsCount(): int
    {
        // Soumissions assignées qui n'ont pas encore été évaluées par cet utilisateur
        return $this->assignedSubmissions()
                    ->whereDoesntHave('evaluations', function($query) {
                        $query->where('evaluator_id', $this->id);
                    })
                    ->count();
    }

    /**
     * Obtenir le nombre d'événements organisés
     */
    public function getOrganizedEventsCount(): int
    {
        return $this->organizedEvents()->count();
    }

    /**
     * Obtenir le nombre d'événements auxquels l'utilisateur est inscrit
     */
    public function getRegisteredEventsCount(): int
    {
        return $this->registrations()->count();
    }

    /**
     * Obtenir le nombre de messages non lus
     */
    public function getUnreadMessagesCount(): int
    {
        return $this->unreadMessages()->count();
    }

    /**
     * Obtenir le nombre de notifications non lues
     */
    public function getUnreadNotificationsCount(): int
    {
        return $this->unreadNotifications()->count();
    }

    /**
     * Obtenir les statistiques complètes de l'utilisateur
     */
    public function getStatistics(): array
    {
        $stats = [
            'total_submissions' => $this->getSubmissionsCount(),
            'accepted_submissions' => $this->getAcceptedSubmissionsCount(),
            'organized_events' => $this->getOrganizedEventsCount(),
            'registered_events' => $this->getRegisteredEventsCount(),
            'unread_messages' => $this->getUnreadMessagesCount(),
            'unread_notifications' => $this->getUnreadNotificationsCount(),
        ];

        // Statistiques spécifiques au rôle
        if ($this->isCommitteeMember()) {
            $stats['pending_evaluations'] = $this->getPendingEvaluationsCount();
            $stats['completed_evaluations'] = $this->evaluations()->count();
        }

        if ($this->isOrganizer()) {
            $stats['active_events'] = $this->organizedEvents()
                                           ->whereIn('status', ['published', 'ongoing'])
                                           ->count();
        }

        if ($this->hasRole('workshop_leader')) {
            $stats['workshops_led'] = $this->ledWorkshops()->count();
        }

        return $stats;
    }

    /**
     * Obtenir le tableau de bord de l'utilisateur selon son rôle
     */
    public function getDashboardData(): array
    {
        $data = [
            'user' => $this->load('roles', 'permissions'),
            'statistics' => $this->getStatistics(),
        ];

        // Données spécifiques au rôle
        if ($this->isOrganizer()) {
            $data['recent_events'] = $this->organizedEvents()
                                          ->latest()
                                          ->take(5)
                                          ->get();
        }

        if ($this->isAuthor()) {
            $data['recent_submissions'] = $this->submissions()
                                               ->with('event')
                                               ->latest()
                                               ->take(5)
                                               ->get();
        }

        if ($this->isCommitteeMember()) {
            $data['pending_evaluations'] = $this->assignedSubmissions()
                                                ->whereDoesntHave('evaluations', function($query) {
                                                    $query->where('evaluator_id', $this->id);
                                                })
                                                ->with('event', 'author')
                                                ->take(10)
                                                ->get();
        }

        $data['recent_messages'] = $this->receivedMessages()
                                        ->with('sender')
                                        ->latest()
                                        ->take(5)
                                        ->get();

        $data['recent_notifications'] = $this->userNotifications()
                                             ->latest()
                                             ->take(10)
                                             ->get();

        return $data;
    }

    /**
     * Envoyer une notification à l'utilisateur
     */
    public function sendNotification(string $type, string $message, array $data = []): void
    {
        Notification::create([
            'user_id' => $this->id,
            'type' => $type,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * Marquer toutes les notifications comme lues
     */
    public function markAllNotificationsAsRead(): void
    {
        $this->userNotifications()
             ->where('is_read', false)
             ->update([
                 'is_read' => true,
                 'read_at' => now()
             ]);
    }

    /**
     * Marquer tous les messages comme lus
     */
    public function markAllMessagesAsRead(): void
    {
        $this->receivedMessages()
             ->where('is_read', false)
             ->update([
                 'is_read' => true,
                 'read_at' => now()
             ]);
    }

    // ============================================
    // EVENTS (Laravel Model Events)
    // ============================================

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        // Quand un utilisateur est créé
        static::created(function ($user) {
            // Vous pouvez ajouter une logique ici
            // Par exemple: envoyer un email de bienvenue
        });

        // Quand un utilisateur est supprimé
        static::deleting(function ($user) {
            // Nettoyer les données associées si nécessaire
            // Note: Les cascades sont déjà gérées dans les migrations
        });
    }
}