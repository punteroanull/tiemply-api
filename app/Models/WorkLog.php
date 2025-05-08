<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class WorkLog extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'date',
        'time',
        'type',
        'category',
        'paired_log_id',
        'ip_address',
        'location',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date',
        'time' => 'datetime:H:i:s',
    ];

    /**
     * Get the employee that owns the work log.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

     /**
     * Get the paired log entry.
     */
    public function pairedLog()
    {
        return $this->belongsTo(WorkLog::class, 'paired_log_id');
    }
    
    /**
     * Get the logs that are paired with this one.
     */
    public function pairedLogs()
    {
        return $this->hasMany(WorkLog::class, 'paired_log_id');
    }
    
    /**
     * Scope a query to only include check-ins.
     */
    public function scopeCheckIns($query)
    {
        return $query->where('type', 'check_in');
    }
    
    /**
     * Scope a query to only include check-outs.
     */
    public function scopeCheckOuts($query)
    {
        return $query->where('type', 'check_out');
    }
    
    /**
     * Scope a query to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }
    
    /**
     * Check if this log is a start event (check-in type).
     */
    public function isStartEvent()
    {
        return $this->type === 'check_in';
    }
    
    /**
     * Check if this log is an end event (check-out type).
     */
    public function isEndEvent()
    {
        return $this->type === 'check_out';
    }
    
    /**
     * Calculate duration from this log to its paired log.
     */
    public function getDurationInMinutes()
    {
        if ($this->isStartEvent() && $this->pairedLogs->isNotEmpty()) {
            $pairedLog = $this->pairedLogs->first();
            $start = Carbon::parse($this->time);
            $end = Carbon::parse($pairedLog->time);
            return $end->diffInMinutes($start);
        } elseif ($this->isEndEvent() && $this->pairedLog) {
            $start = Carbon::parse($this->pairedLog->time);
            $end = Carbon::parse($this->time);
            return $end->diffInMinutes($start);
        }
        
        return null;
    }
    
    /**
     * Get the formatted duration.
     */
    public function getFormattedDurationAttribute()
    {
        $minutes = $this->getDurationInMinutes();
        
        if ($minutes === null) {
            return null;
        }
        
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        return sprintf('%02d:%02d', $hours, $remainingMinutes);
    }
}