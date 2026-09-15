<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class MovimientoInventario extends Model
{
    use BelongsToEmpresa;

    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'empresa_id',
        'producto_id', 'user_id', 'tipo', 'motivo', 'cantidad',
        'stock_anterior', 'stock_nuevo', 'referencia_type', 'referencia_id',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function referencia()
    {
        return $this->morphTo();
    }
}
