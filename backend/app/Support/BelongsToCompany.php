<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Aisla cada modelo tenant-aware por company_id automáticamente.
 *
 * El company_id nunca se acepta desde el cliente: siempre se resuelve
 * del usuario autenticado (o del contexto de consola) para que no exista
 * fuga de información entre empresas (regla 21 del brief).
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new class implements Scope
        {
            public function apply(Builder $builder, Model $model): void
            {
                $companyId = CompanyContext::id();

                if ($companyId !== null) {
                    $builder->where($model->getTable().'.company_id', $companyId);
                }
            }
        });

        static::creating(function (Model $model) {
            if (empty($model->company_id) && CompanyContext::id() !== null) {
                $model->company_id = CompanyContext::id();
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }
}
