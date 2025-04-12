<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\HasUuid;


/**
 * Summary of User
 *
 * @property integer $id
 * @property string $uuid
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $identification_number
 * @property \Illuminate\Support\Carbon|null $birth_date
 * @property string|null $phone
 * @property string|null $address
 * @property integer|null $role_id
 * @property \Illuminate\Support\Carbon|null $registered_at
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property \App\Models\Role|null $role
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\Employee[] $employeeRecords
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\Company[] $companies
 */

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuid;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'identification_number',
        'birth_date',
        'phone',
        'address',
        'role_id',
        'registered_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'birth_date' => 'date',
        'registered_at' => 'datetime',
    ];

    /**
     * Get the role that owns the user.
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the employee records for the user.
     */
    public function employeeRecords()
    {
        return $this->hasMany(Employee::class);
    }
    
    /**
     * Get the companies the user belongs to.
     */
    public function companies()
    {
        return $this->belongsToMany(Company::class, 'employees', 'user_id', 'company_id')
                    ->using(Employee::class)
                    ->withPivot(['id', 'contract_start_time', 'contract_end_time', 'remaining_vacation_days', 'active'])
                    ->withTimestamps();
    }
    
    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $roleName): bool
    {
        return $this->role?->name === $roleName;
    }
    
    /**
     * Check if user has access to a company.
     */
    public function belongsToCompany(string $companyId): bool
    {
        return $this->employeeRecords()->where('company_id', $companyId)->where('active', true)->exists();
    }
}