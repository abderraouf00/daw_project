<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = ['assignment_id', 'score_quality', 'score_originality', 'score_relevance', 'comments', 'recommendation'];

    
    // Review → ReviewAssignment (Many-to-One)
    public function assignment()
    {
        return $this->belongsTo(ReviewAssignment::class, 'assignment_id');
    }
}