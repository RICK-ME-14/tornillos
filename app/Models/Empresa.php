<?php

namespace App\Models;

use App\Support\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * La empresa del sistema: razón social, RUC, logo, moneda e IGV que se aplican
 * en toda la aplicación (sidebar, login, POS, ticket y comprobantes).
 *
 * Existe una sola fila. La columna empresa_id que llevan las tablas de negocio
 * se conserva como salvaguarda de las consultas, no como separación de datos.
 */
class Empresa extends Model
{
    protected $table = 'empresas';

    protected $fillable = [
        'nombre', 'ruc', 'direccion', 'telefono', 'email', 'moneda', 'igv', 'logo',
    ];

    protected $casts = [
        'igv' => 'decimal:2',
    ];

    /** Cache en memoria durante el request para evitar consultas repetidas. */
    protected static ?self $cache = null;

    /** Usuarios que pertenecen a esta empresa. */
    public function usuarios()
    {
        return $this->hasMany(User::class);
    }

    /** Productos del tenant (para conteos del panel super admin). */
    public function productos()
    {
        return $this->hasMany(Producto::class);
    }

    /** Ventas del tenant. */
    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }

    // ------------------------------------------------------------------

    /**
     * Devuelve la empresa del sistema. Si aún no existe ninguna, la crea con
     * el nombre de la aplicación para que el primer arranque no falle.
     */
    public static function actual(): self
    {
        $id = Tenant::id();

        if ($id) {
            if (static::$cache && static::$cache->getKey() === $id) {
                return static::$cache;
            }
            $empresa = static::query()->find($id);
            if ($empresa) {
                return static::$cache = $empresa;
            }
        }

        if (static::$cache) {
            return static::$cache;
        }

        return static::$cache = static::query()->firstOr(function () {
            return static::create(['nombre' => config('app.name', 'Mi Empresa')]);
        });
    }

    protected static function booted(): void
    {
        // Invalida la cache si la configuración se actualiza.
        static::saved(fn () => static::$cache = null);
    }

    /** Tasa de IGV como fracción (0.18) */
    public function tasaIgv(): float
    {
        return (float) $this->igv / 100;
    }
}
