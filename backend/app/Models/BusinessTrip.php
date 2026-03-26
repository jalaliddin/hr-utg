<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessTrip extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id', 'organization_id', 'approved_by',
        'certificate_number', 'certificate_serial', 'certificate_year',
        'destination', 'purpose', 'start_date', 'end_date', 'days_count',
        'transport', 'daily_allowance', 'total_amount', 'description',
        'order_number', 'order_date', 'status', 'reject_reason', 'returned_at', 'attachments',
        'extension_days', 'extended_end_date', 'extension_order_number',
        'extension_order_date', 'extension_reason',
        'passport_series', 'service_id_number',
        'device_push_status', 'device_pushed_at', 'device_push_log',
        'pdf_path', 'pdf_generated_at',
    ];

    protected $casts = [
        'start_date'          => 'date',
        'end_date'            => 'date',
        'order_date'          => 'date',
        'returned_at'         => 'datetime',
        'attachments'         => 'array',
        'days_count'          => 'integer',
        'daily_allowance'     => 'decimal:2',
        'total_amount'        => 'decimal:2',
        'extension_days'      => 'integer',
        'extended_end_date'   => 'date',
        'extension_order_date'=> 'date',
        'device_pushed_at'    => 'datetime',
        'device_push_log'     => 'array',
        'pdf_generated_at'    => 'datetime',
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

    public function destinations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BusinessTripDestination::class)->orderBy('order_index');
    }

    /** Aktiv muddat tugash sanasi (uzaytirilgan bo'lsa uni oladi) */
    public function getEffectiveEndDateAttribute(): \Carbon\Carbon
    {
        return $this->extended_end_date ?? $this->end_date;
    }
}
