<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id', 'user_id', 'question', 'votes', 'is_answered'
    ];

    protected $casts = [
        'votes' => 'integer',
        'is_answered' => 'boolean',
    ];

    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
