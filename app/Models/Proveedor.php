<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    use BelongsToEmpresa;

    protected $table = 'proveedores';

    protected $fillable = [
        'empresa_id',
        'nombre', 'ruc', 'telefono', 'email', 'direccion', 'contacto', 'activo',
    ];

    protected $casts = ['activo' => 'boolean'];

    public function compras()
    {
        return $this->hasMany(Compra::class);
    }
}
