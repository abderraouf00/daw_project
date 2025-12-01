<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PollResponse extends Model
{
    protected $fillable = ['poll_id', 'user_id', 'response'];
    
    // PollResponse → Poll (Many-to-One)
    public function poll()
    {
        return $this->belongsTo(Poll::class, 'poll_id');
    }

    // PollResponse → User (Many-to-One)
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}