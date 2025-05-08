<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class Company extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'tax_id',
        'contact_email',
        'contact_person',
        'address',
        'phone',
        'vacation_type',
        'max_vacation_days',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'max_vacation_days' => 'integer',
    ];

    /**
     * Get the employees for the company.
     */
    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Get all users associated with this company through employees.
     */
    public function users()
    {
        return $this->hasManyThrough(User::class, Employee::class, 'company_id', 'id', 'id', 'user_id');
    }

     /**
     * Get all absence requests for the company.
     */
    public function absenceRequests()
    {
        return $this->hasManyThrough(
            AbsenceRequest::class,
            Employee::class,
            'company_id', // Foreign key on employees table
            'employee_id', // Foreign key on absence_requests table
            'id', // Local key on companies table
            'id' // Local key on employees table
        );
    }
    
    /**
     * Get all absences for the company.
     */
    public function absences()
    {
        return $this->hasManyThrough(
            Absence::class,
            Employee::class,
            'company_id', // Foreign key on employees table
            'employee_id', // Foreign key on absences table
            'id', // Local key on companies table
            'id' // Local key on employees table
        );
    }
    
    /**
     * Check if company uses business days for vacations.
     */
    public function usesBusinessDays()
    {
        return $this->vacation_type === 'business_days';
    }
    
    /**
     * Check if company uses calendar days for vacations.
     */
    public function usesCalendarDays()
    {
        return $this->vacation_type === 'calendar_days';
    }
}