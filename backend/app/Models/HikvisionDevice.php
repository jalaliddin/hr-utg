<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HikvisionDevice extends Model
{
    protected $table = 'hikvision_devices';

    protected $fillable = [
        'organization_id',
        'name',
        'ip_address',
        'port',
        'username',
        'password',
        'serial_number',
        'status',
        'last_sync_at',
        'last_seen_at',
        'device_info',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'device_info' => 'array',
        'last_sync_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'port' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'device_id');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class, 'device_id');
    }
}
