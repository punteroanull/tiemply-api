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
        'geolocation_enabled',
        'geolocation_required', 
        'geolocation_radius',
        'office_latitude',
        'office_longitude',
        'office_address',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'max_vacation_days' => 'integer',
        'geolocation_enabled' => 'boolean',
        'geolocation_required' => 'boolean',
        'geolocation_radius' => 'decimal:2',
        'office_latitude' => 'decimal:12', // 12 digits total, 9 after decimal
        'office_longitude' => 'decimal:12',        
    ];

    /**
     * Check if geolocation is enabled for this company.
     */
    public function isGeolocationEnabled()
    {
        return $this->geolocation_enabled;
    }

    /**
     * Check if geolocation is required for this company.
     */
    public function isGeolocationRequired()
    {
        return $this->geolocation_required;
    }

    /**
     * Calculate distance between two coordinates in meters.
     */
    public function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371000; // metros
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat/2) * sin($dLat/2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng/2) * sin($dLng/2);
            
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }

    /**
     * Check if coordinates are within office radius.
     */
    public function isWithinOfficeRadius($latitude, $longitude)
    {
        if (!$this->office_latitude || !$this->office_longitude || !$this->geolocation_radius) {
            return true; // Sin restricciones de ubicación
        }
        
        $distance = $this->calculateDistance(
            $this->office_latitude,
            $this->office_longitude,
            $latitude,
            $longitude
        );
        
        return $distance <= $this->geolocation_radius;
    }

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