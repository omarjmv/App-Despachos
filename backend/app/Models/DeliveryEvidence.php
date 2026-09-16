<?php

namespace App\Models;

use App\Enums\EvidenceType;
use Illuminate\Database\Eloquent\Model;

class DeliveryEvidence extends Model
{
    protected $fillable = ['delivery_id', 'type', 'file_path', 'mime_type', 'size_bytes', 'captured_at'];

    protected $casts = [
        'type' => EvidenceType::class,
        'captured_at' => 'datetime',
    ];

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }
}
