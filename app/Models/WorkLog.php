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
}