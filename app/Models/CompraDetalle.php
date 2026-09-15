<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class CompraDetalle extends Model
{
    use BelongsToEmpresa;

    protected $table = 'compra_detalles';

    protected $fillable = [
        'empresa_id',
        'compra_id', 'producto_id', 'cantidad', 'precio', 'subtotal',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'cantidad' => 'integer',
    ];

    public function compra()
    {
        return $this->belongsTo(Compra::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
