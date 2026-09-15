<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class CotizacionDetalle extends Model
{
    use BelongsToEmpresa;

    protected $table = 'cotizacion_detalles';

    protected $fillable = [
        'empresa_id', 'cotizacion_id', 'producto_id', 'descripcion',
        'cantidad', 'precio', 'tipo_afectacion_igv', 'unidad_sunat', 'subtotal',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'precio' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
