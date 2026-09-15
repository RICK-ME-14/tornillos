<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class Compra extends Model
{
    use BelongsToEmpresa;

    protected $table = 'compras';

    protected $fillable = [
        'empresa_id',
        'numero', 'proveedor_id', 'user_id', 'fecha',
        'subtotal', 'impuesto', 'total', 'estado', 'observacion',
    ];

    protected $casts = [
        'fecha' => 'date',
        'subtotal' => 'decimal:2',
        'impuesto' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles()
    {
        return $this->hasMany(CompraDetalle::class);
    }
}
