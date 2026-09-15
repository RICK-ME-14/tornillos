<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use BelongsToEmpresa;

    protected $table = 'clientes';

    protected $fillable = [
        'empresa_id',
        'nombre', 'tipo_documento', 'numero_documento',
        'telefono', 'email', 'direccion', 'activo',
    ];

    protected $casts = ['activo' => 'boolean'];

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }
}
