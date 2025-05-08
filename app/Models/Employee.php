<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use App\Traits\HasUuid;

class Employee extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'user_id',
        'contract_start_time',
        'contract_end_time',
        'remaining_vacation_days',
        'active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'contract_start_time' => 'datetime:H:i',
        'contract_end_time' => 'datetime:H:i',
        'remaining_vacation_days' => 'integer',
        'active' => 'boolean',
    ];

    /**
     * Get the company that owns the employee.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user that owns the employee.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the work logs for the employee.
     */
    public function workLogs()
    {
        return $this->hasMany(WorkLog::class);
    }

    /**
     * Get the absences for the employee.
     */
    public function absences()
    {
        return $this->hasMany(Absence::class);
    }

    /**
     * Get the absence requests for the employee.
     */
    public function absenceRequests()
    {
        return $this->hasMany(AbsenceRequest::class);
    }
    
    /**
     * Get the employee's total scheduled hours per day.
     */
    public function getScheduledHoursAttribute()
    {
        if (!$this->contract_start_time || !$this->contract_end_time) {
            return null;
        }
        
        $start = \Carbon\Carbon::parse($this->contract_start_time);
        $end = \Carbon\Carbon::parse($this->contract_end_time);
        
        return $end->diffInHours($start);
    }
    
    /**
     * Get work logs for a specific date.
     */
    public function getWorkLogsForDate($date)
    {
        return $this->workLogs()
                    ->whereDate('date', $date)
                    ->orderBy('time')
                    ->get();
    }

    /**
     * Get check-in and check-out for a specific date.
     */
    public function getAttendanceForDate($date)
    {
        $logs = $this->getWorkLogsForDate($date);
        
        $checkIn = $logs->where('type', 'check_in')->first();
        $checkOut = $logs->where('type', 'check_out')->first();
        
        return [
            'date' => $date,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'worked_hours' => $checkIn && $checkOut ? 
                Carbon::parse($checkIn->time)->diffInHours(Carbon::parse($checkOut->time)) : null
        ];
    }
    
    /**
     * Calculate used vacation days in a given year.
     */
    public function getUsedVacationDays($year = null)
    {
        $year = $year ?? date('Y');
        
        $vacationType = AbsenceType::where('code', 'vacation')->first();
        
        if (!$vacationType) {
            return 0;
        }
        
        $absences = $this->absences()
            ->where('absence_type_id', $vacationType->id)
            ->whereYear('date', $year)
            ->count();
            
        return $absences;
    }
}