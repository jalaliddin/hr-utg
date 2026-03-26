<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingDeviceDeletion extends Model
{
    protected $fillable = ['device_id', 'hikvision_person_id'];
}
