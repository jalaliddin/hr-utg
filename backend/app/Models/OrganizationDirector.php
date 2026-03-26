<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationDirector extends Model
{
    protected $fillable = [
        'organization_id', 'full_name', 'position', 'short_name', 'is_active', 'appointed_at',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'appointed_at' => 'date',
    ];

    public function organization(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
