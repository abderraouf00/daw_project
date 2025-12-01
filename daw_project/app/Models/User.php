<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'institution', 'bio', 'photo', 
        'research_domain', 'country'
    ];
    
    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function hasRole($roleName)
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    public function createdEvents()
    {
        return $this->hasMany(Event::class, 'created_by');
    }
    
    public function submissions()
    {
        return $this->hasMany(Submission::class, 'user_id');
    }

    public function coAuthoredSubmissions()
    {
        return $this->belongsToMany(Submission::class, 'submission_authors');
    }

    public function reviewAssignments()
    {
        return $this->hasMany(ReviewAssignment::class, 'reviewer_id');
    }

    public function workshopRegistrations()
    {
        return $this->hasMany(WorkshopRegistration::class, 'user_id');
    }

    public function eventRegistrations()
    {
        return $this->hasMany(Registration::class, 'user_id');
    }

    public function chairedSessions()
    {
        return $this->hasMany(Session::class, 'session_chair_id');
    }

    public function pollResponses()
    {
        return $this->hasMany(PollResponse::class, 'user_id');
    }

    public function committees()
    {
        return $this->hasMany(Committee::class, 'user_id');
    }

    public function questions()
    {
        return $this->hasMany(Question::class, 'user_id');
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class, 'user_id');
    }
}