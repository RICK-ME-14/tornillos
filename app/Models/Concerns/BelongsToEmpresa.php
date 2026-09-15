<?php

namespace App\Models\Concerns;

use App\Models\Empresa;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aísla automáticamente un modelo por empresa (tenant):
 *  - Al consultar, filtra por la empresa activa (global scope).
 *  - Al crear, rellena empresa_id con la empresa activa si no viene dado.
 *
 * Cuando no hay tenant en contexto (consola/seeder sin empresa fijada) no
 * aplica filtro, para no ocultar datos en operaciones administrativas.
 */
trait BelongsToEmpresa
{
    protected static function bootBelongsToEmpresa(): void
    {
        static::addGlobalScope('empresa', function (Builder $builder) {
            if (Tenant::check()) {
                $builder->where(
                    $builder->getModel()->getTable() . '.empresa_id',
                    Tenant::id()
                );
            }
        });

        static::creating(function ($model) {
            if (empty($model->empresa_id) && Tenant::check()) {
                $model->empresa_id = Tenant::id();
            }
        });
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
}
