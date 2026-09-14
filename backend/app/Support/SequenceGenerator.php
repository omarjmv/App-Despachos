<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Genera correlativos legibles por empresa (PED-000001, DES-000001...).
 * No usa el id autoincremental global para no filtrar volumen entre empresas.
 */
class SequenceGenerator
{
    public static function next(string $table, string $column, int $companyId, string $prefix): string
    {
        return DB::transaction(function () use ($table, $column, $companyId, $prefix) {
            $last = DB::table($table)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value($column);

            $nextNumber = 1;
            if ($last) {
                $nextNumber = ((int) substr($last, strlen($prefix) + 1)) + 1;
            }

            return $prefix.'-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
        });
    }
}
