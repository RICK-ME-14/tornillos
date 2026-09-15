<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - venta_detalles.unidad_sunat: código del catálogo 03 (NIU, KGM, MTR, …) que
 *   viaja en el comprobante. Se congela en la línea igual que la afectación,
 *   porque el comprobante debe reflejar la unidad vigente al vender.
 * - ventas.fe_baja_cdr_ruta: CDR de la Comunicación de Baja, que llega al
 *   consultar el ticket y forma parte del respaldo fiscal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->string('unidad_sunat', 5)->default('NIU')->after('tipo_afectacion_igv');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->string('fe_baja_cdr_ruta')->nullable()->after('fe_baja_fecha');
        });
    }

    public function down(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->dropColumn('unidad_sunat');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('fe_baja_cdr_ruta');
        });
    }
};
