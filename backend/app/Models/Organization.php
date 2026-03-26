<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'code',
        'type',
        'address',
        'phone',
        'is_active',
        'hikvision_group_no',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(HikvisionDevice::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function workSchedules(): HasMany
    {
        return $this->hasMany(WorkSchedule::class);
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

    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }

    public function directors(): HasMany
    {
        return $this->hasMany(OrganizationDirector::class);
    }

    public function activeDirector(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OrganizationDirector::class)->where('is_active', true)->latest('appointed_at');
    }
}
