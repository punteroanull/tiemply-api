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
        
        $start = \Carbon\Carbon::parse($this->start_time);
        $end = \Carbon\Carbon::parse($this->end_time);
        
        return $end->diffInHours($start);
    }

    /**
     * Get the notes for the absence.
     */
    public function getNotesAttribute($value)
    {
        return $value ?: 'No notes provided.';
    }
}