<?php

namespace App\Models;

use App\Enums\ReviewResult;
use App\Support\Auditable;
use App\Support\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use Auditable, BelongsToCompany;

    protected $fillable = [
        'company_id', 'preparation_id', 'reviewed_by', 'result',
        'rejection_reason', 'reviewed_at',
    ];

    protected $casts = [
        'result' => ReviewResult::class,
        'reviewed_at' => 'datetime',
    ];

    public function preparation()
    {
        return $this->belongsTo(Preparation::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function dispatch()
    {
        return $this->hasOne(Dispatch::class);
    }
}
