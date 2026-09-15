<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

/**
 * Resumen diario de boletas (RC) enviado a SUNAT.
 */
class FacturacionResumen extends Model
{
    use BelongsToEmpresa;

    protected $table = 'facturacion_resumenes';

    protected $fillable = [
        'empresa_id', 'fecha_referencia', 'fecha_generacion', 'identificador',
        'cantidad', 'ticket', 'estado', 'mensaje', 'xml_ruta', 'cdr_ruta',
    ];

    protected $casts = [
        'fecha_referencia' => 'date',
        'fecha_generacion' => 'date',
    ];

    public function ventas()
    {
        return $this->hasMany(Venta::class, 'fe_resumen_id');
    }
}
