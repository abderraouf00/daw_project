<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Statistic extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'total_submissions', 'accepted_submissions', 
        'rejected_submissions', 'total_participants', 'total_countries',
        'acceptance_rate', 'data'
    ];

    protected $casts = [
        'data' => 'array',
        'acceptance_rate' => 'decimal:2',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }
}
