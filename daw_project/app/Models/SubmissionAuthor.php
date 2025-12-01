<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SubmissionAuthor extends Model
{
    protected $fillable = ['submission_id', 'user_id'];

    
    // SubmissionAuthor → Submission (Many-to-One)
    public function submission()
    {
        return $this->belongsTo(Submission::class, 'submission_id');
    }

    // SubmissionAuthor → User (Many-to-One)
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}