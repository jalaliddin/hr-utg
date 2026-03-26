<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkSchedule extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'work_start',
        'work_end',
        'lunch_minutes',
        'work_days',
        'late_tolerance_minutes',
        'is_default',
    ];

    protected $casts = [
        'work_days' => 'array',
        'is_default' => 'boolean',
        'lunch_minutes' => 'integer',
        'late_tolerance_minutes' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
