<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class WorkshopRegistration extends Model
{
    protected $fillable = ['workshop_id', 'user_id'];

    
    // WorkshopRegistration → Workshop (Many-to-One)
    public function workshop()
    {
        return $this->belongsTo(Workshop::class, 'workshop_id');
    }

    // WorkshopRegistration → User (Many-to-One)
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}