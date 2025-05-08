<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class AbsenceType extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'requires_approval',
        'affects_vacation_balance',
        'is_paid',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'requires_approval' => 'boolean',
        'affects_vacation_balance' => 'boolean',
        'is_paid' => 'boolean',
    ];

    /**
     * Get the absences for the absence type.
     */
    public function absences()
    {
        return $this->hasMany(Absence::class);
    }

    /**
     * Get the absence requests for the absence type.
     */
    public function absenceRequests()
    {
        return $this->hasMany(AbsenceRequest::class);
    }
    
    /**
     * Scope a query to only include vacation absence types.
     */
    public function scopeVacation($query)
    {
        return $query->where('code', 'vacation');
    }
    
    /**
     * Scope a query to only include sick leave absence types.
     */
    public function scopeSickLeave($query)
    {
        return $query->where('code', 'sick_leave');
    }
    
    /**
     * Check if this type is vacation.
     */
    public function isVacation()
    {
        return $this->code === 'vacation';
    }
    
    /**
     * Check if this type is sick leave.
     */
    public function isSickLeave()
    {
        return $this->code === 'sick_leave';
    }
    
    /**
     * Check if this type requires approval.
     */
    public function requiresApproval()
    {
        return $this->requires_approval;
    }
    
    /**
     * Check if this type affects vacation balance.
     */
    public function affectsVacationBalance()
    {
        return $this->affects_vacation_balance;
    }
    
    /**
     * Check if this type is paid.
     */
    public function isPaid()
    {
        return $this->is_paid;
    }
}