<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class Marca extends Model
{
    use BelongsToEmpresa;

    protected $table = 'marcas';

    protected $fillable = [
        'empresa_id',
        'nombre', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }
}
