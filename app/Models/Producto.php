<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use BelongsToEmpresa;

    protected $table = 'productos';

    /**
     * Tipos de afectación del IGV (catálogo 07 de SUNAT) admitidos en la venta.
     * Solo el gravado genera IGV; exonerado e inafecto van al comprobante con
     * su propio total y sin impuesto.
     */
    public const AFECTACIONES = [
        '10' => 'Gravado (con IGV)',
        '20' => 'Exonerado (sin IGV)',
        '30' => 'Inafecto (sin IGV)',
    ];

    /**
     * Unidades de medida: etiqueta visible y su código en el catálogo 03 de
     * SUNAT, que es el que viaja en el comprobante electrónico.
     *
     *   'CLAVE' => ['Etiqueta', 'CÓDIGO SUNAT']
     */
    public const UNIDADES = [
        'UND' => ['Unidad', 'NIU'],
        'KG' => ['Kilogramo', 'KGM'],
        'LT' => ['Litro', 'LTR'],
        'MT' => ['Metro', 'MTR'],
        'GAL' => ['Galón', 'GLL'],
        'CAJA' => ['Caja', 'BX'],
        'PAQ' => ['Paquete', 'PK'],
        'BOLSA' => ['Bolsa', 'BG'],
        'DOC' => ['Docena', 'DZN'],
        'CIEN' => ['Ciento', 'CEN'],
        'PAR' => ['Par', 'PR'],
        'JGO' => ['Juego', 'SET'],
        'ROLLO' => ['Rollo', 'RO'],
    ];

    protected $fillable = [
        'empresa_id',
        'codigo', 'nombre', 'descripcion', 'categoria_id', 'marca_id',
        'unidad', 'tipo_afectacion_igv', 'precio_compra', 'precio_venta',
        'stock', 'stock_minimo', 'imagen', 'activo',
    ];

    /**
     * Código SUNAT (catálogo 03) de una unidad. Si la unidad es desconocida
     * —por datos antiguos o importados— cae en NIU, que es el valor neutro.
     */
    public static function codigoUnidadSunat(?string $unidad): string
    {
        return static::UNIDADES[$unidad][1] ?? 'NIU';
    }

    /** Código SUNAT de la unidad de este producto. */
    public function unidadSunat(): string
    {
        return static::codigoUnidadSunat($this->unidad);
    }

    protected $casts = [
        'precio_compra' => 'decimal:2',
        'precio_venta' => 'decimal:2',
        'stock' => 'integer',
        'stock_minimo' => 'integer',
        'activo' => 'boolean',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function marca()
    {
        return $this->belongsTo(Marca::class);
    }

    /** Lineas de venta donde aparece este producto. */
    public function ventaDetalles()
    {
        return $this->hasMany(VentaDetalle::class);
    }

    /** Lineas de compra donde aparece este producto. */
    public function compraDetalles()
    {
        return $this->hasMany(CompraDetalle::class);
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoInventario::class);
    }

    public function scopeStockBajo($query)
    {
        return $query->whereColumn('stock', '<=', 'stock_minimo');
    }
}
