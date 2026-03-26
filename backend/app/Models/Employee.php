<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'employee_id',
        'first_name',
        'last_name',
        'middle_name',
        'position',
        'department',
        'department_id',
        'position_id',
        'phone',
        'photo_path',
        'hikvision_card_no',
        'hikvision_person_id',
        'is_device_synced',
        'is_active',
        'hired_at',
        'fired_at',
    ];

    protected $appends = ['photo_url'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_device_synced' => 'boolean',
        'hired_at' => 'date',
        'fired_at' => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @phpstan-ignore-next-line */
    public function departmentRel(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /** @phpstan-ignore-next-line */
    public function positionRel(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function user(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function dailyAttendances(): HasMany
    {
        return $this->hasMany(DailyAttendance::class);
    }

    public function businessTrips(): HasMany
    {
        return $this->hasMany(BusinessTrip::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->last_name} {$this->first_name} {$this->middle_name}");
    }

    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->photo_path
                ? '/storage/'.$this->photo_path
                : null,
        );
    }
}
