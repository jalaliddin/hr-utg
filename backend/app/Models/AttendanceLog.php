<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    protected $fillable = [
        'organization_id',
        'device_id',
        'employee_id',
        'hikvision_person_id',
        'hikvision_card_no',
        'event_type',
        'event_time',
        'door_name',
        'raw_data',
        'is_processed',
    ];

    protected $casts = [
        'event_time' => 'datetime',
        'raw_data' => 'array',
        'is_processed' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(HikvisionDevice::class, 'device_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
