<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id',
        'organization_id',
        'created_by',
        'updated_by',
        'work_date',
        'code',
        'hours',
        'days',
        'note',
        'document_number',
        'document_date',
        'document_type',
        'source',
        'is_approved',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'work_date' => 'date',
        'document_date' => 'date',
        'hours' => 'float',
        'days' => 'float',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
    ];

    /** @var list<string> */
    public const CODES = ['Б', 'К', 'О', 'Р', 'ЧБ', 'У', 'А', 'Я', 'С', 'В', 'Н', 'П'];

    /** @var list<string> Ishda yo'q kodlar */
    public const ABSENT_CODES = ['Б', 'К', 'О', 'Р', 'ЧБ', 'У', 'А'];

    /** @var list<string> Ishlangan kodlar */
    public const WORKED_CODES = ['Я', 'С', 'В', 'Н', 'П'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
