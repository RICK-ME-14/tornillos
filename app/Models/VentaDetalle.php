<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class VentaDetalle extends Model
{
    use BelongsToEmpresa;

    protected $table = 'venta_detalles';

    protected $fillable = [
        'empresa_id',
        'venta_id', 'producto_id', 'descripcion', 'cantidad', 'precio',
        'tipo_afectacion_igv', 'unidad_sunat', 'subtotal',
    ];

    /** ¿La línea está gravada con IGV? */
    public function gravado(): bool
    {
        return ($this->tipo_afectacion_igv ?: '10') === '10';
    }

    protected $casts = [
        'precio' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'cantidad' => 'integer',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
