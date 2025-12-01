<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name'];
    
    // Role ↔ Users (Many-to-Many through user_roles)
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }
}