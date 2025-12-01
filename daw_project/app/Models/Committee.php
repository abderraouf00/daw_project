<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Committee extends Model
{
    protected $fillable = ['event_id', 'user_id', 'role_in_committee'];

    
    // Committee → Event (Many-to-One)
    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    // Committee → User (Many-to-One)
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}