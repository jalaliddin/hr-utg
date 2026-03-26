<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    protected $fillable = [
        'device_id',
        'organization_id',
        'sync_started_at',
        'sync_finished_at',
        'records_fetched',
        'records_new',
        'records_duplicate',
        'status',
        'error_message',
        'params',
    ];

    protected $casts = [
        'sync_started_at' => 'datetime',
        'sync_finished_at' => 'datetime',
        'params' => 'array',
        'records_fetched' => 'integer',
        'records_new' => 'integer',
        'records_duplicate' => 'integer',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(HikvisionDevice::class, 'device_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
