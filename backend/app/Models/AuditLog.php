<?php

namespace App\Models;

use App\Support\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as RequestFacade;

class AuditLog extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $fillable = [
        'company_id', 'user_id', 'action', 'auditable_type',
        'auditable_id', 'old_values', 'new_values', 'ip_address', 'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $log) {
            $log->created_at ??= now();
        });
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Registra un evento de negocio explícito (aprobar, rechazar, despachar...)
     * con snapshot propio, independiente del estado actual del registro.
     */
    public static function record(string $action, Model $subject, array $context = []): self
    {
        return static::query()->create([
            'company_id' => $subject->company_id ?? null,
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => $subject->getKey(),
            'new_values' => $context,
            'ip_address' => RequestFacade::ip(),
        ]);
    }
}
