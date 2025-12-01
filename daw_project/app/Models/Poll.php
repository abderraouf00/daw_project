<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Poll extends Model
{
    protected $fillable = ['event_id', 'question_text'];

    
    // Poll → Event (Many-to-One)
    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    // Poll → PollResponses (One-to-Many)
    public function responses()
    {
        return $this->hasMany(PollResponse::class, 'poll_id');
    }

    // Poll → Users (Many-to-Many through PollResponses)
    public function voters()
    {
        return $this->belongsToMany(User::class, 'poll_responses');
    }
}