<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessTripDestination extends Model
{
    protected $fillable = [
        'business_trip_id', 'organization_id', 'order_index',
        'arrival_date', 'departure_date', 'arrival_signed_by', 'departure_signed_by',
        'note', 'device_id', 'push_status', 'pushed_at', 'push_error',
        'retry_count', 'retry_after',
    ];

    protected $casts = [
        'arrival_date'   => 'datetime',
        'departure_date' => 'datetime',
        'pushed_at'      => 'datetime',
        'retry_after'    => 'datetime',
    ];

    public function businessTrip(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BusinessTrip::class);
    }

    public function organization(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function device(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(HikvisionDevice::class, 'device_id');
    }
}
