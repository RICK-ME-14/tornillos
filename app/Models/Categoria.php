<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    use BelongsToEmpresa;

    protected $table = 'categorias';

    protected $fillable = [
        'empresa_id',
        'nombre', 'descripcion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }
}
