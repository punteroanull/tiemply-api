<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
use Carbon\Carbon;

class AbsenceRequest extends Model
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
        'start_date',
        'end_date',
        'is_partial',
        'start_time',
        'end_time',
        'status',
        'reviewed_by',
        'notes',
        'rejection_reason',
        'reviewed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_partial' => 'boolean',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'reviewed_at' => 'datetime',
    ];

    protected $appends = [
        'days_count',
    ];
    /**
     * Get the employee that owns the absence request.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the absence type that owns the absence request.
     */
    public function absenceType()
    {
        return $this->belongsTo(AbsenceType::class);
    }

    /**
     * Get the user who reviewed the request.
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
    
    /**
     * Get the absences for the absence request.
     */
    public function absences()
    {
        return $this->hasMany(Absence::class, 'request_id');
    }
    
    /**
     * Calculate the number of days in the request.
     */
    public function getDaysCountAttribute()
    {
        if (!$this->start_date || !$this->end_date) {
            return 0;
        }
        
        $startDate = Carbon::parse($this->start_date);
        $endDate = Carbon::parse($this->end_date);
        
        // If company uses business days, count only weekdays
        if ($this->employee->company->vacation_type === 'business_days') {
            $days = 0;
            $currentDate = clone $startDate;
            
            while ($currentDate->lte($endDate)) {
                if (!$currentDate->isWeekend()) {
                    $days++;
                }
                $currentDate->addDay();
            }
            
            return $days;
        }
        
        // For calendar days, include all days in the range
        return $startDate->diffInDays($endDate) + 1;
    }

    /**
     * Scope a query to only include pending requests.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
    
    /**
     * Scope a query to only include approved requests.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
    
    /**
     * Scope a query to only include rejected requests.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
    
    /**
     * Check if the request is pending.
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }
    
    /**
     * Check if the request is approved.
     */
    public function isApproved()
    {
        return $this->status === 'approved';
    }
    
    /**
     * Check if the request is rejected.
     */
    public function isRejected()
    {
        return $this->status === 'rejected';
    }
    
    /**
     * Check if the request needs minimum notice (24 hours).
     */
    public function needsMinimumNotice()
    {
        // Vacation requests for consecutive days need minimum notice
        if ($this->absenceType->code === 'vacation' && $this->getDaysCountAttribute() > 1) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if the request meets minimum consecutive days requirement.
     */
    public function meetsConsecutiveDaysRequirement()
    {
        // If calendar days and vacation request, need minimum 7 consecutive days
        if ($this->employee->company->vacation_type === 'calendar_days' && 
            $this->absenceType->code === 'vacation') {
            return $this->getDaysCountAttribute() >= 7;
        }
        
        return true;
    }
    /**
     * Check if the employee has enough vacation days for this request.
     */
    public function hasEnoughVacationDays()
    {
        if ($this->absenceType->affects_vacation_balance) {
            $daysRequested = $this->getDaysCountAttribute();
            
            return $daysRequested <= $this->employee->remaining_vacation_days;
        }
        
        return true;
    }
    
    /**
     * Create absence records for an approved request.
     */
    public function createAbsenceRecords()
    {
        if (!$this->isApproved()) {
            return [];
        }
        
        $absences = [];
        $startDate = Carbon::parse($this->start_date);
        $endDate = Carbon::parse($this->end_date);
        $isPartial = $this->is_partial;
        $startTime = $this->start_time;
        $endTime = $this->end_time;
        
        $currentDate = clone $startDate;
        
        while ($currentDate->lte($endDate)) {
            // Skip weekends if company uses business days
            if ($this->employee->company->vacation_type === 'business_days' && $currentDate->isWeekend()) {
                $currentDate->addDay();
                continue;
            }
            
            $absence = Absence::create([
                'employee_id' => $this->employee_id,
                'absence_type_id' => $this->absence_type_id,
                'request_id' => $this->id,
                'date' => $currentDate->toDateString(),
                'is_partial' => $isPartial,
                'start_time' => $isPartial ? $startTime : null,
                'end_time' => $isPartial ? $endTime : null,
                'notes' => $this->notes,
            ]);
            
            $absences[] = $absence;
            $currentDate->addDay();
        }
        
        return $absences;
    }
    
    /**
     * Update employee vacation balance after approval.
     */
    public function updateVacationBalance()
    {
        if (!$this->isApproved() || !$this->absenceType->affects_vacation_balance) {
            return false;
        }
        
        $daysCount = $this->getDaysCountAttribute();
        $employee = $this->employee;
        
        if ($employee->remaining_vacation_days >= $daysCount) {
            $employee->remaining_vacation_days -= $daysCount;
            $employee->save();
            return true;
        }
        
        return false;
    }
    
    /**
     * Approve the request.
     * 
     * @param int $reviewerId
     * @return bool
     */
    public function approve($reviewerId)
    {
        if (!$this->isPending()) {
            return false;
        }
        
        // Begin transaction
        \DB::beginTransaction();
        
        try {
            // Update request status
            $this->status = 'approved';
            $this->reviewed_by = $reviewerId;
            $this->reviewed_at = now();
            $this->save();
            
            // Create absence records
            $this->createAbsenceRecords();
            
            // Update vacation balance if needed
            if ($this->absenceType->affects_vacation_balance) {
                $success = $this->updateVacationBalance();
                
                if (!$success) {
                    \DB::rollBack();
                    return false;
                }
            }
            
            \DB::commit();
            return true;
        } catch (\Exception $e) {
            \DB::rollBack();
            return false;
        }
    }
    
    /**
     * Reject the request.
     * 
     * @param int $reviewerId
     * @param string $reason
     * @return bool
     */
    public function reject($reviewerId, $reason = null)
    {
        if (!$this->isPending()) {
            return false;
        }
        
        $this->status = 'rejected';
        $this->reviewed_by = $reviewerId;
        $this->reviewed_at = now();
        $this->rejection_reason = $reason ?? 'Request rejected without specific reason.';
        
        return $this->save();
    }
}