<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class Role extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'is_exempt',
        'access_level',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_exempt' => 'boolean',
        'access_level' => 'integer',
    ];

    /**
     * Get the users for the role.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Check if role is Administrator.
     */
    public function isAdmin()
    {
        return $this->name === 'Administrator';
    }
    
    /**
     * Check if role is Manager.
     */
    public function isManager()
    {
        return $this->name === 'Manager';
    }
    
    /**
     * Check if role is Employee.
     */
    public function isEmployee()
    {
        return $this->name === 'Employee';
    }
    
    /**
     * Check if role is exempt from time tracking.
     */
    public function isExempt()
    {
        return $this->is_exempt;
    }
    
    /**
     * Check if role has at least the given access level.
     * 
     * @param int $level
     * @return bool
     */
    public function hasAccessLevel($level)
    {
        return $this->access_level >= $level;
    }
}