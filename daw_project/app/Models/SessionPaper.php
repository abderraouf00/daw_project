<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SessionPaper extends Model
{
    protected $fillable = ['session_id', 'submission_id', 'presentation_order'];

   
    
    // SessionPaper → Session (Many-to-One)
    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    // SessionPaper → Submission (Many-to-One)
    public function submission()
    {
        return $this->belongsTo(Submission::class, 'submission_id');
    }
}