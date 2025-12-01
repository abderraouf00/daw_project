<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'description', 'location', 'start_date', 'end_date', 
        'theme', 'contact', 'created_by', 'status'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class, 'event_id');
    }

    public function sessions()
    {
        return $this->hasMany(Session::class, 'event_id');
    }

    public function workshops()
    {
        return $this->hasMany(Workshop::class, 'event_id');
    }

    public function committees()
    {
        return $this->hasMany(Committee::class, 'event_id');
    }

    public function invitedSpeakers()
    {
        return $this->hasMany(InvitedSpeaker::class, 'event_id');
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class, 'event_id');
    }

    public function polls()
    {
        return $this->hasMany(Poll::class, 'event_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'event_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'event_id');
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class, 'event_id');
    }

    public function statistics()
    {
        return $this->hasMany(Statistic::class, 'event_id');
    }
}