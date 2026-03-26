<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyTabel extends Model
{
    protected $table = 'monthly_tabel';

    protected $fillable = [
        'employee_id',
        'organization_id',
        'year',
        'month',
        'sick_days',
        'trip_days',
        'vacation_days',
        'maternity_days',
        'childcare_days',
        'study_days',
        'admin_leave_days',
        'actual_hours',
        'overtime_hours',
        'evening_hours',
        'night_hours',
        'holiday_hours',
        'work_days_count',
        'calendar_days',
        'total_hours',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
