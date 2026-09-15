<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - modo_boleta: define si las boletas se envían individualmente a SUNAT o se
 *   declaran únicamente por el Resumen Diario (RC). Evita la doble declaración.
 * - Índices únicos para blindar los correlativos de comprobante y nota de crédito
 *   contra condiciones de carrera (los NULL no colisionan en MySQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturacion_configs', function (Blueprint $table) {
            $table->string('modo_boleta', 15)->default('individual')->after('emitir_automatico'); // individual | resumen
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->unique(['empresa_id', 'fe_serie', 'fe_correlativo'], 'ventas_fe_correlativo_unique');
            $table->unique(['empresa_id', 'fe_nc_serie', 'fe_nc_correlativo'], 'ventas_fe_nc_correlativo_unique');
        });
    }

    public function down(): void
    {
        Schema::table('facturacion_configs', function (Blueprint $table) {
            $table->dropColumn('modo_boleta');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropUnique('ventas_fe_correlativo_unique');
            $table->dropUnique('ventas_fe_nc_correlativo_unique');
        });
    }
};
