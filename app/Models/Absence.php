<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class Absence extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'absence_type_id',
        'request_id',
        'date',
        'is_partial',
        'start_time',
        'end_time',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date',
        'is_partial' => 'boolean',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    /**
     * Get the employee that owns the absence.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the absence type that owns the absence.
     */
    public function absenceType()
    {
        return $this->belongsTo(AbsenceType::class);
    }

     /**
     * Get the absence request that owns the absence.
     */
    public function request()
    {
        return $this->belongsTo(AbsenceRequest::class, 'request_id');
    }
    
    /**
     * Scope a query to only include absences of a specific type.
     */
    public function scopeOfType($query, $typeCode)
    {
        return $query->whereHas('absenceType', function ($query) use ($typeCode) {
            $query->where('code', $typeCode);
        });
    }
    
    /**
     * Scope a query to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }
    
    /**
     * Calculate the duration of this absence in hours (for partial absences).
     */
    public function getDurationHoursAttribute()
    {
        if (!$this->is_partial || !$this->start_time || !$this->end_time) {
            return null;
        }
        
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);
        
        return $end->diffInHours($start);
    }
    
    /**
     * Check if this absence is a vacation day.
     */
    public function isVacation()
    {
        return $this->absenceType->isVacation();
    }
    
    /**
     * Check if this absence is a sick leave day.
     */
    public function isSickLeave()
    {
        return $this->absenceType->isSickLeave();
    }
    
    /**
     * Check if this absence was created from a request.
     */
    public function hasRequest()
    {
        return $this->request_id !== null;
    }
    
    /**
     * Check if this absence is for a full day.
     */
    public function isFullDay()
    {
        return !$this->is_partial;
    }
}