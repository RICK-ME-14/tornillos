<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-tenancy (base de datos compartida + columna empresa_id).
 * Cada fila de "empresas" es un tenant (un negocio). Todas las tablas de
 * negocio quedan asociadas a una empresa para aislar los datos.
 */
return new class extends Migration
{
    /** Tablas de negocio que reciben empresa_id. */
    private array $tablas = [
        'users',
        'categorias',
        'marcas',
        'proveedores',
        'clientes',
        'productos',
        'ventas',
        'venta_detalles',
        'compras',
        'compra_detalles',
        'movimientos_inventario',
    ];

    public function up(): void
    {
        foreach ($this->tablas as $tabla) {
            if (! Schema::hasColumn($tabla, 'empresa_id')) {
                Schema::table($tabla, function (Blueprint $table) {
                    // Nullable para no romper filas existentes; se puebla luego.
                    $table->foreignId('empresa_id')->nullable()->after('id')->index();
                });
            }
        }

        // El código de producto debe ser único POR empresa, no global.
        Schema::table('productos', function (Blueprint $table) {
            $table->dropUnique('productos_codigo_unique');
            $table->unique(['empresa_id', 'codigo'], 'productos_empresa_codigo_unique');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropUnique('productos_empresa_codigo_unique');
            $table->unique('codigo', 'productos_codigo_unique');
        });

        foreach ($this->tablas as $tabla) {
            if (Schema::hasColumn($tabla, 'empresa_id')) {
                Schema::table($tabla, function (Blueprint $table) {
                    $table->dropColumn('empresa_id');
                });
            }
        }
    }
};
