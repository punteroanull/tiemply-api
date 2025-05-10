<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
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

class User extends Authenticatable implements FilamentUser
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

    protected $primary = 'uuid';
    
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
        return $this->employeeRecords()->with('company')->get()->pluck('company');
    }

    public function getCompanies()
    {
        return $this->employeeRecords()
            ->with('company')
            ->get()
            ->map(function ($employee) {
                return $employee->company;
            })
            ->filter(); // Asegúrate de filtrar valores nulos
    }

    public function companiesEloquent()
    {
        return $this->hasManyThrough(
            \App\Models\Company::class, // Modelo final (Company)
            \App\Models\Employee::class, // Modelo intermedio (Employee)
            'user_id', // Clave foránea en la tabla employees
            'id', // Clave foránea en la tabla companies
            'id', // Clave local en la tabla users
            'company_id' // Clave local en la tabla employees
        );
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
        return $this->employeeRecords()->where('company_id', $companyId)->exists();
    }
    
    /**
     * Check if user is an administrator.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('Administrator');
    }
    
    /**
     * Check if user is a manager.
     */
    public function isManager(): bool
    {
        return $this->hasRole('Manager');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() || $this->isManager();
    }
}