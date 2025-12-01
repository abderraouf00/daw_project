<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Submission extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'user_id', 'title', 'abstract', 'keywords', 
        'type', 'status', 'file_path'
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function mainAuthor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function coAuthors()
    {
        return $this->belongsToMany(User::class, 'submission_authors');
    }

    public function submissionAuthors()
    {
        return $this->hasMany(SubmissionAuthor::class, 'submission_id');
    }

    public function reviewAssignments()
    {
        return $this->hasMany(ReviewAssignment::class, 'submission_id');
    }

    public function sessions()
    {
        return $this->belongsToMany(Session::class, 'session_papers');
    }

    public function sessionPapers()
    {
        return $this->hasMany(SessionPaper::class, 'submission_id');
    }
}