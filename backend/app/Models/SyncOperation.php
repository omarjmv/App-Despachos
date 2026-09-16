<?php

namespace App\Models;

use App\Support\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class SyncOperation extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'device_id', 'client_uuid', 'entity_type', 'payload',
        'status', 'processed_at', 'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
