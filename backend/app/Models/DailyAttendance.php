<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyAttendance extends Model
{
    protected $table = 'daily_attendance';

    protected $fillable = [
        'employee_id',
        'organization_id',
        'work_date',
        'first_entry',
        'last_exit',
        'work_minutes',
        'status',
        'note',
    ];

    protected $casts = [
        'work_date' => 'date',
        'work_minutes' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function getWorkHoursAttribute(): float
    {
        return round($this->work_minutes / 60, 2);
    }
}
