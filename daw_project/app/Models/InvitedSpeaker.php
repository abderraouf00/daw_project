<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class InvitedSpeaker extends Model
{
    protected $fillable = ['event_id', 'name', 'institution', 'biography', 'talk_title', 'photo'];

    
    // InvitedSpeaker → Event (Many-to-One)
    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }
}