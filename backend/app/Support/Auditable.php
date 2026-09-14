<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as RequestFacade;

/**
 * Registra creación/modificación/borrado como snapshots independientes,
 * en vez de depender del estado actual del registro (requisito sección 15).
 *
 * Para transiciones de negocio explícitas (aprobar, rechazar, despachar...)
 * se usa AuditLog::record() directamente desde los Services con la acción
 * de negocio real, en vez del genérico "updated" que deja este observer.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => static::writeAuditLog($model, 'CREAR', null, $model->getAttributes()));

        static::updated(function (Model $model) {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if (empty($changes)) {
                return;
            }

            static::writeAuditLog($model, 'ACTUALIZAR', $model->getOriginal(), $changes);
        });

        static::deleted(fn (Model $model) => static::writeAuditLog($model, 'ELIMINAR', $model->getAttributes(), null));
    }

    protected static function writeAuditLog(Model $model, string $action, ?array $old, ?array $new): void
    {
        AuditLog::query()->create([
            'company_id' => $model->company_id ?? CompanyContext::id(),
            'user_id' => Auth::id(),
            'action' => $action.'_'.strtoupper(class_basename($model)),
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => RequestFacade::ip(),
        ]);
    }
}
