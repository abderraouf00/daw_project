<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    protected $fillable = ['event_id', 'title', 'room', 'start_time', 'end_time', 'session_chair_id'];

    
    // Session → Event (Many-to-One)
    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    // Session → User (Many-to-One: The session chair)
    public function chair()
    {
        return $this->belongsTo(User::class, 'session_chair_id');
    }

    // Session → SessionPapers (One-to-Many)
    public function sessionPapers()
    {
        return $this->hasMany(SessionPaper::class, 'session_id');
    }

    // Session → Submissions (Many-to-Many through SessionPapers)
    public function submissions()
    {
        return $this->belongsToMany(Submission::class, 'session_papers');
    }

    // Session → Questions (One-to-Many)
    public function questions()
    {
        return $this->hasMany(Question::class, 'session_id');
    }

    
}