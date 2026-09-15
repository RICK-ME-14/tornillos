<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - tipo_afectacion_igv: código SUNAT del catálogo 07 (10 gravado, 20 exonerado,
 *   30 inafecto). Se define en el producto y se congela en la línea de venta,
 *   porque el comprobante debe reflejar la afectación vigente al momento de
 *   emitirse aunque el producto cambie después.
 * - fe_baja_correlativo / fe_baja_fecha: numeración diaria de la Comunicación de
 *   Baja (RA-YYYYMMDD-correlativo). SUNAT rechaza dos RA del mismo día con el
 *   mismo correlativo, así que hay que llevar la cuenta por empresa y fecha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('tipo_afectacion_igv', 2)->default('10')->after('unidad');
        });

        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->string('tipo_afectacion_igv', 2)->default('10')->after('precio');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->unsignedInteger('fe_baja_correlativo')->nullable()->after('fe_baja_motivo');
            $table->date('fe_baja_fecha')->nullable()->after('fe_baja_correlativo');

            // Búsqueda del último correlativo de baja del día por empresa.
            $table->index(['empresa_id', 'fe_baja_fecha'], 'ventas_fe_baja_fecha_index');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('tipo_afectacion_igv');
        });

        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->dropColumn('tipo_afectacion_igv');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex('ventas_fe_baja_fecha_index');
            $table->dropColumn(['fe_baja_correlativo', 'fe_baja_fecha']);
        });
    }
};
