<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ReviewAssignment extends Model
{
    protected $fillable = ['submission_id', 'reviewer_id', 'status'];

    
    // ReviewAssignment → Submission (Many-to-One)
    public function submission()
    {
        return $this->belongsTo(Submission::class, 'submission_id');
    }

    // ReviewAssignment → User (Many-to-One: The reviewer)
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    // ReviewAssignment → Review (One-to-One)
    public function review()
    {
        return $this->hasOne(Review::class, 'assignment_id');
    }
}