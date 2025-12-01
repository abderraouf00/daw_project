<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Workshop extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'title', 'description', 'responsible_id', 
        'date', 'max_places', 'materials_path'
    ];

    protected $casts = [
        'date' => 'datetime',
        'max_places' => 'integer',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function registrations()
    {
        return $this->hasMany(WorkshopRegistration::class, 'workshop_id');
    }

    public function participants()
    {
        return $this->belongsToMany(User::class, 'workshop_registrations');
    }
}
