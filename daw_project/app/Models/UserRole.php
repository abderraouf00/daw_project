<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    protected $fillable = ['user_id', 'role_id'];

    
    // UserRole → User (Many-to-One)
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // UserRole → Role (Many-to-One)
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}